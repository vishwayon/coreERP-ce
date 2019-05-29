<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\taxType;

/**
 * Description of TaxTypeValidator
 *
 * @author Girish Shenoy
 */
class TaxTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateTaxTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate Tax Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select tax_type from tx.tax_type where tax_type ilike :ptax_type and tax_type_id!=:ptax_type_id');
        $cmm->addParam('ptax_type', $this->bo->tax_type);
        $cmm->addParam('ptax_type_id', $this->bo->tax_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Tax Type already exists. Duplicate Tax type(s) not allowed.');
        }
    }
}
