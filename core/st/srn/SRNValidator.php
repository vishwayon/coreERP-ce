<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\srn;
use YaLinqo\Enumerable;

/**
 * Description of SRNValidator
 *
 * @author priyanka
 */
class SRNValidator extends \app\core\st\base\StockBaseValidator {
    
    public function validateSRNEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {     
        if(!$this->validateDateValue($this->bo->doc_date)){
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }
        
        if(strtotime($this->bo->annex_info->value()->origin_inv_date) > strtotime($this->bo->doc_date)) {
            $this->bo->addBRule('Original Invoice date cannot be greater than current document date');
        }
        
        if($this->validateDateValue($this->bo->annex_info->value()->origin_inv_date)) {
            $this->bo->addBRule('Original Inovice date should preceed the Financial Year');
        }
        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        if($this->bo->fc_type_id == 0){
            $cmm->setCommandText('Select currency as fc_type from ac.fc_type where fc_type_id = :pfc_type_id');
        }
        else{
            $cmm->setCommandText('Select fc_type  from ac.fc_type where fc_type_id = :pfc_type_id');
        }
        $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
        $dtfc= \app\cwf\vsla\data\DataConnect::getData($cmm);
        $fc_type = '';
        if(count($dtfc->Rows()) > 0){
            $fc_type = $dtfc->Rows()[0]['fc_type'];
        }
        
        $row_cnt =0;
        foreach($this->bo->stock_tran->Rows() as &$refmat_row){
            $row_cnt = $row_cnt +1;
            $refmat_row['sl_no'] = $row_cnt;            
        }
        
        if (count($this->bo->stock_tran->Rows()) == 0) {           
            $this->bo->addBRule('Select atleast one Stock Item for return');
        }
        
        $currency='';
        $subCurrency='';
        $currency_system='';
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows())>0){
            $currency=$dtbr->Rows()[0]['currency'];
            $subCurrency=$dtbr->Rows()[0]['sub_currency'];
            $currency_system=$dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If($this->bo->total_amt > 0){
            $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->total_amt);
            $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        }
               
        If($this->bo->total_amt_fc > 0){
            
            // Fetch currency and sub currency for selected FC
            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc= \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtfc->Rows())>0){
                $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->total_amt_fc);
                $this->bo->amt_in_words_fc=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);  
            }   
        }
    }
    
    public function validateBeforeUnpost(){
        parent::validateStockBeforeUnpost();
    }
}
