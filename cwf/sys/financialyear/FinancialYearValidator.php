<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\financialyear;

/**
 * Description of FinancialYearValidator
 *
 * @author Ravindra
 */
class FinancialYearValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateFinancialYearEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {        
        
//        // Validate duplicate asset book
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText(' SELECT finyear_code FROM sys.finyear where finyear_code ilike :pfinyear_code and finyear_id!=:pfinyear_id');
        $cmm->addParam('pfinyear_code', $this->bo->finyear_code);
        $cmm->addParam('pfinyear_id', $this->bo->finyear_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Year with same code already exists. Duplicate code not allowed.');
        }
        
        if(strtotime($this->bo->year_end) <= strtotime($this->bo->year_begin))
        {
             $this->bo->addBRule('Year ends cannot be less than or equal to year begins.'); 
        }
    }
}
