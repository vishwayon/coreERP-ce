<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xmlbo;

/**
 * This class represents the document
 * 
 * @author girish
 */
class DocBo extends BoBase {
    const STATUS_NEW=0;
    const STATUS_CREATED=1;
    const STATUS_IN_WORKFLOW=3;
    const STATUS_POSTED=5;
    const STATUS_UNARCHIVED=8;
    const STATUS_ARCHIVED=9;
    
    //put your code here
    private $docSecurity = [];
    private $userPref = [];
    
    private $docWfOption = [
        'user_id_to' => -1,
        'doc_sender_comment' => '',
    ];
    
    private $docStageInfo = [];
    private $docStageCurrent = 'new';
    
    
    public function __construct() {
        $this->resetSecurity();
    }
    
    public function getDocSecurity() {
        return $this->docSecurity;
    }
    
    public function resetSecurity() {
        $this->docSecurity = [
            //Document Actions
            'allowSave' => false,
            'allowSend' => false,
            'allowApprove' => false,
            'allowReject' => false,
            'allowPost' => false, 
            'allowUnpost' => false,
            'allowDelete' => false,
            'allowArchive' => false,
            'allowAssign' => false,
            'allowAuditTrail' => false,
            // Role related properties
            'role_id'=> -1,
            'role_name'=> '',
            'next_role_id'=> -1,
            'next_role_name'=> '',
            'next_user_id' => -1,
            'next_stage_id' => '',
            'next_stage_desc' => '',
            'regress_stage_id' => '',
            'regress_stage_desc' => ''
        ];
    }
    
    public function setAllowSave($allow) {
        $this->docSecurity['allowSave'] = $allow;
    }
    
    public function getAllowSave() {
        return $this->docSecurity['allowSave'];
    }
    
    public function setAllowSend($allow, $desc='') {
        $this->docSecurity['allowSend'] = $allow;
        if($desc != '') {
            $this->docSecurity['next_stage_desc'] = $desc;
        }
    }
    
    public function getAllowSend() {
        return $this->docSecurity['allowSend'];
    }
    
    public function setAllowApprove($allow) {
        $this->docSecurity['allowApprove'] = $allow;
    }
    
    public function getAllowApprove() {
        return $this->docSecurity['allowApprove'];
    }
    
    public function setAllowReject($allow, $desc = '') {
        $this->docSecurity['allowReject'] = $allow;
        if($desc != '') {
            $this->docSecurity['regress_stage_desc'] = $desc;
        }
    }
    
    public function getAllowReject() {
        return $this->docSecurity['allowReject'];
    }
    
    public function setAllowPost($allow, $desc = '') {
        $this->docSecurity['allowPost'] = $allow;
        if($desc != '') {
            $this->docSecurity['next_stage_desc'] = $desc;
        }
    }
    
    public function getAllowPost() {
        return $this->docSecurity['allowPost'];
    }
    
    public function setAllowAssign($allow) {
        $this->docSecurity['allowAssign'] = $allow;
    }
    
    public function getAllowAssign() {
        return $this->docSecurity['allowAssign'];
    }
    
    public function setAllowUnpost($allow) {
        $this->docSecurity['allowUnpost'] = $allow;
    }
    
    public function getAllowUnpost() {
        return $this->docSecurity['allowUnpost'];
    }

    public function setAllowAuditTrail($allow) {
        $this->docSecurity['allowAuditTrail'] = $allow;
    }

    public function getAllowAuditTrail() {
        return $this->docSecurity['allowAuditTrail'];
    }

    public function setAllowDelete($allow) {
        $this->docSecurity['allowDelete'] = $allow;
    }
    
    public function getAllowDelete() {
        return $this->docSecurity['allowDelete'];
    }
    
    public function setAllowArchive($allow) {
        $this->docSecurity['allowArchive'] = $allow;
    }
    
    public function getAllowArchive() {
        return $this->docSecurity['allowArchive'];
    }
    
    public function setRoleInfo(\app\cwf\vsla\security\RoleInfo $roleInfo) {
        $this->docSecurity['role_id'] = $roleInfo->role_id;
        $this->docSecurity['role_name'] = $roleInfo->role_id;
        $this->docSecurity['next_role_id'] = $roleInfo->next_role_id;
        $this->docSecurity['next_role_name'] = $roleInfo->next_role_name;
        $this->docSecurity['next_stage_id'] = $roleInfo->next_stage_id;
        $this->docSecurity['next_stage_desc'] = ' '.$roleInfo->next_stage_desc;
        $this->docSecurity['regress_stage_id'] = $roleInfo->regress_stage_id;
        $this->docSecurity['regress_stage_desc'] = ' '.$roleInfo->regress_stage_desc;        
    }
    
    public function setNextUserID(int $next_user_id) {
        $this->docSecurity['next_user_id'] = $next_user_id; 
    }
    
    public function setWfOption($user_id_to, $doc_sender_comment) {
        $this->docWfOption['user_id_to'] = $user_id_to;
        $this->docWfOption['doc_sender_comment'] = $doc_sender_comment;
    }
    
    public function getWfOption() {
        return $this->docWfOption;
    }
    
    public function setDocStageInfo(array $stageinfo) {
        $this->docStageInfo = $stageinfo;
    }
    
    public function &getDocStageInfo() {
        return $this->docStageInfo;
    }
    
    public function getDocDate(){
        return $this->doc_date;
    }
    
    public function getUserPref() {
        return $this->userPref;
    }

    public function setUserPref($user_pref) {
        $this->userPref = $user_pref;
    }

}
