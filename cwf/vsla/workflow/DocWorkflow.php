<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\workflow;

/**
 * Class used to organise entries for DocWorkflow
 *
 * @author girish
 */
class DocWorkflow {

    const WF_SEND = 'S';
    const WF_APPROVE = 'A';
    const WF_REJECT = 'R';
    const WF_POST = 'P';
    const WF_UNPOST = 'U';
    const WF_ASSIGN = 'I';
    const WF_UNARCHIVE = 'O';
    const WF_ARCHIVE = 'C';
    
    private $wfValids = [self::WF_APPROVE, self::WF_POST, self::WF_REJECT, self::WF_SEND, self::WF_UNPOST, self::WF_ASSIGN];
    private $wfConsts = [self::WF_APPROVE=>'Approved', self::WF_POST=>'Posted', self::WF_REJECT=>'Rejected', 
                            self::WF_SEND=>'Sent', self::WF_UNPOST=>'Unposted', self::WF_ASSIGN=>'Assigned'];
    
    /**
     * @param WfOption $wfOption 
     * Set the option to move the document within workflow
     */
    public function moveDoc(WfOption $wfOption, \PDO $cn) {
        $wfvalresult = $this->validateOption($wfOption);
        if($wfvalresult->status!='OK') {
            return $wfvalresult;
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_doc_wf_move(:pdoc_id, :pbranch_id, :pfinyear, :pdoc_date, :pbo_id, :pedit_view, :pdoc_name, 
            :pdoc_sender_comment, :puser_id_from, :pdoc_sent_on, :pdoc_action, :puser_id_to, :pdoc_stage_id_from, :pdoc_stage_id)');
        $acted_on = date('Y-m-d H:i:s', time());
        $cmm->addParam('pdoc_id', $wfOption->doc_id);
        $cmm->addParam('pbranch_id', $wfOption->branch_id);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        $cmm->addParam('pdoc_date', $wfOption->doc_date);
        $cmm->addParam('pbo_id', $wfOption->bo_id);
        $cmm->addParam('pedit_view', $wfOption->edit_view);
        $cmm->addParam('pdoc_name', $wfOption->doc_name);
        if($wfOption->doc_sender_comment == null || $wfOption->doc_sender_comment == '') {
            $cmm->addParam('pdoc_sender_comment', 'No comments');
        } else {
            $cmm->addParam('pdoc_sender_comment', $wfOption->doc_sender_comment);
        }
        $cmm->addParam('puser_id_from', $wfOption->user_id_from);
        $cmm->addParam('pdoc_sent_on', $acted_on);
        $cmm->addParam('pdoc_action', $wfOption->doc_action);
        $cmm->addParam('puser_id_to', $wfOption->user_id_to);
        $cmm->addParam('pdoc_stage_id_from', $wfOption->doc_stage_id_from);
        $cmm->addParam('pdoc_stage_id', isset($wfOption->next_stage_id) ? $wfOption->next_stage_id : '');
        
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        $wfresult = new WfResult();
        if($cmm->returnValue['sp_doc_wf_move']=='OK') {
            $wfresult->status = 'OK';
            if(\yii::$app->has('wfEventListner')) {
                $el = \yii::$app->wfEventListner->docMoved($wfOption);
            }
            return $wfresult;
        } else {
            $wfresult->message = 'Insert/Update Errors in Database Table sys.doc_wf. Failed to move document';
            return $wfresult;
        }
    }
    
    private function validateOption($wfOption) {
        $wfresult = new WfResult();
        // validations
        if(!in_array($wfOption->doc_action, $this->wfValids)) {
            $wfresult->message = 'Invalid/Missing Action. Valid values are S, A, R, P, U, I';
            return $wfresult;
        }
        if($wfOption->doc_action != 'P') {
            // validate for non post actions
            if($wfOption->user_id_to <= 0) {
                $wfresult->message = 'Invalid To User. user_to required.';
                return $wfresult;
            }
        }
        $wfresult->status = 'OK';
        return $wfresult;
    }
    
    public function getMyDocs() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.doc_wf Where user_id_to=:puser_id Order By doc_sent_on;');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    public function getUserDocs($user_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.doc_wf Where user_id_to=:puser_id Order By doc_sent_on;');
        $cmm->addParam('puser_id', $user_id);
        
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    public function getMySentDocs() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.doc_wf Where user_id_from=:puser_id Order By doc_sent_on;');
        $cmm->addParam('puser_id', $user_id);
        
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    public function getDocWfHistory($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('  With doc_wf
                                As
                                (
                                    Select doc_id, bo_id, doc_name, doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, last_updated 
                                    From sys.doc_wf  
                                    Where doc_id=:pdoc_id
                                    Union All
                                    Select doc_id, bo_id, doc_name, doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, last_updated 
                                    From sys.doc_wf_history 
                                    Where doc_id=:pdoc_id
                                )
                                Select a.doc_id, a.bo_id, a.doc_name, a.doc_sender_comment, a.user_id_from, b.full_user_name, a.doc_sent_on, a.doc_action, 
                                    a.user_id_to, c.full_user_name, a.last_updated
                                From doc_wf a
                                Inner Join sys.user b On a.user_id_from=b.user_id
                                Left Join sys.user c On a.user_id_to=c.user_id
                                Order by a.doc_sent_on Desc;');
        $cmm->addParam('puser_id', $user_id);
        
        $dtresult = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach($dtresult->Rows() as &$row) {
            $row['doc_action_desc'] = $wfConsts[$row['doc_action']];
        }
        return $dtresult;
    }
    
    public static function getRejectTo($doc_id, $user_id) {
        // First determine if the document was received by what user action
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select doc_action From sys.doc_wf Where doc_id=:pdoc_id and user_id_to=:puser_id';
        $cmm->setCommandText($sql);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('puser_id', $user_id);
        $dtact = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtact->Rows())==1) {
            if($dtact->Rows()[0]['doc_action']==DocWorkflow::WF_APPROVE || $dtact->Rows()[0]['doc_action']==DocWorkflow::WF_SEND) {
                // Document was received as approved from previous user. 
                // Therefore, reject it to previous user
                $cmmrej = new \app\cwf\vsla\data\SqlCommand();
                $sqlrej = 'Select a.user_id_from, b.full_user_name as user_id_from_name, b.email as user_id_from_email, a.doc_stage_id_from From sys.doc_wf a
                        Inner Join sys.user b On a.user_id_from = b.user_id
                        Where a.doc_id=:pdoc_id And a.user_id_to=:puser_id And b.is_active';
                $cmmrej->setCommandText($sqlrej);
                $cmmrej->addParam('pdoc_id', $doc_id);
                $cmmrej->addParam('puser_id', $user_id);
                $dtrej = \app\cwf\vsla\data\DataConnect::getData($cmmrej);
                if (count($dtrej->Rows())==1) {
                    return $dtrej->Rows()[0];
                } else {
                    return [];
                }
            } elseif ($dtact->Rows()[0]['doc_action']==DocWorkflow::WF_REJECT || $dtact->Rows()[0]['doc_action']==DocWorkflow::WF_SEND) {
                // Document was recieved because of rejection by previous user. We get this from history
                // Try to find the user who had previously sent it to me
                $cmmrej = new \app\cwf\vsla\data\SqlCommand();
                $sqlrej = 'Select a.user_id_from, b.full_user_name as user_id_from_name, b.email as user_id_from_email, a.doc_stage_id_from From sys.doc_wf_history a
                        Inner Join sys.user b On a.user_id_from = b.user_id
                        Where a.doc_id=:pdoc_id And a.user_id_to=:puser_id And doc_action in (\'S\', \'A\') And b.is_active
                        Order by a.last_updated desc Limit 1';
                $cmmrej->setCommandText($sqlrej);
                $cmmrej->addParam('pdoc_id', $doc_id);
                $cmmrej->addParam('puser_id', $user_id);
                $dtrej = \app\cwf\vsla\data\DataConnect::getData($cmmrej);
                if (count($dtrej->Rows())==1) {
                    return $dtrej->Rows()[0];
                } else {
                    return [];
                }
            }
        }
        return [];
    }
    
