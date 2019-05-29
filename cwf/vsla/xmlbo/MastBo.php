<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xmlbo;

/**
 * Business Object that implements Master
 *
 * @author girish
 */
class MastBo extends BoBase {

    //put your code here
    private $docSecurity = [];

    public function __construct() {
        $this->resetSecurity();
    }

    public function resetSecurity() {
        $this->docSecurity = [
            //Document Actions
            'allowSave' => false,
            'allowDelete' => false,
            'allowAuditTrail' => false
        ];
    }

    public function getDocSecurity() {
        return $this->docSecurity;
    }

    public function setAllowSave($allow) {
        $this->docSecurity['allowSave'] = $allow;
    }

    public function getAllowSave() {
        return $this->docSecurity['allowSave'];
    }

    public function setAllowDelete($allow) {
        $this->docSecurity['allowDelete'] = $allow;
    }

    public function getAllowDelete() {
        return $this->docSecurity['allowDelete'];
    }

    public function setAllowAuditTrail($allow) {
        $this->docSecurity['allowAuditTrail'] = $allow;
    }

    public function getAllowAuditTrail() {
        return $this->docSecurity['allowAuditTrail'];
    }

}
