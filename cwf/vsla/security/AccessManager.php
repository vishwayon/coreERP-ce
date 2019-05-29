<?php

namespace app\cwf\vsla\security;

class AccessManager {

    private static $als = array();

    public static function verifyAccess($bo_id) {
        if (!array_key_exists($bo_id, self::$als)) {
            $al = self::getBoAccessLevel($bo_id);
            self::$als[$bo_id] = $al;
        } else {
            $al = self::$als[$bo_id];
        }
        return $al;
    }

    public static function verifyUnpostDelete($bo_id) {
        $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        $uact = new UserUnpostDel();
        if ($userinfo->isAdmin() || $userinfo->isOwner()) {
            // masters only
            $uact->allow_delete = true;
        } else {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $sql = 'Select a.allow_delete, a.allow_unpost, a.allow_audit_trail 
                    From sys.user_access_level a 
                    Inner Join sys.menu b On a.menu_id=b.menu_id
                    Where a.user_id=:puser_id And a.branch_id=:pbranch_id And b.bo_id=:pbo_id::uuid';
            $cmm->setCommandText($sql);
            $cmm->addParam('puser_id', $userinfo->getUser_ID());
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pbo_id', md5($bo_id));
            $acdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($acdt->Rows()) > 0) {
                // Ideally there would be only one row. However, if a BO is associated to multiple menu items,
                // then the one with the highest level is chosen
                foreach ($acdt->Rows() as $act) {
                    if ($act['allow_delete']) {
                        $uact->allow_delete = true;
                    }
                    if ($act['allow_unpost']) {
                        $uact->allow_unpost = true;
                    }
                    if ($act['allow_audit_trail']) {
                        $uact->allow_audit_trail = true;
                    }
                }
            }
        }
        return $uact;
    }

    public static function applySecurity($docBo) {
        $userAccess = self::verifyAccess($docBo['__bo']);
        $docBo->resetSecurity();

        $doc_status = $docBo->status;

        switch ($doc_status) {
            case \app\cwf\vsla\xmlbo\DocBo::STATUS_NEW:
                self::onStatusNew($docBo, $userAccess);
                break;
            case \app\cwf\vsla\xmlbo\DocBo::STATUS_CREATED:
                self::onStatusCreated($docBo, $userAccess);
                break;
            case \app\cwf\vsla\xmlbo\DocBo::STATUS_IN_WORKFLOW:
                self::onStatusWorkflow($docBo, $userAccess);
                break;
            case \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED:
                self::onStatusPosted($docBo, $userAccess);
                break;
            default:
                // Do Nothing
                break;
        }

        $uact = self::verifyUnpostDelete($docBo['__bo']);
        if ($doc_status == \app\cwf\vsla\xmlbo\DocBo::STATUS_CREATED || $doc_status == \app\cwf\vsla\xmlbo\DocBo::STATUS_IN_WORKFLOW) {
            $docBo->setAllowDelete($uact->allow_delete);
        } else if ($doc_status == \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED) {
            $docBo->setAllowUnpost($uact->allow_unpost);
        }
        $docBo->setAllowAuditTrail($uact->allow_audit_trail);
    }

    private static function onStatusNew(\app\cwf\vsla\xmlbo\DocBo $docBo, $userAccess) {
        if ($userAccess >= \app\cwf\vsla\security\AccessLevels::DATAENTRY) {
            if (count($docBo->getDocStageInfo()) == 0) {
                // singleStage document
                $docBo->setAllowSave(true);
            } else {
                // multiStage Document
                $sql = "Select a.role_id
                        From sys.role_access_level a
                        Inner Join sys.user_branch_role b On a.role_id=b.role_id
                        Where a.menu_id in (Select menu_id from sys.menu
                                            Where bo_id=md5(:pbo_id)::uuid)
                            And a.en_access_level >= 2 
                            And b.branch_id = :pbranch_id
                            And a.doc_stages @> Array[:pdoc_stage_id]::Varchar[]
                            And b.user_id = :puser_id;";
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText($sql);
                $cmm->addParam('pbo_id', $docBo['__bo']);
                $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                $cmm->addParam('pdoc_stage_id', $docBo->doc_stage_id);
                $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
                $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dt->Rows()) > 0) {
                    $docBo->setAllowSave(true);
                }
            }
        }
    }

    private static function onStatusCreated(\app\cwf\vsla\xmlbo\DocBo $docBo, $userAccess) {
        if ($userAccess >= \app\cwf\vsla\security\AccessLevels::DATAENTRY) { //&& self::isCurrentUserCreater($docBo)) {
            $docBo->setAllowSave(true);
            $docBo->setAllowArchive(true);
            if ($userAccess == \app\cwf\vsla\security\AccessLevels::AUTHORIZE && count($docBo->getDocStageInfo()) == 0) {
                $docBo->setAllowPost(true, ' Post');
            } else {
                $roleInfo = self::getNextRoleInfo($docBo);
                if ($roleInfo->resultStatus == 'OK' && $roleInfo->next_role_id != -1) {
                    $docBo->setAllowSend(true);
                    if ($roleInfo->next_stage_desc == '') {
                        $roleInfo->next_stage_desc = ' Send for Post';
                    }
                    $docBo->setRoleInfo($roleInfo);
                }
            }
        }
    }

    private static function isCurrentUserCreater(\app\cwf\vsla\xmlbo\DocBo $docBo) {
        $userInfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select doc_id, user_id_created ' .
                'From sys.doc_created ' .
                'Where doc_id = :pdoc_id';
        $cmm->setCommandText($sql);
        $cmm->addParam('pdoc_id', $docBo['__doc_id']);
        $dtcr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtcr->Rows()) == 1) {
            if ($userInfo->getUser_ID() == $dtcr->Rows()[0]['user_id_created']) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    private static function onStatusWorkflow($docBo, $userAccess) {
        if ($userAccess >= \app\cwf\vsla\security\AccessLevels::DATAENTRY) {
            $docBo->setAllowSave(true);
            $docBo->setAllowArchive(true);
            if ($userAccess == \app\cwf\vsla\security\AccessLevels::AUTHORIZE && count($docBo->getDocStageInfo()) == 0) {
                $docBo->setAllowPost(true, ' Post');
                $docBo->setAllowReject(true, ' Reject');
            } else {
                $roleInfo = self::getNextRoleInfo($docBo);
                if ($roleInfo->resultStatus == 'OK' && ($roleInfo->next_role_id != '')) {
                    if ($roleInfo->next_role_id == 'POST') {
                        $docBo->setAllowPost(true);
                        $docBo->setAllowReject(true);
                        $docBo->setRoleInfo($roleInfo);
                    } else {
                        $docBo->setAllowApprove(true);
                        $docBo->setAllowReject(true);
                        if ($roleInfo->next_stage_desc == '') {
                            $roleInfo->next_stage_desc = ' Send for Post';
                        }
                        $docBo->setRoleInfo($roleInfo);
                    }
                }
            }
        }
        self::restrictByWf($docBo);
    }

    private static function onStatusPosted($docBo, $userAccess) {
        // User cannot perform any actions. 
        // Therefore, do not do anything
    }

    /**
     * This method is used to restrict usage based on Workflow
     * This is applied only if document is in workflow
     * @param \app\cwf\vsla\xmlbo\DocBo $docBo
     */
    private static function restrictByWf(\app\cwf\vsla\xmlbo\DocBo $docBo) {
        if (count($docBo->getDocStageInfo()) == 0) {
            // Single Stage document
            $userInfo = SessionManager::getInstance()->getUserInfo();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $sql = 'Select doc_action, user_id_to ' .
                    'From sys.doc_wf ' .
                    'Where doc_id = :pdoc_id';
            $cmm->setCommandText($sql);
            $cmm->addParam('pdoc_id', $docBo['__doc_id']);
            $dtwf = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtwf->Rows()) == 1) {
                if ($userInfo->getUser_ID() != $dtwf->Rows()[0]['user_id_to']) {
                    $docBo->setAllowApprove(false);
                    $docBo->setAllowReject(false);
                    $docBo->setAllowSave(false);
                    $docBo->setAllowPost(false);
                } else if ($userInfo->getUser_ID() == $dtwf->Rows()[0]['user_id_to'] &&
                        $dtwf->Rows()[0]['doc_action'] == \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                    // This is an unposted document opened by the user who unposted it.
                    // Therefore allow choice of assign
                    $docBo->setAllowAssign(true);
                }
            }
        } else {
            // multi stage document
            $sql = "Select a.role_id
                    From sys.role_access_level a
                    Inner Join sys.user_branch_role b On a.role_id=b.role_id
                    Where a.menu_id in (Select menu_id from sys.menu
                                        Where bo_id=md5(:pbo_id)::uuid)
                        And a.en_access_level >= 2 
                        And b.branch_id = :pbranch_id
                        And a.doc_stages @> Array[:pdoc_stage_id]::Varchar[]
                        And b.user_id = :puser_id;";
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($sql);
            $cmm->addParam('pbo_id', $docBo['__bo']);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pdoc_stage_id', $docBo->doc_stage_id);
            $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) == 0) {
                $docBo->setAllowApprove(false);
                $docBo->setAllowReject(false);
                $docBo->setAllowSave(false);
                $docBo->setAllowPost(false);
            } else {
                // Restrict access if the user sent to is not the same as user logged in
                $userInfo = SessionManager::getInstance()->getUserInfo();
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $sql = 'Select doc_action, user_id_to ' .
                        'From sys.doc_wf ' .
                        'Where doc_id = :pdoc_id';
                $cmm->setCommandText($sql);
                $cmm->addParam('pdoc_id', $docBo['__doc_id']);
                $dtwf = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dtwf->Rows()) == 1) {
                    if ($userInfo->getUser_ID() != $dtwf->Rows()[0]['user_id_to']) {
                        $docBo->setAllowApprove(false);
                        $docBo->setAllowReject(false);
                        $docBo->setAllowSave(false);
                        $docBo->setAllowPost(false);
                    } else if ($userInfo->getUser_ID() == $dtwf->Rows()[0]['user_id_to'] &&
                            $dtwf->Rows()[0]['doc_action'] == \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                        // This is an unposted document opened by the user who unposted it.
                        // Therefore allow choice of assign
                        $docBo->setAllowAssign(true);
                    }
                }
            }
        }
    }

    private static function getNextRoleInfo(\app\cwf\vsla\xmlbo\DocBo $docBo): RoleInfo {
        $userInfo = SessionManager::getInstance()->getUserInfo();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $roleinfo = new RoleInfo();
        if (count($docBo->getDocStageInfo()) == 0) {
            // docBo is singleStage (use role hierarchy)
            // $sql = 'Select a.role_id, a.role_name, a.parent_role_id, b.role_name as parent_role_name '.
            //    'From sys.role a '.
            //    'Left Join sys.role b On a.parent_role_id=b.role_id '.
            //    'Where a.role_id = (Select z.role_id from sys.role_to_user z Where z.user_id=:puser_id)';
            // $cmm->addParam('puser_id', $userInfo->getUser_ID());
            // $roleinfo->role_id = $roleData->Rows()[0]['role_id'];
            // $roleinfo->role_name = $roleData->Rows()[0]['role_name'];
            // docBo is singleStage Alternate Logic: (See if any role has posting rights)
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
            $cmm->addParam('pbranch_id', SessionManager::getInstance()->getSessionVariable('branch_id'));
            $cmm->addParam('pbo_id', $docBo['__bo']);
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
            // docBo is multiStage
            // next role could be an array of roles having access
            $nextStage = '';
            $docStageInfo = $docBo->getDocStageInfo();
            $cstage = $docBo->doc_stage_id;
            reset($docStageInfo);
            while (true) {
                $stage = current($docStageInfo);
                if ($cstage == $stage['id']) {
                    $nextStage = next($docStageInfo);
                    break;
                } else {
                    $tmp = next($docStageInfo);
                    if (!$tmp) {
                        break;
                    }
                }
            }
            if ($nextStage) {
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
                $cmm->addParam('pbo_id', $docBo['__bo']);
                $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                $cmm->addParam('pdoc_stage_id', $nextStage['id']);
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
                        $roleinfo->next_stage_id = $nextStage['id'];
                        $roleinfo->next_stage_desc = $nextStage['desc'];
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

    /*
     * Gets Actual Access level for requested bo id
     * This is based on the user role and the highest access given to a user role
     */

    private static function getBoAccessLevel($bo_id) {
        /** @var \app\cwf\vsla\security\AccessLevels */
        $access_level = \app\cwf\vsla\security\AccessLevels::NOACCESS;
        $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($userinfo->isAdmin() || $userinfo->isOwner() || $bo_id == "UserProfile" || $bo_id == "Feedback") {
            $access_level = \app\cwf\vsla\security\AccessLevels::AUTHORIZE;
        } else {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from sys.get_menu_access_for_userv2(:puser_id, :pbranch_id, :pbo_id::uuid) as en_access_level');
            $cmm->addParam('puser_id', $userinfo->getUser_ID());
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pbo_id', md5($bo_id));
            $acdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($acdt->Rows()) > 0) {
                $access_level = \app\cwf\vsla\security\AccessLevels::getLevel((int) $acdt->Rows()[0]['en_access_level']);
            }
        }
        return $access_level;
    }

    public static function allowFirstStage($bo_id): bool {
        // todo:Yet to implement
        return true;
    }

    public static function log_doc_view($doc_id, $bo_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'INSERT INTO sys.doc_view_log(bo_id, doc_id, user_id) values '
                . '(:pbo_id, :pdoc_id, :puser_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public static function log_doc_print($bo_id, $doc_id, $doc_status) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'INSERT INTO sys.doc_print_log(bo_id, doc_id, user_id, doc_status) values '
                . '(:pbo_id, :pdoc_id, :puser_id, :pdoc_status)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pdoc_status', $doc_status);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public static function check_print_access($bo_id, $doc_id, $doc_status) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.fn_get_allow_print(:pdoc_id , :pdoc_status , :pbo_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('pdoc_status', $doc_status);
        $cmm->addParam('pbo_id', $bo_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = FALSE;
        if (count($res->Rows()) > 0) {
            $allow = (bool) $res->Rows()[0]['fn_get_allow_print'];
        }
        return $allow;
    }

    public static function check_pending_print_request($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.doc_print_request '
                . 'where doc_id = :pdoc_id and requested_by_user_id = :puser_id and allowed_by_user_id is not NULL and printed_on is NULL and closed = false';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $reqres = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($reqres->Rows()) > 0) {
            return TRUE;
        }
        return FALSE;
    }

    public static function check_export_access($bo_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.fn_get_allow_export(:pbo_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = FALSE;
        if (count($res->Rows()) > 0) {
            $allow = (bool) $res->Rows()[0]['fn_get_allow_export'];
        }
        return $allow;
    }

    public static function check_export_options($bo_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select export_allow, array_to_json(export_types) as et from sys.doc_print_control where bo_id = :pbo_id';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = NULL;
        if (count($res->Rows()) > 0) {
            $allow = [];
            if ((bool) $res->Rows()[0]['export_allow']) {
                $allow = json_decode($res->Rows()[0]['et']);
            }
        }
        return $allow;
    }

    public static function check_report_mail_access($bo_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.fn_get_allow_report_mail(:pbo_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = FALSE;
        if (count($res->Rows()) > 0) {
            $allow = (bool) $res->Rows()[0]['fn_get_allow_report_mail'];
        }
        return $allow;
    }

    public static function check_audit_trail_access($bo_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.fn_get_allow_audit_trail(:pbo_id, :puser_id, :pbranch_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pbo_id', $bo_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getSessionVariable('user_id'));
        $cmm->addParam('pbranch_id', SessionManager::getInstance()->getSessionVariable('branch_id'));
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = FALSE;
        if (count($res->Rows()) > 0) {
            $allow = (bool) $res->Rows()[0]['fn_get_allow_audit_trail'];
        }
        return $allow;
    }

    public static function check_confirm_post() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select * from sys.settings where key='confirm_post'";
        $cmm->setCommandText($cmmtext);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $allow = FALSE;
        if (count($res->Rows()) > 0) {
            $allow = $res->Rows()[0]['value'];
        }
        return $allow;
    }

}

class UserUnpostDel {

    public $allow_delete = false;
    public $allow_unpost = false;
    public $allow_audit_trail = false;

}
