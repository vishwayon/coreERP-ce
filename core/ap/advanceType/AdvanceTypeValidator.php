<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\advanceType;

/**
 * Description of AdvanceTypeValidator
 *
 * @author Valli
 */

class AdvanceTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAdvanceTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        
       // Validate duplicate Advance Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select adv_type from ap.adv_type where adv_type ilike :padv_type '
                . 'and adv_type_id!=:padv_type_id');
        $cmm->addParam('padv_type_id', $this->bo->adv_type_id);
        $cmm->addParam('padv_type', $this->bo->adv_type);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Advance Type already exists. Duplicate not allowed.');
        }       
    }
              
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select * from st.stock_control where (annex_info->>'adv_type_id')::bigint=:padv_type_id");        
        $cmm->addParam('padv_type_id', $this->bo->income_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Cannot delete Advance Type as it is used in Purchase Order.');
        }   
    }
}