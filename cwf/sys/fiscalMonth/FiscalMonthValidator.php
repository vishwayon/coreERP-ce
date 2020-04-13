<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\fiscalMonth;

/**
 * Description of FinancialYearValidator
 *
 * @author Ravindra
 */
class FiscalMonthValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateFiscalMonthEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {        
         // Possible validations could include display warning for pending documents.
        // However, we do not know how many tables are involved.
        $dg_ids = '';
        foreach ($this->bo->doc_group_temp->Rows() as $dr) {
            if ($dr['select']) {
                if ($dg_ids == '') {
                    $dg_ids = $dr['doc_group_id'];                    
                }
                else{
                    $dg_ids .= ',' . $dr['doc_group_id'];
                }
            }
        }
        
//        if($dg_ids != ''){
            $this->bo->annex_info->Value()->doc_group_ids = "{" . $dg_ids . "}";
//        }
    }
}
