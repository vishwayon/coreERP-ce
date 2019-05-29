<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\gstCreditNote;

/**
 * GstCreditNoteValidator
 * @author Priyanka
 */
class GstCreditNoteValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGstCreditNoteEditForm() {
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
            $this->bo->addBRule('Credit Note cannot precede Invoice Date [' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->bo->annex_info->Value()->origin_inv_date) . ']');
        }

        // Credit Amt cannot be greater than invoice Amt
        foreach ($this->bo->rcpt_tran->Rows() as $row) {
            if ($row['debit_amt'] > $row['invoice_amt']) {
                $this->bo->addBRule('Invoices - Row[' . $row['sl_no'] . '] : Amount cannot be greater than Invoice Amt');
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

        $rowcount = count($this->bo->receivable_ledger_alloc_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->receivable_ledger_alloc_tran->removeRow(0);
        }

        // Fetch the original Invoice balance if available and allocate the amount to the extent possible.
        if ($this->bo->annex_info->Value()->dcn_type == 0 || $this->bo->annex_info->Value()->dcn_type == 2) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.rl_pl_id, a.voucher_id, a.balance, a.balance_fc
                                from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                Where voucher_id = :pinv_id');
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', 0);
            $cmm->addParam('paccount_id', $this->bo->customer_account_id);
            $cmm->addParam('pto_date', $this->bo->doc_date);
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmm->addParam('pinv_id', $this->bo->annex_info->Value()->origin_inv_id);
            $cmm->addParam('pdc', 'D');
            $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtbr->Rows()) == 1) {
                $newplRow = $this->bo->receivable_ledger_alloc_tran->newRow();
                $newplRow['rl_pl_id'] = $dtbr->Rows()[0]['rl_pl_id'];
                $newplRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $newplRow['voucher_id'] = $this->bo->voucher_id;
                $newplRow['vch_tran_id'] = '';
                $newplRow['doc_date'] = $this->bo->doc_date;
                $newplRow['account_id'] = $this->bo->customer_account_id;
                $newplRow['exch_rate'] = $this->bo->exch_rate;

                $newplRow['debit_amt'] = 0;
                $newplRow['debit_amt_fc'] = 0;
                // Allocate only to the extent of balance available
                if (floatval($dtbr->Rows()[0]['balance']) > $this->bo->debit_amt) {
                    $newplRow['credit_amt'] = $this->bo->debit_amt;
                } else {
                    $newplRow['credit_amt'] = floatval($dtbr->Rows()[0]['balance']);
                }
                if (floatval($dtbr->Rows()[0]['balance']) > $this->bo->debit_amt) {
                    $newplRow['credit_amt_fc'] = 0;
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
    }

    public function validateBeforePost() {
    }

}
