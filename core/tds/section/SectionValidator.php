<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\section;


/**
 * Description of SectionValidator
 *
 * @author Shrishail
 */
class SectionValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSectionEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    private function validateBusinessRules() {        
        
        // Validate duplicate Section
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select section from tds.section where section ilike :psection and section_id!=:psection_id');
        $cmm->addParam('psection', $this->bo->section);
        $cmm->addParam('psection_id', $this->bo->section_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Section already exists. Duplicate Section not allowed.');
        }
    }
}
