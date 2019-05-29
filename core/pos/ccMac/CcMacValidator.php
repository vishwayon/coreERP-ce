<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\pos\ccMac;

/**
 * Description of TerminalValidator
 *
 * @author Girish Shenoy
 */
class CcMacValidator extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    
    public function validateCcMacEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate CcMac Code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select cc_mac_code from pos.cc_mac where cc_mac_code ilike :pcc_mac_code '
                             . 'and cc_mac_id!=:pcc_mac_id and company_id=:pcompany_id');
        $cmm->addParam('pcc_mac_code', $this->bo->cc_mac_code);
        $cmm->addParam('pcc_mac_id', $this->bo->cc_mac_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $tc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($tc->Rows())>0) {
            $this->bo->addBRule('Credit Card Machine already exists. Duplicate card machine(s) not allowed.');
        }
    }
}
