<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\restrictIP;


/**
 * Description of RestrictIPValidator
 *
 * @author Shrishail
 */
class RestrictIPValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateRestrictIPEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() { 
        
        // Validate if ip is in correct format. Invalid ip will throw exception
        try {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select :pip::inet;");
            $cmm->addParam('pip', $this->bo->ip);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        } catch (\Exception $ex) {
            $this->bo->addBRule('Invalid IP. Please enter a valid value.');
        }
        
        // Validate duplicate Restrict IP
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select restrict_ip_id, domain, ip from sys.restrict_ip Where domain ilike :pdomain And ip=:pip And restrict_ip_id!=:prestrict_ip_id');
        $cmm->addParam('pdomain', $this->bo->domain);
        $cmm->addParam('pip', $this->bo->ip);
        $cmm->addParam('prestrict_ip_id', $this->bo->restrict_ip_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('IP already exists. Duplicate IP not allowed.');
        }
    }
}
