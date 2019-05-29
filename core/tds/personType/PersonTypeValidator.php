<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\personType;


/**
 * Description of PersonTypeValidator
 *
 * @author Shrishail
 */
class PersonTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validatePersonTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    private function validateBusinessRules() {        
        
        // Validate duplicate TDS Person Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select person_type_desc from tds.person_type where person_type_desc ilike :pperson_type_desc and person_type_id!=:pperson_type_id');
        $cmm->addParam('pperson_type_desc', $this->bo->person_type_desc);
        $cmm->addParam('pperson_type_id', $this->bo->person_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Person Type already exists. Duplicate Person Type not allowed.');
        }
    }
}