    public static function getWfHistory($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'With doc_wf
                As
                (
                    Select b.full_user_name, a.doc_action, a.doc_sender_comment, 
                        to_char(a.doc_sent_on, \'YYYY-MM-DD"T"HH24:MI:SS\') doc_sent_on
                    From sys.doc_wf a 
                    Inner Join sys.user b On a.user_id_from = b.user_id
                    Where a.doc_id = :pdoc_id            
                    Union All
                    Select b.full_user_name, a.doc_action, a.doc_sender_comment, 
                        to_char(a.doc_sent_on, \'YYYY-MM-DD"T"HH24:MI:SS\') doc_sent_on
                    From sys.doc_wf_history a 
                    Inner Join sys.user b On a.user_id_from = b.user_id
                    Where a.doc_id = :pdoc_id
                )
                Select full_user_name, doc_action, doc_sender_comment, doc_sent_on
                From doc_wf
                Order By doc_sent_on Desc';
        $cmm->setCommandText($sql);
        $cmm->addParam('pdoc_id', $doc_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $dt->addColumn('doc_action_desc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        foreach($dt->Rows() as &$row) {
            switch ($row['doc_action']) {
                case self::WF_APPROVE:
                    $row['doc_action_desc'] = 'Approved';
                    break;
                case self::WF_ASSIGN:
                    $row['doc_action_desc'] = 'Assigned';
                    break;
                case self::WF_POST:
                    $row['doc_action_desc'] = 'Posted';
                    break;
                case self::WF_REJECT:
                    $row['doc_action_desc'] = 'Rejected';
                    break;
                case self::WF_SEND:
                    $row['doc_action_desc'] = 'Sent';
                    break;
                case self::WF_UNPOST:
                    $row['doc_action_desc'] = 'Unposted';
                    break;
                case self::WF_ARCHIVE:
                    $row['doc_action_desc'] = 'Archived';
                    break;
                case self::WF_UNARCHIVE:
                    $row['doc_action_desc'] = 'Unarchived';
                    break;
            }
        }
        return $dt;
    }
    
    public static function getArchiveStatus($doc_id){
        $isArchived = FALSE;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select doc_action, doc_sender_comment, to_char(doc_sent_on, \'YYYY-MM-DD"T"HH24:MI:SS\') doc_sent_on
                    From sys.doc_wf_history
                    Where doc_id = :pdoc_id
                    Order By last_updated Desc limit 1';
        $cmm->setCommandText($sql);
        $cmm->addParam('pdoc_id', $doc_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            if($dt->Rows()[0]['doc_action']=='C'){
                $isArchived = TRUE;
            }
        }
        return $isArchived;
    }
    
}
