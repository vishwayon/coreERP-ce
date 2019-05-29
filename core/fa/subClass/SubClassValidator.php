<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\subClass;
use YaLinqo\Enumerable;

/**
 * Description of SubClassValidator
 *
 * @author valli
 */
class SubClassValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSubClassEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate asset location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select sub_class_desc from fa.sub_class where sub_class_desc ilike :psub_class_desc and sub_class_id!=:psub_class_id');
        $cmm->addParam('psub_class_desc', $this->bo->sub_class_desc);
        $cmm->addParam('psub_class_id', $this->bo->sub_class_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Sub class already exists. Duplicate sub class not allowed.');
        }       
        
    }
}
