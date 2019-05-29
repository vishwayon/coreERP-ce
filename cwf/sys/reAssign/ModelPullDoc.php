<?php

namespace app\cwf\sys\reAssign;

class ModelPullDoc {

    public $dt_request;
    public $doc_bo_id = '';
    public $from_user_id = -1;
    public $to_user_id = -1;
    public $find_vch_id = '';
    public $brokenrules = array();

    public function __construct() {
        
    }

    public function setFilters($filter) {
        $this->doc_bo_id = $filter['doc_bo_id'] == -1 ? '' : $filter['doc_bo_id'];
        $this->from_user_id = $filter['from_user_id'] == '' ? -1 : (int) $filter['from_user_id'];
        $this->to_user_id = $filter['to_user_id'] == '' ? -1 : (int) $filter['to_user_id'];
        $this->find_vch_id = $filter['find_vch_id'];
        if ($this->doc_bo_id != '' || $this->from_user_id != -1 || $this->to_user_id != -1 || $this->find_vch_id != '') {
            $this->getData();
        } else {
            $this->brokenrules[] = 'Please select at least one option - Document Type / From User / To User.';
        }
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "select doc_id, bo_id, edit_view, doc_name, doc_sender_comment, user_id_from, doc_sent_on::date, doc_action, 
                            user_id_to, last_updated, doc_stage_id, doc_stage_id_from, branch_id, '' as from_user, user_id_to,
                            '' as to_user, false as select, -1 as new_user_id_to, '' as remark, null as next_role 
                    from sys.doc_wf 
                    where user_id_to <> -1 and (case when :puser_id_from <> -1 then user_id_from = :puser_id_from else :puser_id_from = -1 end)
                        And (case when :puser_id_to <> -1 then user_id_to = :puser_id_to else :puser_id_to = -1 end) 
                        And (case when :pdoc_id <> '' then doc_id = :pdoc_id else :pdoc_id = '' end) 
                        And (case when :pbo_id <> '' then bo_id = :pbo_id else :pbo_id = '' end)
                        And branch_id = :pbranch_id and 
                        finyear = :pfinyear";
        $cmm->addParam('puser_id_from', $this->from_user_id);
        $cmm->addParam('puser_id_to', $this->to_user_id);
        $cmm->addParam('pdoc_id', $this->find_vch_id);
        $cmm->addParam('pbo_id', $this->doc_bo_id);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        
        $cmm->setCommandText($cmmtext);
        $this->dt_request = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select user_id, full_user_name, email from sys.user";
        $cmm->setCommandText($cmmtext);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $this->dt_request->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($this->dt_request->Rows() as &$row_req) {
            $row_req['doc_date_sort'] = strtotime($row_req['doc_sent_on']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_req['user_id_from'] == $row_user['user_id']) {
                    $row_req['from_user'] = $row_user['full_user_name'];
                }
                if ($row_req['user_id_to'] == $row_user['user_id']) {
                    $row_req['to_user'] = $row_user['full_user_name'];
                }
            }
        }
        foreach ($this->dt_request->Rows() as &$row_req) {
            $row_req['next_role'] = $this->getNextRoleInfo($row_req);
        }
    }

    public function setData($model) {
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        try {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Update sys.doc_wf 
                                set user_id_to = :pnew_user_id_to, 
                                doc_sender_comment = :pcomment,
                                last_updated = now() 
                                where doc_id = :pdoc_id");
            $cmm->addParam('pnew_user_id_to', -1);
            $cmm->addParam('pdoc_id', '');
            $cmm->addParam('pcomment', '');
            $cn->beginTransaction();
            for ($rowIndex = 0; $rowIndex < count($model->dt_request); $rowIndex++) {
                if ($model->dt_request[$rowIndex]->select == TRUE &&
                        $model->dt_request[$rowIndex]->new_user_id_to != -1 &&
                        $model->dt_request[$rowIndex]->new_user_id_to != $model->dt_request[$rowIndex]->user_id_to) {
                    $cmmt = $model->dt_request[$rowIndex]->doc_sender_comment.
                            ' Reassigned by '.\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName()
                            .' '.$model->dt_request[$rowIndex]->remark;
                    $cmm->setParamValue('pnew_user_id_to', $model->dt_request[$rowIndex]->new_user_id_to);
                    $cmm->setParamValue('pdoc_id', $model->dt_request[$rowIndex]->doc_id);
                    $cmm->setParamValue('pcomment', $cmmt);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }
            $cn->commit();
            $cn = null;
            $this->logAction($model);
            return 'OK';
        } catch (\Exception $ex) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
            }
            return $ex->getMessage();
        }
    }

    private function logAction($model) {
        $json_data = json_encode($model, JSON_HEX_APOS);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('insert into sys.user_action_log (utility_name, user_id,machine_name,json_log)'
                . ' (select :putil, :puser_id, :pmachine_name, :pjson_log)');
        $cmm->addParam('putil', 'Reassign Doc Workflow');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pmachine_name', gethostname());
        $cmm->addParam('pjson_log', $json_data);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    private function getNextRoleInfo($docInfo): \app\cwf\vsla\security\RoleInfo {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $roleinfo = new \app\cwf\vsla\security\RoleInfo();
        if ($docInfo['doc_stage_id'] == '') {
            // doc is singleStage Alternate Logic: (See if any role has posting rights)
            $sql = 'Select a.role_id as next_role_id, c.role_name as next_role_name
                    From sys.role_access_level a
                    Inner Join sys.menu b On a.menu_id=b.menu_id
                    Inner Join sys.role c On a.role_id=c.role_id
                    Inner Join sys.user_branch_role d On c.role_id = d.role_id
                    where d.branch_id = :pbranch_id
                            And b.bo_id=md5(:pbo_id)::uuid
                            And a.en_access_level = 3
                    Group by a.role_id, c.role_name';
            $cmm->setCommandText($sql);
            $cmm->addParam('pbranch_id', intval($docInfo['branch_id']));
            $cmm->addParam('pbo_id', $docInfo['bo_id']);
            $roleData = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($roleData->Rows()) >= 1) {
                $roleinfo->role_id = -1; // $roleData->Rows()[0]['role_id'] commented for alternate logic
                $roleinfo->role_name = ''; // $roleData->Rows()[0]['role_name'] commented for alternate logic
                foreach ($roleData->Rows() as $nr) {
                    if (strlen($roleinfo->next_role_id) > 0) {
                        $roleinfo->next_role_id .= ', ';
                        $roleinfo->next_role_name .= ', ';
                    }
                    $roleinfo->next_role_id .= $nr['next_role_id'];
                    $roleinfo->next_role_name .= $nr['next_role_name'];
                }
                $roleinfo->resultStatus = 'OK';
                return $roleinfo;
            } else {
                $roleinfo->resultStatus = 'NA';
                $roleinfo->resultMsg = 'Role does not have a parent role';
                return $roleinfo;
            }
        } else {
            // doc is multiStage
            // next role could be an array of roles having access
            $nextStage = '';
            $docStageInfo = $this->getStages($docInfo['edit_view']);
            $cstage = $docInfo['doc_stage_id'];
            if ($cstage) {
                $sql = "With role_al
                        As 
                        (   Select distinct a.role_id
                            From sys.role_access_level a
                            Inner Join sys.user_branch_role b On a.role_id=b.role_id
                            Where a.menu_id in (Select menu_id from sys.menu
                                              Where bo_id=md5(:pbo_id)::uuid)
                                    And a.en_access_level >= 2 
                                    And b.branch_id = :pbranch_id
                                    And a.doc_stages @> Array[:pdoc_stage_id]::Varchar[]
                        )
                        Select x.role_id as next_role_id, x.role_name as next_role_name
                        From sys.role x
                        Inner Join role_al y On x.role_id = y.role_id";
                $cmm->setCommandText($sql);
                $cmm->addParam('pbo_id', $docInfo['bo_id']);
                $cmm->addParam('pbranch_id', $docInfo['branch_id']);
                $cmm->addParam('pdoc_stage_id', $cstage);
                $dtnextRole = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dtnextRole->Rows()) >= 1) {
                    $roleinfo->role_id = -1;
                    $roleinfo->role_name = 'Yet to be resolved';
                    $roleinfo->next_role_id = '';
                    $roleinfo->next_role_name = '';
                    foreach ($dtnextRole->Rows() as $nr) {
                        if (strlen($roleinfo->next_role_id) > 0) {
                            $roleinfo->next_role_id .= ', ';
                            $roleinfo->next_role_name .= ', ';
                        }
                        $roleinfo->next_role_id .= $nr['next_role_id'];
                        $roleinfo->next_role_name .= $nr['next_role_name'];
                        $roleinfo->next_stage_id = $cstage;
                        $roleinfo->next_stage_desc = $cstage;
                    }
                    $roleinfo->resultStatus = 'OK';
                    return $roleinfo;
                } else {
                    // Lets verify if it is last stage
                    if ($nextStage['id'] == end($docStageInfo)['id']) {
                        // Allow for post
                        $roleinfo->next_stage_id = $nextStage['id'];
                        $roleinfo->next_stage_desc = $nextStage['desc'];
                        $roleinfo->next_role_id = 'POST';
                    }
                    $roleinfo->resultStatus = 'OK';
                    return $roleinfo;
                }
            } else {
                // Current stage is last stage. 
                $roleinfo->resultStatus = 'NA';
                $roleinfo->resultMsg = 'Current stage is last stage.';
                return $roleinfo;
            }
        }
    }

    private function getStages($editform) {
        $stages = [];
        $posn = strpos($editform, 'CoreWebApp/');
        $path = substr($editform, $posn + strlen('CoreWebApp/'));
        $xeditview = simplexml_load_file(\yii::getAlias('@app/' . $path));
        if (isset($xeditview->formView)) {
            $bo = (string) $xeditview->formView->attributes()->bindingBO;
            $last = strrpos($path, '/');
            $next_to_last = strrpos($path, '/', $last - strlen($path) - 1);
            $temp = substr($path, 0, $next_to_last);
            $bopath = $temp . '/' . $bo . '.xml';
            $xbo = simplexml_load_file(\yii::getAlias('@app/' . $bopath));
            if (isset($xbo->businessObject->docStageInfo)) {
                $stage_count = count($xbo->businessObject->docStageInfo->children());
                for ($cnt = 0; $cnt < ($stage_count - 1); $cnt++) {
                    $stg = array(
                        'step' => ($cnt + 1),
                        'id' => (string) $xbo->businessObject->docStageInfo->children()[$cnt]->attributes()->id,
                        'desc' => (string) $xbo->businessObject->docStageInfo->children()[$cnt]->attributes()->desc,
                    );
                    $stages[] = $stg;
                }
            }
        }
        return $stages;
    }

}
