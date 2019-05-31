<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\gstBill;

use YaLinqo\Enumerable;

/**
 * Description of GstBillValidator
 *
 * @author Priyanka
 */
class GstBillValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGstBillEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        // GST Bill allowed to create after 01 Jul, 2017
        if (strtotime($this->bo->doc_date) < strtotime('2017-07-01')) {
            $this->bo->addBRule('Not allowed to create GST Bills before 01 Jul, 2017.');
        }

        if (!$this->validateDateValue($this->bo->doc_date)) {
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }

        // Validate Supplier state and GSTIN
        if ($this->bo->vat_type_id == -1 ||
                $this->bo->annex_info->Value()->gst_input_info->supplier_state_id == -1) {
            $this->bo->addBRule("Failed to resolve GST Type/Supplier State. Select Supplier with GSTN information.");
        }

        // Validate if GST% is zero GST amt cannot be greater than zero
        foreach ($this->bo->bill_tran->Rows() as $row) {
            if ($row['gtt_sgst_pcnt'] == 0 && $row['gtt_sgst_amt'] != 0) {
                $this->bo->addBRule('Bill Information - Row[' . $row['sl_no'] . '] : SGST amount should be zero for Interstate Purchase or exempt items.');
            }
            if ($row['gtt_cgst_pcnt'] == 0 && $row['gtt_cgst_amt'] != 0) {
                $this->bo->addBRule('Bill Information - Row[' . $row['sl_no'] . '] : CGST amount should be zero for Interstate Purchase or exempt items.');
            }
            if ($row['gtt_igst_pcnt'] == 0 && $row['gtt_igst_amt'] != 0) {
                $this->bo->addBRule('Bill Information - Row[' . $row['sl_no'] . '] : IGST amount should be zero for Local Purchase or exempt items.');
            }
        }

        \app\core\ap\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->supplier_id, $this->bo->bill_id);

        // Validate reverse charge
        $this->validateRC();

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
        
        $this->validatePo();

        $this->ValidateSubHead();
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
                \app\core\tds\worker\TDSWorker::CalculateTds($this->bo, $this->bo->supplier_id, $debit_amt_total, $debit_amt_fc_total, $this->bo->bill_amt, $this->bo->bill_amt_fc);
            }
        } else {
            \app\core\tds\worker\TDSWorker::ClearTDSInfo($this->bo);
            // Set values of default fields such as company_id branch_id and supplier_id            
            $this->bo->btt_doc_date = $this->bo->doc_date;
            $this->bo->btt_company_id = $this->bo->company_id;
            $this->bo->btt_branch_id = $this->bo->branch_id;
            $this->bo->btt_supplier_id = $this->bo->supplier_id;
        }
    }
    
    protected function ValidatePO() {
        // Validate Purchase Order
        $RowNo = 0;
        foreach ($this->bo->bill_tran->Rows() as $row) {
            $RowNo++;
            If ($row['ref_id'] != '')
            {
                $cmm = new \app\cwf\vsla\data\SqlCommand();            
                $cmm->setCommandText('Select supplier_id from ap.bill_control where bill_id=:ppo_id');
                $cmm->addParam('ppo_id', $row['ref_id']);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                
                if (count($result->Rows()) > 0) {
                    if ($result->Rows()[0]['supplier_id'] !=  $this->bo->supplier_id)
                    {
                        $this->bo->addBRule( 'Purchase Order ' . $row['ref_tran_id'] .' does not belongs to the selected Supplier - Row [' . $RowNo .']');
                    } 
                } 
            }
        }
    }

    protected function ValidateSubHead() {

        // Validate Subhead total if exists
        $RowNo = 0;
        foreach ($this->bo->bill_tran->Rows() as $row) {
            $RowNo++;

            // If selected account does not require Sub Head Allocation or ref allocation, remove allocated sub head or ref info  if any.
            $result = \app\core\ac\subHeadAlloc\SubHeadAllocHelper::IsDetailReqd($row['account_id']);
            if ($result['is_detail_reqd'] == 'false') {
                // remove sub head and ref ledger allocation 
                $sub_head_cnt = count($row['sub_head_ledger_tran']->Rows());
                for ($i = 0; $i <= $sub_head_cnt; $i++) {
                    $row['sub_head_ledger_tran']->removeRow(0);
                }

                $ref_cnt = count($row['ref_ledger_alloc_tran']->Rows());
                for ($i = 0; $i <= $ref_cnt; $i++) {
                    $row['ref_ledger_alloc_tran']->removeRow(0);
                }

                $row['ref_no'] = '';
                $row['ref_desc'] = '';
            } else if ($result['is_detail_reqd'] == 'true') {
                if ($result['sub_head_dim_id'] == -1) {// Ref Ledger reqd
                    // Remove rows from sub head ledger.
                    $sub_head_cnt = count($row['sub_head_ledger_tran']->Rows());
                    for ($i = 0; $i <= $sub_head_cnt; $i++) {
                        $row['sub_head_ledger_tran']->removeRow(0);
                    }

                    if ($row['ref_no'] == '') {
                        // Set connected branch id and document date in alloc
                        foreach ($row['ref_ledger_alloc_tran']->Rows() as &$ref_led_row) {
                            $ref_led_row['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                            $ref_led_row['affect_doc_date'] = $this->bo->doc_date;
                        }
                        // Validate ref ledger total 
                        $ref_debit_total = round(Enumerable::from($row['ref_ledger_alloc_tran']->Rows())->sum('$a==>$a["net_debit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                        if ($row['debit_amt'] != $ref_debit_total) {
                            $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Ref Ledger total should match with the Debits.');
                        }
                    }
                }
                if ($result['is_ref_ledger'] == 'false') {
                    // Set connected document date in alloc
                    foreach ($row['sub_head_ledger_tran']->Rows() as &$shl_row) {
                        $shl_row['doc_date'] = $this->bo->doc_date;
                        $shl_row['branch_id'] = $row['branch_id'];
                    }
                    // Remove rows from ref ledger alloc.
                    $ref_cnt = count($row['ref_ledger_alloc_tran']->Rows());
                    for ($i = 0; $i <= $ref_cnt; $i++) {
                        $row['ref_ledger_alloc_tran']->removeRow(0);
                    }

                    $row['ref_no'] = '';
                    $row['ref_desc'] = '';

                    $debit_total = round(Enumerable::from($row['sub_head_ledger_tran']->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                    $credit_total = round(Enumerable::from($row['sub_head_ledger_tran']->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                    if ($row['debit_amt'] != $debit_total) {
                        $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Sub head total should match with the Debits.');
                    }
                }
            }

            // Validate account with selected Sub Head Account
            foreach ($row['sub_head_ledger_tran']->Rows() as $sub_head_row) {
                if ($row['account_id'] != $sub_head_row['account_id']) {
                    $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Sub Head details does not belong to the selected Account. Kindly revise the Sub Head Allocations.');
                    break;
                }
            }

            // Validate account with selected Sub Head Account
            for ($i = count($row['sub_head_ledger_tran']->Rows()) - 1; $i >= 0; $i--) {
                if ($row['sub_head_ledger_tran']->Rows()[$i]['sub_head_id'] == -1) {
                    $row['sub_head_ledger_tran']->removeRow($i);
                }
            }

            // Validate account with selected ref ledger Account
            foreach ($row['ref_ledger_alloc_tran']->Rows() as $ref_row) {
                if ($row['account_id'] != $ref_row['account_id']) {
                    $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Ref Ledger details does not belong to the selected Account. Kindly revise the Ref Ledger Allocations.');
                    break;
                }
            }
        }
    }

    protected function validateRC() {
        $registered_supplier = false;
        if (preg_match("/[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}/", $this->bo->annex_info->Value()->gst_input_info->supplier_gstin)) {
            $registered_supplier = true;
        }

        if (!$registered_supplier && !$this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
            $this->bo->addBRule("Supply received from unregistered dealer is subject to reverse charge");
        }

        // Validate based on vat type
        if ($this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SGST_CGST) {
            if ($registered_supplier && $this->bo->annex_info->Value()->gst_rc_info->apply_rc && $this->bo->annex_info->Value()->gst_rc_info->rc_sec_id == 94) {
                $this->bo->addBRule("Registered Supplier cannot be subject to reverse charge u/s 9(4)");
            }
        } else if ($this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_COMPOS) {
            if ($this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
                $this->bo->addBRule("Composition Taxable Person cannot be subject to reverse charge");
            }
        } else if ($this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IGST) {
            if ($registered_supplier && $this->bo->annex_info->Value()->gst_rc_info->apply_rc && $this->bo->annex_info->Value()->gst_rc_info->rc_sec_id == 54) {
                $this->bo->addBRule("Registered Supplier cannot be subject to reverse charge u/s 5(4)");
            }
        } else if ($this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IMPORT || $this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SEZ) {
            if (!$this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
                $this->bo->addBRule("Import/SEZ Supply is subject to reverse charge");
            }
        }

        // Set the GST Reverse Charge Section
        $gst_rc_sec = -1;
        if ($this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
            $gst_rc_sec = intval($this->bo->annex_info->Value()->gst_rc_info->rc_sec_id);
            if ($gst_rc_sec < 1) {
                $this->bo->addBRule("Select the section under which GST reverse charge is applied");
            } else {
                foreach ($this->bo->bill_tran->Rows() as &$dr) {
                    $dr['gtt_is_rc'] = true;
                    $dr['gtt_rc_sec_id'] = $gst_rc_sec;
                }
            }
        } else {
            foreach ($this->bo->bill_tran->Rows() as &$dr) {
                $dr['gtt_is_rc'] = false;
                $dr['gtt_rc_sec_id'] = -1;
            }
        }

        // Validate ITC (can be used only if GSTIN of supplier is entered or item is under reverse charge
        foreach ($this->bo->bill_tran->Rows() as $gtt_row) {
            if ($gtt_row['gtt_apply_itc'] && !$gtt_row['gtt_is_rc']) {
                if (!$registered_supplier) {
                    $this->bo->addBRule("ITC claim allowed only when Supplier is registered and/or Reverse Charge is applied on line item");
                    break;
                }
            }
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
            $cmm->setCommandText('Select * from ap.fn_validate_bill_no(:paccount_id, :pbill_no, :pbill_date, :pvoucher_id)');
            $cmm->addParam('pvoucher_id', $this->bo->bill_id);
            $cmm->addParam('paccount_id', $this->bo->supplier_id);
            $cmm->addParam('pbill_no', $this->bo->bill_no);
            $cmm->addParam('pbill_date', $this->bo->bill_date);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Bill No '. $this->bo->bill_no .' Dt. '.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']).' already entered for the selected Supplier in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
            } else {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select bill_no, bill_date, voucher_id from ac.rl_pl where account_id=:paccount_id and bill_no ilike :pbill_no and bill_date = :pbill_date and voucher_id!=:pvoucher_id');
                $cmm->addParam('pvoucher_id', $this->bo->bill_id);
                $cmm->addParam('paccount_id', $this->bo->supplier_id);
                $cmm->addParam('pbill_no', $this->bo->bill_no);
                $cmm->addParam('pbill_date', $this->bo->bill_date);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    $this->bo->addBRule('Bill No '. $this->bo->bill_no .' Dt. '.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']).' already used for the selected Ledger Account in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
                }
            }
            if (strtotime($this->bo->bill_date) > strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Bill date should be less than or equal to doc date.');
            }
        } else {
            $this->bo->bill_date = $this->bo->doc_date;
        }
    }

    public function validateBeforeUnpost() {
        // If payment already done, don't allow to unpost bill       
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
            $this->bo->addBRule('Cannot Unpost as Payment(s) - ' . $msgstr . ' are already made against this bill.');
        }

        // Validate for TDS Payment
        \app\core\tds\worker\TDSWorker::ValidateTDSOnUnpost($this->bo, $this->bo->bill_id);

        // If Self Invoice is created then don't allow to unpost PAYV  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.si_tran where ref_id=:pvoucher_id
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->bill_id);
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
            $this->bo->addBRule('Cannot Unpost as Self Invoice - ' . $msgstr . ' is already generated.');
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

}
