<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerRefund;

use YaLinqo\Enumerable;

/**
 * Description of CustomerRefundValidator
 *
 * @author Priyanka
 */
class CustomerRefundValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateCustomerRefundEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
               
        $refrowNo = 0;
        foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrow) {
            $refrowNo++;
            if (strtotime($this->bo->doc_date) < strtotime($refrow['adv_ref_date'])) {
                $this->bo->addBRule('Receivable Allocations [' . $refrowNo . ']: Customer refund date cannot be less than invoice date.');
            } else {
                if ($this->bo->fc_type_id == 0) {
                    $refrow['debit_amt_fc'] = 0;
                    $refrow['net_debit_amt_fc'] = 0;
                } else {
                    $refrow['debit_amt'] = round(($refrow['debit_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                    $refrow['net_debit_amt_fc'] = round(($refrow['debit_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                }
                $refrow['net_debit_amt'] = $refrow['debit_amt'];
                $refrow['doc_date'] = $this->bo->doc_date;
                $refrow['exch_rate'] = $this->bo->exch_rate;
            }
            if($this->bo->is_interbranch == true){
                if($this->bo->branch_id == $refrow['branch_id']){
                    $this->bo->addBRule('Receivable Allocations [' . $refrowNo . ']: Cannot allocate voucher of the same branch as connected branch.');
                }
            }
        }
        
        if($this->bo->net_settled != $this->bo->debit_amt){
            $this->bo->addBRule('Amount Refunded should match with Gross total ('.\app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt).').');
        }
        
        if($this->bo->fc_type_id !=0){
            if($this->bo->net_settled_fc != $this->bo->debit_amt_fc){
                $this->bo->addBRule('Amount Refunded FC should match with Gross total FC ('.\app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt_fc).').');
            }
        }
        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }
        
        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If ($this->bo->debit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->debit_amt_fc > 0) {
            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }
//
//        $RowNo = 0;
//        foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as $tran) {
//            $RowNo++;
//            if ($this->bo->fc_type_id == 0) {
//                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) > round($tran['balance'], \app\cwf\vsla\Math::$amtScale)) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . ']: Net Refunded amount cannot be greater than Balance');
//                }
//                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) == 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Refunded amount is required');
//                }
////                if(round($tran['credit_amt'], \app\cwf\vsla\Math::$amtScale) == 0){
////                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Settled Amount is required');                 
////                }
//                if (round($tran['credit_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Refunded amount cannot be negative');
//                }
//                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Refunded amount cannot be negative');
//                }
//            } else if ($this->bo->fc_type_id != 0) {
//                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) > round($tran['balance_fc'], \app\cwf\vsla\Math::$amtScale)) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Refunded amount FC cannot be greater than Balance FC');
//                }
//                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) == 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Refunded amount FC is required');
//                }
//                if (round($tran['credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Refunded amount FC cannot be negative');
//                }
//                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Refunded FC cannot be negative');
//                }
//            }
//        }

        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->rcpt_type == 0) {
                if ($acc_type_id != 1 && $acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash Bank account.');
                }
            } else if ($this->bo->rcpt_type == 1) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 45) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            }else if ($this->bo->rcpt_type == 2) {
                if ($acc_type_id != 12) {
                    $this->bo->addBRule('Please select Supplier account.');
                }
            }
        }
        
        if($this->bo->received_from==''){
            $this->bo->received_from = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->customer_account_id);
        }
    }

    public function validateBeforeDelete() {
        if ($this->bo->collected) {
            $this->bo->addBRule('This voucher has reconciled items. Cannot be deleted.');
        }
        parent::validateBeforeDelete();
    }

    public function validateBeforeUnpost() {
        if ($this->bo->collected) {
            $this->bo->addBRule('This voucher has reconciled items. Cannot be unposted.');
        }
    }

    public function validateBeforePost() {
//        // Compulsory method named. No implementation currently required
//        if ($this->bo->fc_type_id == 0) {
//            if(($this->bo->credit_amt_total + $this->bo->adv_amt) != $this->bo->net_settled){
//                $this->bo->addBRule('Net Received + Advance ('. \app\cwf\vsla\utils\FormatHelper::FormatAmt(($this->bo->credit_amt_total + $this->bo->adv_amt)).') should match with Amount Received (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->net_settled) .')');
//            }
//        } else {
//            if((($this->bo->credit_amt_total_fc + $this->bo->adv_amt_fc)) != $this->bo->net_settled_fc){
//                $this->bo->addBRule('Net Received Amount FC ('. \app\cwf\vsla\utils\FormatHelper::FormatAmt(($this->bo->credit_amt_total_fc + $this->bo->adv_amt_fc)).') should match with Amount Received FC (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->net_settled) .')');
//            }
//        }
//        
//        if($this->bo->rcpt_type == 2){
//            if ($this->bo->fc_type_id == 0) {
//                if($this->bo->debit_amt != $this->bo->credit_amt){
//                    $this->bo->addBRule('Gross Total ('. \app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt).') should match with Total Settelment (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->credit_amt) .')');
//                }
//            }
//            else{
//                if($this->bo->debit_amt_fc != $this->bo->credit_amt_fc){
//                    $this->bo->addBRule('Gross Total FC ('. \app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt_fc).') should match with Total Settelment FC(' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->credit_amt_fc) .')');
//                }
//            }
//        }
    }
}
