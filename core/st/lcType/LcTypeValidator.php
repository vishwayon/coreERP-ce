<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\lcType;

/**
 * Description of StockLocationValidator
 *
 * @author Shrishail
 */
class LcTypeValidator extends \app\core\st\base\StockBaseValidator {
    
    public function validateLcTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        
        // Validate duplicate Lc Desc
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select lc_desc from st.lc_type 
                            where lc_type_id!=:plc_type_id And company_id=:pcompany_id
                                And lc_desc ilike :plc_desc");
        $cmm->addParam('plc_desc', $this->bo->lc_desc);
        $cmm->addParam('plc_type_id', $this->bo->lc_type_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Landed Cost Type already exists. Duplicates not allowed.');
        }
        
        // Validate exp_ac_id
        if ($this->bo->jdata->Value()->req_alloc) {
            if ($this->bo->exp_ac_id == -1) {
                $this->bo->addBRule('Expense account required for allocations. Select an Expense Account.');
            }
        }
        
        // Validate liab_ac_id
        if ($this->bo->jdata->Value()->post_gl) {
            if ($this->bo->exp_ac_id == -1) {
                $this->bo->addBRule('Expense account required for GL Posting. Select an Expense Account.');
            }
            if ($this->bo->liab_ac_id == -1) {
                $this->bo->addBRule('Liability account required for GL Posting. Select Liability Account.');
            }
        } else {
            $this->bo->liab_ac_id = -1;
        }
        
        if (!$this->bo->jdata->Value()->req_alloc && !$this->bo->jdata->Value()->post_gl) {
            $this->bo->exp_ac_id = -1;
        }
    }    
    
    public function validateBeforeUnpost(){
        parent::validateStockBeforeUnpost();
    }
}
