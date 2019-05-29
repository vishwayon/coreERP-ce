<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\deductorInfo;


/**
 * Description of DeductorInfoValidator
 *
 * @author Shrishail
 */
class DeductorInfoValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateDeductorInfoEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    private function validateBusinessRules() {        
         
        if (!ctype_alnum($this->bo->tan)) {
            $this->bo->addBRule('TAN should be entered capital letters ONLY(e.g. MUMM07081E ). Special characters are not allowed.');
         }
         if (!ctype_alnum($this->bo->pan)) {
            $this->bo->addBRule('PAN should be entered capital letters ONLY(e.g. MUMM07081E ).Special characters are not allowed.');
         }
         if (!ctype_alnum($this->bo->tan_registration_no)) {
            $this->bo->addBRule('TAN Registration Number should be entered capital letters ONLY(e.g. T20098776DAC ). Special characters are not allowed.');
         }
         if (!ctype_digit($this->bo->pin_code)) {
            $this->bo->addBRule('Dedutor address PIN code should be entered numeric value Only.');
         }
         if ($this->bo->std_code != '' and !ctype_digit($this->bo->std_code)) {
            $this->bo->addBRule('Dedutor telephone STD code should be entered numeric value Only.');
         }
         if ($this->bo->telephone_no != '' and !ctype_digit($this->bo->telephone_no)) {
            $this->bo->addBRule('Dedutor telephone no.should be entered numeric value Only.');
         }
         if ($this->bo->std_code_alternate != '' and !ctype_digit($this->bo->std_code_alternate)) {
            $this->bo->addBRule('Dedutor alternate telephone STD code should be entered numeric value Only.');
         }
         if ($this->bo->telephone_no_alternate != '' and !ctype_digit($this->bo->telephone_no_alternate)) {
            $this->bo->addBRule('Dedutor alternate telephone no. should be entered numeric value Only.');
         }
         if (!ctype_digit($this->bo->p_pin_code)) {
            $this->bo->addBRule('Person address PIN code should be entered numeric value Only.');
         }
         if ($this->bo->p_mobile_no != '' and !ctype_digit($this->bo->p_mobile_no)) {
            $this->bo->addBRule('Person mobile no. should be entered numeric value Only.');
         }
         if ($this->bo->p_std_code != '' and !ctype_digit($this->bo->p_std_code)) {
            $this->bo->addBRule('Person telephone STD code should be entered numeric value Only.');
         }
         if ($this->bo->p_telephone_no != '' and !ctype_digit($this->bo->p_telephone_no)) {
            $this->bo->addBRule('Person telephone no. should be entered numeric value Only.');
         }
         if ($this->bo->p_std_code_alternate != '' and !ctype_digit($this->bo->p_std_code_alternate)) {
            $this->bo->addBRule('Person alternate telephone STD code should be entered numeric value Only.');
         }
         if ($this->bo->p_telephone_no_alternate != '' and !ctype_digit($this->bo->p_telephone_no_alternate)) {
            $this->bo->addBRule('Person alternate telephone no. should be entered numeric value Only.');
         }
         
         if($this->bo->deductor_type_id == 1 || $this->bo->deductor_type_id == 2 || $this->bo->deductor_type_id == 3 || $this->bo->deductor_type_id == 4 ||
                 $this->bo->deductor_type_id == 5 || $this->bo->deductor_type_id == 6 || $this->bo->deductor_type_id == 7 || $this->bo->deductor_type_id == 8 ||
                 $this->bo->deductor_type_id == 9){
             
         }
        if($this->bo->deductor_type_id == 2 || $this->bo->deductor_type_id == 4 || $this->bo->deductor_type_id == 6 || $this->bo->deductor_type_id == 8){
            if($this->bo->state_id ==-1){
                $this->bo->addBRule('Deductor State is required');
            }
        }
         
    }
    
   
}
