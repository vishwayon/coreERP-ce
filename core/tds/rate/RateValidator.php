<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\rate;


/**
 * Description of RateValidator
 *
 * @author Shrishail
 */
class RateValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateRateEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    private function validateBusinessRules() {        
        
        // Validate duplicate Rate
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select section_id, person_type_id, effective_from from tds.rate '
                . 'where section_id=:psection_id and person_type_id=:pperson_type_id '
                . 'and effective_from=:peffective_from and rate_id!=:prate_id');
        $cmm->addParam('psection_id', $this->bo->section_id);
        $cmm->addParam('pperson_type_id', $this->bo->person_type_id);
        $cmm->addParam('peffective_from', $this->bo->effective_from);
        $cmm->addParam('prate_id', $this->bo->rate_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Rate already exists. Duplicate Rate not allowed.');
        }
    }
}
