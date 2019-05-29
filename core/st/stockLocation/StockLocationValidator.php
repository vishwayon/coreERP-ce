<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\stockLocation;

/**
 * Description of StockLocationValidator
 *
 * @author Shrishail
 */
class StockLocationValidator extends \app\core\st\base\StockBaseValidator {
    
    public function validateStockLocationEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        
        // Validate duplicate Stock Location Name or Stock Location Code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select stock_location_name, stock_location_code from st.stock_location where stock_location_id!=:pstock_location_id '
                             . 'and branch_id=:pbranch_id and company_id=:pcompany_id and '
                             . '(stock_location_name ilike :pstock_location_name or stock_location_code ilike :pstock_location_code)');
        $cmm->addParam('pstock_location_name', $this->bo->stock_location_name);
        $cmm->addParam('pstock_location_code', $this->bo->stock_location_code);
        $cmm->addParam('pstock_location_id', $this->bo->stock_location_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Stock location name or Stock location code already exists. Duplicate Stock location not allowed.');
        }
        
        // Validate duplicate Is Default Branch
        if($this->bo->is_default_for_branch == true) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from st.stock_location where stock_location_id!=:pstock_location_id '
                                 . 'and branch_id=:pbranch_id and company_id=:pcompany_id and '
                                 . 'is_default_for_branch=true');
            $cmm->addParam('pstock_location_id', $this->bo->stock_location_id);
            $cmm->addParam('pbranch_id', $this->bo->branch_id);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $resultIsBranch = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($resultIsBranch->Rows())>0) {
                $this->bo->addBRule('This Branch has already a default Stock location.');
            }
        }
        
//        if($this->bo->sl_type_id == 2){
//            // One SL of Type Production is allowed for one branch
//            $cmm = new \app\cwf\vsla\data\SqlCommand();
//            $cmm->setCommandText('select stock_location_name from st.stock_location where stock_location_id!=:pstock_location_id '
//                                 . 'and branch_id=:pbranch_id and company_id=:pcompany_id and '
//                                 . 'sl_type_id=:psl_type_id');
//            $cmm->addParam('psl_type_id', $this->bo->sl_type_id);
//            $cmm->addParam('pstock_location_id', $this->bo->stock_location_id);
//            $cmm->addParam('pbranch_id', $this->bo->branch_id);
//            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
//            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
//            if(count($result->Rows())>0) {
//                $this->bo->addBRule("Stock location ".$result->Rows()[0]['stock_location_name']." of type Production is already exists. Only one Stock location of type production is allowed for a branch.");
//            }
//        }
        // cleanup based on allowed materials
        $jdata = $this->bo->jdata->Value();
        if($jdata->allow_all_si==false && $jdata->mat_type_ids =='' && $jdata->mat_ids == ''){
            $this->bo->addBRule('Select Allow All Stock Items or specific Stock Item/Type.');
        }
        if($jdata->allow_all_si) {
            $jdata->mat_type_ids = "{}";
            $jdata->mat_ids = "{}";
        }
    }    
    
    public function validateBeforeUnpost(){
        parent::validateStockBeforeUnpost();
    }
}
