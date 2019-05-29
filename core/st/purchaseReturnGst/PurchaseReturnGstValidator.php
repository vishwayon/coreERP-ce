<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\purchaseReturnGst;

use YaLinqo\Enumerable;

/**
 * Description of PurchaseReturnGstValidator
 *
 * @author Priyanka
 */
class PurchaseReturnGstValidator extends \app\core\st\base\StockBaseValidator {

    public function validatePurchaseReturnGstEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        if (!$this->validateDateValue($this->bo->doc_date)) {
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }

        If (strtotime($this->bo->doc_date) < strtotime($this->bo->annex_info->Value()->origin_inv_date)) {
            $this->bo->addBRule('Purchase Return cannot precede Stock Purchase [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->origin_inv_date) . ']');
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($this->bo->fc_type_id == 0) {
            $cmm->setCommandText('Select currency as fc_type from ac.fc_type where fc_type_id = :pfc_type_id');
        } else {
            $cmm->setCommandText('Select fc_type  from ac.fc_type where fc_type_id = :pfc_type_id');
        }
        $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
        $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $fc_type = '';
        if (count($dtfc->Rows()) > 0) {
            $fc_type = $dtfc->Rows()[0]['fc_type'];
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
        If ($this->bo->total_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->total_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->total_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->total_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }
        $rowcount = count($this->bo->payable_ledger_alloc_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->payable_ledger_alloc_tran->removeRow(0);
        }

        // Fetch the original Stock Purchase balance if available and allocate the amount to the extent possible.
        if ($this->bo->annex_info->Value()->dcn_type == 0 || $this->bo->annex_info->Value()->dcn_type == 2) {
            // dcn Type is Purchase Return or Post Purchase Discount

            $cmmSpBal = new \app\cwf\vsla\data\SqlCommand();
            $cmmSpBal->setCommandText('select a.rl_pl_id, a.voucher_id, a.balance, a.balance_fc
                                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                Where voucher_id = :psp_id');
            $cmmSpBal->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmmSpBal->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmmSpBal->addParam('paccount_id', $this->bo->account_id);
            $cmmSpBal->addParam('pto_date', $this->bo->doc_date);
            $cmmSpBal->addParam('pvoucher_id', $this->bo->stock_id);
            $cmmSpBal->addParam('psp_id', $this->bo->reference_id);
            $cmmSpBal->addParam('pdc', 'C');
            $dtSpBal = \app\cwf\vsla\data\DataConnect::getData($cmmSpBal);
            if (count($dtSpBal->Rows()) == 1) {
                $newplRow = $this->bo->payable_ledger_alloc_tran->newRow();
                $newplRow['rl_pl_id'] = $dtSpBal->Rows()[0]['rl_pl_id'];
                $newplRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $newplRow['voucher_id'] = $this->bo->stock_id;
                $newplRow['vch_tran_id'] = '';
                $newplRow['doc_date'] = $this->bo->doc_date;
                $newplRow['account_id'] = $this->bo->account_id;
                $newplRow['exch_rate'] = $this->bo->exch_rate;

                // Allocate only to the extent of balance available
                if (floatval($dtSpBal->Rows()[0]['balance']) > $this->bo->total_amt) {
                    $newplRow['debit_amt'] = $this->bo->total_amt;
                } else {
                    $newplRow['debit_amt'] = floatval($dtSpBal->Rows()[0]['balance']);
                }
                if (floatval($dtSpBal->Rows()[0]['balance']) > $this->bo->total_amt) {
                    $newplRow['debit_amt_fc'] = $this->bo->total_amt_fc;
                } else {
                    $newplRow['debit_amt_fc'] = floatval($dtSpBal->Rows()[0]['balance_fc']);
                }
                $newplRow['credit_amt'] = 0;
                $newplRow['credit_amt_fc'] = 0 ;
                $newplRow['debit_exch_diff'] = 0;
                $newplRow['credit_exch_diff'] = 0;

                $newplRow['net_debit_amt'] =  $newplRow['debit_amt'];
                $newplRow['net_debit_amt_fc'] = $newplRow['debit_amt_fc'];
                $newplRow['net_credit_amt'] = 0;
                $newplRow['net_credit_amt_fc'] = 0;

                $newplRow['status'] = $this->bo->status;
                $this->bo->payable_ledger_alloc_tran->AddRow($newplRow);
            }
        }
        
        // Set sales account id from original stock purchase
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.sale_account_id From st.stock_control a where stock_id = :porigin_inv_id');
        $cmm->addParam('porigin_inv_id', $this->bo->annex_info->Value()->origin_inv_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows()) > 0){
            $this->bo->sale_account_id = $dt->Rows()[0]['sale_account_id'];
        }
    }
    
    public function validateBeforePost() {
        if($this->bo->annex_info->Value()->dcn_type == 0) {
            // Validate stocks only when type is purchase return
            parent::validateBeforePost();
        }
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
    }

}
