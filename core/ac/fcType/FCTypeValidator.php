<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\fcType;


/**
 * Description of AssetBookValidator
 *
 * @author girish
 */
class FCTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateFCTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {
        
        // Validate duplicate asset book
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select fc_type from ac.fc_type where fc_type ilike :pfc_type and fc_type_id!=:pfc_type_id');
        $cmm->addParam('pfc_type', $this->bo->fc_type);
        $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Foreign Currency already exists. Duplicate Foreign Currency not allowed.');
        }
    }
}
