<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\designation;
/**
 * Description of DesignationValidator
 *
 * @author Valli
 */

class DesignationValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateDesignationEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate duplicate Designation
  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select designation from hr.designation where designation ilike :pdesignation and designation_id!=:pdesignation_id');
        $cmm->addParam('pdesignation', $this->bo->designation);
        $cmm->addParam('pdesignation_id', $this->bo->designation_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Designation already exists. Duplicate Designation not allowed.');
        }
    }
}