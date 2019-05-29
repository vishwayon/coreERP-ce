<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\vatType;

/**
 * Description of TaxTypeValidator
 *
 * @author Girish Shenoy
 */
class VatTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    const GST_PURCH_SGST_CGST = 401;
    const GST_PURCH_IGST = 402;
    const GST_PURCH_IMPORT = 403;
    const GST_PURCH_COMPOS = 404;
    const GST_PURCH_SEZ = 405;

        public function validateVatTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate for apply options
        if(!$this->bo->apply_item_tax && $this->bo->apply_tax_schedule_id == -1) {
            $this->bo->addBRule('Please select a overriding Tax Schedule for the VAT Type');
        }
        if($this->bo->apply_item_tax && $this->bo->apply_tax_schedule_id != -1) {
            $this->bo->addBRule('Cannot apply overriding Tax Schedule when "Apply Stock Item Tax" is selected');
        }
    }
}
