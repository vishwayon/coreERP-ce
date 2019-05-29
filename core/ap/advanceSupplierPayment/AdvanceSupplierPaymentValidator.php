<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\advanceSupplierPayment;
use YaLinqo\Enumerable;

/**
 * Description of AdvanceSupplierPaymentValidator
 *
 * @author priyanka
 */
class AdvanceSupplierPaymentValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateAdvanceSupplierPaymentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        $this->bo->received_from = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $this->bo->supplier_account_id);

        if ($this->bo->fc_type_id == 0) {
            $this->bo->gross_adv_amt_fc = 0;
        } else {
            $this->bo->gross_adv_amt = round(($this->bo->gross_adv_amt_fc * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
        }
        
        if ($this->bo->annex_info->Value()->is_tds_applied) {
            if (!\app\core\tds\worker\TDSWorker::TDSInfoExists($this->bo->supplier_account_id)) {
                $this->bo->addBRule('TDS Information not available for selected supplier. Deduction calculations failed.');
            } else {
                \app\core\tds\worker\TDSWorker::CalculateTds($this->bo, $this->bo->supplier_account_id, $this->bo->gross_adv_amt, $this->bo->gross_adv_amt_fc, $this->bo->gross_adv_amt, $this->bo->gross_adv_amt_fc);
            }
        } else {
            \app\core\tds\worker\TDSWorker::ClearTDSInfo($this->bo);
        }

        $tds_total = 0;
        $tds_fc_total = 0;
            $tds_total = $this->bo->btt_tds_base_rate_amt + $this->bo->btt_tds_ecess_amt + $this->bo->btt_tds_surcharge_amt;
            $tds_fc_total = $this->bo->btt_tds_base_rate_amt_fc + $this->bo->btt_tds_ecess_amt_fc + $this->bo->btt_tds_surcharge_amt_fc;

        if ($this->bo->fc_type_id == 0) {
            $this->bo->credit_amt = round(($this->bo->gross_adv_amt - $tds_total), \app\cwf\vsla\Math::$amtScale);
            $this->bo->credit_amt_fc = 0;
        } else {
            $this->bo->credit_amt_fc = round(($this->bo->gross_adv_amt_fc - $tds_fc_total), \app\cwf\vsla\Math::$amtScale);
        }


        // Check if payment is blocked for selected supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select (annex_info->>'block_pymt')::boolean block_pymt from ap.supplier where supplier_id = :psupplier_id");
        $cmm->addParam('psupplier_id', $this->bo->supplier_account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            if ($dt->Rows()[0]['block_pymt'] == TRUE) {
                $this->bo->addBRule('Payments are blocked for the selected supplier.');
            }
        }

        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->pymt_type == 0) {
                if ($acc_type_id != 1 && $acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash Bank account.');
                }
            } else if ($this->bo->pymt_type == 1) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 45) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            }
        }

        if ($this->bo->fc_type_id == 0) {
            if ($this->bo->credit_amt == 0) {
                $this->bo->addBRule('Net Amount is required');
            }
        } else {
            if ($this->bo->gross_adv_amt_fc == 0) {
                $this->bo->addBRule('Amount FC is required.');
            }

            if ($this->bo->credit_amt_fc == 0) {
                $this->bo->addBRule('Net Amount FC is required.');
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
        If ($this->bo->gross_adv_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->gross_adv_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->gross_adv_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->gross_adv_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }
        // Validate sub_head_alloc
        $this->validateSubHead();
    }

    public function validateBeforeUnpost() {
        // If depreciation document for the period is created then don't allow to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.rl_pl_alloc
                                where rl_pl_id in (select rl_pl_id from ac.rl_pl 
                                                                where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
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
            $this->bo->addBRule('Cannot Unpost as advance already settled in - ' . $msgstr . '.');
        }

        // Validate for TDS Payment
        \app\core\tds\worker\TDSWorker::ValidateTDSOnUnpost($this->bo, $this->bo->voucher_id);

        // If reconciled, don't allow to unpost   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from ap.pymt_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be deleted.');
            }
        }
        
        // If reversed, don't allow to unpost   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select is_reversed from ap.pymt_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['is_reversed']) {
                $this->bo->addBRule('This voucher is reversed. Cannot be unposted.');
            }
        }

        if ($this->bo->annex_info->Value()->po_no != '') {
            // If PO is released, don't allow to unpost
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select status from st.stock_control
                            where stock_id=:ppo_no");
            $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                if ($result->Rows()[0]['status'] == 5) {
                    $this->bo->addBRule('Purchase Order No ' . $this->bo->annex_info->Value()->po_no . ' is already released for this payment. Cannot be unposted.');
                }
            }

            // Validate for TDS Payment
            \app\core\tds\worker\TDSWorker::ValidateTDSOnUnpost($this->bo, $this->bo->voucher_id);

            if ($this->bo->collected) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be unposted.');
            }
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

    

    private function validateSubHead() {

        // Validate Subhead total if exists for Bank Account
        // If selected account does not require Sub Head Allocation or ref allocation, remove allocated sub head or ref info  if any.
        $result = \app\core\ac\subHeadAlloc\SubHeadAllocHelper::IsDetailReqd($this->bo->account_id);
        if ($result['is_detail_reqd'] == 'false') {
            // remove sub head and ref ledger allocation 
            $sub_head_cnt = count($this->bo->shl_head_tran->Rows());
            for ($i = 0; $i <= $sub_head_cnt; $i++) {
                $this->bo->shl_head_tran->removeRow(0);
            }

            $ref_cnt = count($this->bo->rla_head_tran->Rows());
            for ($i = 0; $i <= $ref_cnt; $i++) {
                $this->bo->rla_head_tran->removeRow(0);
            }

            $this->bo->ref_no = '';
            $this->bo->ref_desc = '';
        } else if ($result['is_detail_reqd'] == 'true') {
            if ($result['sub_head_dim_id'] == -1) {// Ref Ledger reqd
                // Remove rows from sub head ledger.
                $sub_head_cnt = count($this->bo->shl_head_tran->Rows());
                for ($i = 0; $i <= $sub_head_cnt; $i++) {
                    $this->bo->shl_head_tran->removeRow(0);
                }

                if ($this->bo->ref_no == '') {
                    // Set connected branch id and document date in alloc
                    foreach ($this->bo->rla_head_tran->Rows() as &$ref_led_row) {
                        $ref_led_row['branch_id'] = $this->bo->branch_id;
                        $ref_led_row['affect_doc_date'] = $this->bo->doc_date;
                    }
                    // Validate ref ledger total 
                    $ref_credit_total = round(Enumerable::from($this->bo->rla_head_tran->Rows())->sum('$a==>$a["net_credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                    if ($this->bo->gross_adv_amt != $ref_credit_total) {
                        $this->bo->addBRule('Ref Ledger total should match with the Amount Received for Journal Account');
                    }
                }
            }
            if ($result['is_ref_ledger'] == 'false') {
                // Set connected document date in alloc
                foreach ($this->bo->shl_head_tran->Rows() as &$shl_row) {
                    $shl_row['branch_id'] = $this->bo->branch_id;
                    $shl_row['doc_date'] = $this->bo->doc_date;
                }
                // Remove rows from ref ledger alloc.
                $ref_cnt = count($this->bo->rla_head_tran->Rows());
                for ($i = 0; $i <= $ref_cnt; $i++) {
                    $this->bo->rla_head_tran->removeRow(0);
                }
                $this->bo->ref_no = '';
                $this->bo->ref_desc = '';

                $credit_total = round(Enumerable::from($this->bo->shl_head_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                if ($this->bo->gross_adv_amt != $credit_total) {
                    $this->bo->addBRule('Sub head total should match with the Amount Received for Journal Account.');
                }
            }
        }

        // Validate account with selected Sub Head Account
        for ($i = count($this->bo->shl_head_tran->Rows()) - 1; $i >= 0; $i--) {
            if ($this->bo->shl_head_tran->Rows()[$i]['sub_head_id'] == -1) {
                $this->bo->shl_head_tran->removeRow($i);
            }
        }

        foreach ($this->bo->shl_head_tran->Rows() as $sub_head_row) {
            if ($this->bo->account_id != $sub_head_row['account_id']) {
                $this->bo->addBRule('Sub Head details does not belong to the selected Account. Kindly revise the Sub Head Allocations.');
                break;
            }
        }

        // Validate account with selected ref ledger Account
        foreach ($this->bo->rla_head_tran->Rows() as $ref_row) {
            if ($this->bo->account_id != $ref_row['account_id']) {
                $this->bo->addBRule('Ref Ledger details does not belong to the selected Account. Kindly revise the Ref Ledger Allocations.');
                break;
            }
        }
    }
}
