<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\saleReturnGst;

/**
 * SaleReturnGstValidator
 * @author Girish
 */
class SaleReturnGstValidator extends \app\core\st\base\StockBaseValidator {

    public function validateSaleReturnGstEditForm() {
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
            $this->bo->addBRule('Sales Return cannot precede Stock Invoice [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->origin_inv_date) . ']');
        }
        
        if(count($this->bo->stock_tran->Rows()) == 0){
            $this->bo->addBRule('Enter atleast one Stock Item.');
        }

        // Ensure that the rate does not exceed Inv. Rate (stored in other_amt)
        if ($this->bo->annex_info->Value()->dcn_type == 2) {
            //dcn type is Post Sale Discount
            foreach ($this->bo->stock_tran->Rows() as $stran) {
                if (floatval($stran['rate']) > floatval($stran['other_amt'])) {
                    $this->bo->addBRule('Post Sale Discount cannot exceed net Invoice Rate per unit [Row# ' . $stran['sl_no'] . ']');
                }
            }
        }
        if ($this->bo->annex_info->Value()->dcn_type == 0 || $this->bo->annex_info->Value()->dcn_type == 3) {
            if ($this->bo->annex_info->Value()->srr_id == -1) {
                $this->bo->addBRule('Return Reason is required.');
            }
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
        $rowcount = count($this->bo->receivable_ledger_alloc_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->receivable_ledger_alloc_tran->removeRow(0);
        }

        // Fetch the original Invoice balance if available and allocate the amount to the extent possible.
        if ($this->bo->annex_info->Value()->dcn_type == 0 || $this->bo->annex_info->Value()->dcn_type == 2) {
            // dcn Type is Sales Return or Post Sale Discount
            $cmm = new \app\cwf\vsla\data\SqlCommand();
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
            $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtbr->Rows()) == 1) {
                $newplRow = $this->bo->receivable_ledger_alloc_tran->newRow();
                $newplRow['rl_pl_id'] = $dtbr->Rows()[0]['rl_pl_id'];
                $newplRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $newplRow['voucher_id'] = $this->bo->stock_id;
                $newplRow['vch_tran_id'] = '';
                $newplRow['doc_date'] = $this->bo->doc_date;
                $newplRow['account_id'] = $this->bo->account_id;
                $newplRow['exch_rate'] = $this->bo->exch_rate;

                $newplRow['debit_amt'] = 0;
                $newplRow['debit_amt_fc'] = 0;
                // Allocate only to the extent of balance available
                if (floatval($dtbr->Rows()[0]['balance']) > $this->bo->total_amt) {
                    $newplRow['credit_amt'] = $this->bo->total_amt;
                } else {
                    $newplRow['credit_amt'] = floatval($dtbr->Rows()[0]['balance']);
                }
                if (floatval($dtbr->Rows()[0]['balance']) > $this->bo->total_amt) {
                    $newplRow['credit_amt_fc'] = $this->bo->total_amt_fc;
                } else {
                    $newplRow['credit_amt_fc'] = floatval($dtbr->Rows()[0]['balance_fc']);
                }
                $newplRow['write_off_amt'] = 0;
                $newplRow['write_off_amt_fc'] = 0;
                $newplRow['debit_exch_diff'] = 0;
                $newplRow['credit_exch_diff'] = 0;

                $newplRow['net_debit_amt'] = 0;
                $newplRow['net_debit_amt_fc'] = 0;
                $newplRow['net_credit_amt'] = $newplRow['credit_amt'];
                $newplRow['net_credit_amt_fc'] = $newplRow['credit_amt_fc'];

                $newplRow['status'] = $this->bo->status;
                $this->bo->receivable_ledger_alloc_tran->AddRow($newplRow);
            }
        }
    }

    public function validateBeforeUnpost() {
//        parent::validateStockBeforeUnpost();
        // If document is settled do not allow to unpost
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.rl_pl_alloc
                                where rl_pl_id in (select rl_pl_id from ac.rl_pl 
                                                                where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->stock_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $msgstr = '';
            foreach ($result->Rows() as $row) {
                if ($msgstr == '') {
                    $msgstr = $row['voucher_id'];
                } else {
                    $msgstr = $msgstr . ', ' . $row['voucher_id'];
                }
            }
            $this->bo->addBRule('Cannot Unpost as Sales Return already settled in - ' . $msgstr . '.');
        }
    }

    public function validateBeforePost() {
        
    }

}
