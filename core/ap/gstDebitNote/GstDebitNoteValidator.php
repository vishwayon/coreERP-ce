<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\gstDebitNote;

/**
 * GstDebitNoteValidator
 * @author Priyanka
 */
class GstDebitNoteValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateGstDebitNoteEditForm() {
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
        If (strtotime($this->bo->doc_date) < strtotime($this->bo->annex_info->Value()->origin_bill_date)){            
            $this->bo->addBRule('Debit Note cannot precede Origin Bill Date [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->origin_bill_date) .']');
        }      
        If (strtotime($this->bo->annex_info->Value()->origin_bill_date) > strtotime($this->bo->annex_info->Value()->supp_ref_date)){            
//            $this->bo->addBRule('Supplier Ref Date cannot precede Origin Bill Date [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->origin_bill_date) .']');
        }    
        If (strtotime($this->bo->doc_date) < strtotime($this->bo->annex_info->Value()->supp_ref_date)){            
            $this->bo->addBRule('Debit Note cannot precede Supplier Ref. Date [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->supp_ref_date) .']');
        }
        
        // Debit Amt cannot be greater than invoice Amt
        foreach ($this->bo->pymt_tran->Rows() as $row) {
            if($row['credit_amt'] > $row['bill_amt']){
                $this->bo->addBRule('Bills - Row[' . $row['sl_no'] . '] : Amount cannot be greater than Bill Amt');
            }
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
        If($this->bo->credit_amt > 0){
            $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->credit_amt);
            $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        }
        
        $rowcount=count($this->bo->payable_ledger_alloc_tran->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->payable_ledger_alloc_tran->removeRow(0);
        }
        
        // Fetch the original Invoice balance if available and allocate the amount to the extent possible.
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.rl_pl_id, a.voucher_id, a.balance, a.balance_fc
                                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                Where voucher_id = :pbill_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('paccount_id', $this->bo->supplier_account_id);
        $cmm->addParam('pto_date', $this->bo->doc_date);
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $cmm->addParam('pbill_id', $this->bo->annex_info->Value()->origin_bill_id);
        $cmm->addParam('pdc', 'C');
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows()) == 1) {
            $newplRow = $this->bo->payable_ledger_alloc_tran->newRow();
            $newplRow['rl_pl_id']= $dtbr->Rows()[0]['rl_pl_id'] ;
            $newplRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $newplRow['voucher_id'] = $this->bo->voucher_id;
            $newplRow['vch_tran_id'] = '';
            $newplRow['doc_date']= $this->bo->doc_date;
            $newplRow['account_id']= $this->bo->supplier_account_id;
            $newplRow['exch_rate']= $this->bo->exch_rate;

            $newplRow['credit_amt']= 0;
            $newplRow['credit_amt_fc']= 0;
            // Allocate only to the extent of balance available
            if(floatval($dtbr->Rows()[0]['balance']) > $this->bo->credit_amt) {
                $newplRow['debit_amt'] = $this->bo->credit_amt;
            } else {
                $newplRow['debit_amt'] = floatval($dtbr->Rows()[0]['balance']);
            }
            if(floatval($dtbr->Rows()[0]['balance']) > $this->bo->credit_amt) {
                $newplRow['debit_amt_fc'] = 0;
            } else {
                $newplRow['debit_amt_fc'] = floatval($dtbr->Rows()[0]['balance_fc']);
            }
            $newplRow['write_off_amt']= 0;
            $newplRow['write_off_amt_fc']= 0;
            $newplRow['debit_exch_diff']= 0;
            $newplRow['credit_exch_diff']= 0;

            $newplRow['net_debit_amt']= $newplRow['debit_amt'];
            $newplRow['net_debit_amt_fc']= $newplRow['debit_amt_fc'];
            $newplRow['net_credit_amt']= 0 ;
            $newplRow['net_credit_amt_fc']= 0;

            $newplRow['status'] = $this->bo->status;
            $this->bo->payable_ledger_alloc_tran->AddRow($newplRow);
        }
    }
    
    public function validateBeforeUnpost(){
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}
