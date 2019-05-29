<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\invoice;
use YaLinqo\Enumerable;

/**
 * Description of InvoiceValidator
 *
 * @author priyanka
 */
class InvoiceValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateInvoiceEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    protected function validateBusinessRules() { 
        // Bill not allowed to create after 01 Jul, 2017
        if(strtotime($this->bo->doc_date) >= strtotime('2017-07-01')){
            $this->bo->addBRule('Not allowed to create normal Invoice after 01 Jul, 2017. Please create GST Invoice.');
        }
                 
        if(!$this->validateDateValue($this->bo->doc_date)){
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }
        for ($rowIndex=0;$rowIndex< count ($this->bo->invoice_tran->Rows());$rowIndex++) {
            $this->bo->invoice_tran->Rows()[$rowIndex]['sl_no']=$rowIndex+1;
        } 
        
        // Calculate all amount values before validation
        if($this->bo->fc_type_id!=0){            
            foreach($this->bo->invoice_tran->Rows() as &$refinv_tran_row){
                $refinv_tran_row['credit_amt']=round(($refinv_tran_row['credit_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
            }
        }
        
        $credit_total= round(Enumerable::from($this->bo->invoice_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
        $credit_total_fc= round(Enumerable::from($this->bo->invoice_tran->Rows())->sum('$a==>$a["credit_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);
            
        // Recalculate Tax on Save
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CalculateTaxOnSave($this->bo);       
        
        // Validate Tax
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::ValidateTax($this->bo);

        $this->calculateRLAlloc();
        
        // Validate Adv Alloc
        \app\core\ar\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->customer_id, $this->bo->invoice_id);
        
        $this->bo->invoice_amt = round(($credit_total + $this->bo->tax_amt), \app\cwf\vsla\Math::$amtScale);            
        $this->bo->invoice_amt_fc = round(($credit_total_fc + $this->bo->tax_amt_fc), \app\cwf\vsla\Math::$amtScale);

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
        If($this->bo->invoice_amt > 0){
            $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->invoice_amt);
            $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        }
         
        If($this->bo->invoice_amt_fc > 0){
            
            // Fetch currency and sub currency for selected FC
            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc= \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtfc->Rows())>0){
                $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->invoice_amt_fc);
                $this->bo->amt_in_words_fc=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);  
            }   
        }      
        
        if($this->bo->po_no != ''){
            if(strtotime($this->bo->po_date) > strtotime($this->bo->doc_date)){            
                $this->bo->addBRule('Order Ref Date should be less than or equal to Document Date.');
            }        
        }
                
        //Broken rule if credit amt is zero in Invoice Information
        $RowNo = 0;
        foreach ($this->bo->invoice_tran->Rows() as $invoiceTran) {
            $RowNo++;
            if($this->bo->fc_type_id ==0 ){
                if($invoiceTran['credit_amt']==0){
                    $this->bo->addBRule('Item Details - Row[' . $RowNo . '] : Amount is required');
                }                
            }
            else{                
                if($invoiceTran['credit_amt_fc']==0){
                    $this->bo->addBRule('Item Details - Row[' . $RowNo . '] : Amount FC is required');
                }
            }
        }
        
        //Broken rule if no row is present in Invoice Information
        if( $RowNo==0) {
            $this->bo->addBRule('No row present in Item Details to save.');
        }
    }
    
    protected function calculateRLAlloc(){
        foreach($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrl_alloc_row){
            $refrl_alloc_row['exch_rate'] = $this->bo->exch_rate;
            $refrl_alloc_row['doc_date'] = $this->bo->doc_date;
            $refrl_alloc_row['net_debit_amt'] = round($refrl_alloc_row['debit_amt'], \app\cwf\vsla\Math::$amtScale) + round($refrl_alloc_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) + round($refrl_alloc_row['debit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
            $refrl_alloc_row['net_debit_amt_fc'] = round($refrl_alloc_row['debit_amt_fc'], \app\cwf\vsla\Math::$amtScale) + round($refrl_alloc_row['write_off_amt_fc'], \app\cwf\vsla\Math::$amtScale);
        }
    }
    
    public function validateBeforeUnpost(){        
        // If receipt is created then don't allow to unpost Invoice   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.rl_pl_alloc
                                where rl_pl_id in (select rl_pl_id from ac.rl_pl 
                                                                where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->invoice_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            $msgstr='';
            foreach($result->Rows() as $row){
                if($msgstr == ''){
                    $msgstr = $row['voucher_id'];
                }
                else{
                    $msgstr = $msgstr . ', '. $row['voucher_id'];
                }
            }
            $this->bo->addBRule('Cannot Unpost as Receipt(s) - '. $msgstr . ' are already generated.');
        }  
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}
