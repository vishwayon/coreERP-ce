<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\salesReturn;
use YaLinqo\Enumerable;

/**
 * Description of SalesReturnValidator
 *
 * @author vaishali
 */
class SalesReturnValidator extends \app\core\st\base\StockBaseValidator {
    
    public function validateSalesReturnEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        // Ensure max date cut-off for GST
        if(strtotime($this->bo->doc_date) > strtotime('2017-06-30')) {
            $this->bo->addBRule('VAT Sales Return not allowed after 30 Jun, 2017');
        }
        
        if(!$this->validateDateValue($this->bo->doc_date)){
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }       
        If (strtotime($this->bo->doc_date) < strtotime($this->bo->si_date)){            
            $this->bo->addBRule('Sales Return Date cannot be less the Stock Invoice Date [' . $this->bo->si_date .']');
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
        $rowcount=count($this->bo->receivable_ledger_alloc_tran->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->receivable_ledger_alloc_tran->removeRow(0);
        }
        
        // Fetch the original Invoice balance if available and allocate the amount to the extent possible.
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.rl_pl_id, a.voucher_id, a.balance, a.balance_fc
                                from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                Where voucher_id = :pinv_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $cmm->addParam('pto_date', $this->bo->doc_date);
        $cmm->addParam('pvoucher_id', $this->bo->stock_id);
        $cmm->addParam('pinv_id', $this->bo->reference_id);
        $cmm->addParam('pdc', 'D');
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows()) == 1) {
            $newplRow = $this->bo->receivable_ledger_alloc_tran->newRow();
            $newplRow['rl_pl_id']= $dtbr->Rows()[0]['rl_pl_id'] ;
            $newplRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $newplRow['voucher_id'] = $this->bo->stock_id;
            $newplRow['vch_tran_id'] = '';
            $newplRow['doc_date']= $this->bo->doc_date;
            $newplRow['account_id']= $this->bo->account_id;
            $newplRow['exch_rate']= $this->bo->exch_rate;

            $newplRow['debit_amt']= 0;
            $newplRow['debit_amt_fc']= 0;
            // Allocate only to the extent of balance available
            if(floatval($dtbr->Rows()[0]['balance']) > $this->bo->total_amt) {
                $newplRow['credit_amt'] = $this->bo->total_amt;
            } else {
                $newplRow['credit_amt'] = floatval($dtbr->Rows()[0]['balance']);
            }
            if(floatval($dtbr->Rows()[0]['balance']) > $this->bo->total_amt) {
                $newplRow['credit_amt_fc'] = $this->bo->total_amt_fc;
            } else {
                $newplRow['credit_amt_fc'] = floatval($dtbr->Rows()[0]['balance_fc']);
            }
            $newplRow['write_off_amt']= 0;
            $newplRow['write_off_amt_fc']= 0;
            $newplRow['debit_exch_diff']= 0;
            $newplRow['credit_exch_diff']= 0;

            $newplRow['net_debit_amt']= 0;
            $newplRow['net_debit_amt_fc']=0;
            $newplRow['net_credit_amt']= $newplRow['credit_amt'];
            $newplRow['net_credit_amt_fc']=  $newplRow['credit_amt_fc'];

            $newplRow['status'] = $this->bo->status;
            $this->bo->receivable_ledger_alloc_tran->AddRow($newplRow);
        }
    }
    
    public function validateBeforeUnpost(){
        parent::validateStockBeforeUnpost();
    }
}
