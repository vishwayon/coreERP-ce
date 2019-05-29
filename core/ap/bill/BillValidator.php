<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\bill;

use YaLinqo\Enumerable;

/**
 * Description of BillValidator
 *
 * @author Kaustubh
 */
class BillValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateBillEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        // Bill not allowed to create after 01 Jul, 2017
        if (strtotime($this->bo->doc_date) >= strtotime('2017-07-01')) {
            $this->bo->addBRule('Not allowed to create normal Bill after 01 Jul, 2017. Please create GST Bill.');
        }

        if (!$this->validateDateValue($this->bo->doc_date)) {
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }
        if ($this->bo->fc_type_id != 0) {
            $this->bo->bill_amt = round(($this->bo->bill_amt_fc * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
            foreach ($this->bo->bill_tran->Rows() as &$refrow) {
                $refrow['debit_amt'] = round(($refrow['debit_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
            }
        }

        $debit_total = round(Enumerable::from($this->bo->bill_tran->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
        $debit_fc_total = round(Enumerable::from($this->bo->bill_tran->Rows())->sum('$a==>$a["debit_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);

        // Recalculate Tax on Save
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CalculateTaxOnSave($this->bo);

        // Validate Tax
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::ValidateTax($this->bo);


        \app\core\ap\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->supplier_id, $this->bo->bill_id);
//        
//        foreach($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_alloc_row){
//            $refpl_alloc_row['exch_rate'] = $this->bo->exch_rate;
//            $refpl_alloc_row['doc_date'] = $this->bo->doc_date;
//            $refpl_alloc_row['net_credit_amt'] = round($refpl_alloc_row['credit_amt'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['credit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
//            $refpl_alloc_row['net_credit_amt_fc'] = round($refpl_alloc_row['credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['write_off_amt_fc'], \app\cwf\vsla\Math::$amtScale);
//        }

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
        If ($this->bo->bill_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->bill_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->bill_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->bill_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }
        $this->validateBill();

        if (sizeof($this->bo->getBRules()) == 0) {
            //To set Sl No 
            for ($rowIndex = 0; $rowIndex < count($this->bo->bill_tran->Rows()); $rowIndex++) {
                $this->bo->bill_tran->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
            }

            for ($rowIndex = 0; $rowIndex < count($this->bo->bill_tds_tran->Rows()); $rowIndex++) {
                $this->bo->bill_tds_tran->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
            }
        }
        // Calculate TDS
        if ($this->bo->annex_info->Value()->is_tds_applied) {
            if (!\app\core\tds\worker\TDSWorker::TDSInfoExists($this->bo->supplier_id)) {
                $this->bo->addBRule('TDS Information not available for selected supplier. Deduction calculations failed.');
            } else {
                $debit_amt_total = round(Enumerable::from($this->bo->bill_tran->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                $debit_amt_fc_total = round(Enumerable::from($this->bo->bill_tran->Rows())->sum('$a==>$a["debit_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);
                if ($this->bo->annex_info->Value()->tds_net_adv) {
                    // Reduce the advance amt from the gross amt
                    $debit_amt_total -= round(Enumerable::from($this->bo->payable_ledger_alloc_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                    $debit_amt_fc_total -= round(Enumerable::from($this->bo->payable_ledger_alloc_tran->Rows())->sum('$a==>$a["credit_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);
                }
                if ($debit_amt_total > 0) {
                    \app\core\tds\worker\TDSWorker::GetRowsInTDSTran($this->bo, $this->bo->supplier_id, $debit_amt_total, $debit_amt_fc_total, $this->bo->bill_amt, $this->bo->bill_amt_fc);
                } else {
                    \app\core\tds\worker\TDSWorker::ClearTDS($this->bo);
                }
            }
        } else {
            \app\core\tds\worker\TDSWorker::ClearTDS($this->bo);
        }
    }

    protected function validateBill() {
        //Broken rule if bill amt is zero                       
        if ($this->bo->net_bill_amt <> 0) {
            $this->bo->addBRule('Bill diff should be zero.');
        }
        if ($this->bo->fc_type_id != 0) {
            //Broken rule if bill amt is zero
            if ($this->bo->bill_amt_fc == 0) {
                $this->bo->addBRule('Bill Amount FC is required');
            }

            //Broken rule if amt in Bill Info Tran is zero
            $RowNo = 0;
            foreach ($this->bo->bill_tran->Rows() as $rowBillTran) {
                $RowNo++;
                if ($rowBillTran['debit_amt_fc'] == 0) {
                    $this->bo->addBRule('Bill Information - Row[' . $RowNo . '] : Amount FC is required');
                }
            }

            if ($this->bo->net_bill_amt_fc <> 0) {
                $this->bo->addBRule('Bill diff FC should be zero.');
            }
        }

        //  Validate duplicate bill no for a supplier
        if ($this->bo->bill_no != 'BNR') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select bill_no, bill_id from ap.bill_control where supplier_id=:psupplier_id and bill_no ilike :pbill_no and bill_id!=:pbill_id');
            $cmm->addParam('pbill_id', $this->bo->bill_id);
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $cmm->addParam('pbill_no', $this->bo->bill_no);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Bill No already used for the selected Supplier in (' . $result->Rows()[0]['bill_id'] . '). Duplicate Bill No not allowed.');
            } else {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select bill_no, voucher_id from ac.rl_pl where account_id=:paccount_id and bill_no ilike :pbill_no and voucher_id!=:pvoucher_id');
                $cmm->addParam('pvoucher_id', $this->bo->bill_id);
                $cmm->addParam('paccount_id', $this->bo->supplier_id);
                $cmm->addParam('pbill_no', $this->bo->bill_no);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    $this->bo->addBRule('Bill No already used for the selected Ledger Account in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
                }
            }
        } else {
            $this->bo->bill_date = $this->bo->doc_date;
        }
    }

    public function validateBeforeUnpost() {
        // If depreciation document for the period is created then don't allow to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select b.voucher_id from ac.rl_pl_alloc b
                                where b.rl_pl_id in (select a.rl_pl_id from ac.rl_pl a
                                                                where a.voucher_id=:pvoucher_id)
                                    And b.voucher_id != :ptdsvoucher_id
                                group by b.voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->bill_id);
        $cmm->addParam('ptdsvoucher_id', $this->bo->bill_id . ':TDS');
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
            $this->bo->addBRule('Cannot Unpost as Payaments(s) - ' . $msgstr . ' are already made against this bill.');
        }

        // Validate for TDS Payment
        \app\core\tds\worker\TDSWorker::ValidateTDSOnUnpost($this->bo, $this->bo->bill_id);
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

}
