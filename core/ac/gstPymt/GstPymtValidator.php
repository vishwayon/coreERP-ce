<?php

namespace app\core\ac\gstPymt;

use YaLinqo\Enumerable;

/**
 * GstPymtValidator
 * @author Girish
 */
class GstPymtValidator extends \app\core\ac\base\VoucherBaseValidator {

    public function validateGstPymtEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        parent::validateBusinessRules();

        $annex_info = $this->bo->annex_info->Value();
        if ($annex_info->line_item_gst) {
            // GST information is for each line
            //clear all bill level gst information
            $annex_info->gst_input_info->supplier_name = "";
            $annex_info->gst_input_info->supplier_address = "";
            $annex_info->gst_input_info->supplier_gstin = "";
            $annex_info->gst_input_info->is_ctp = false;
            $annex_info->gst_rc_info->apply_rc = false;
            $annex_info->gst_rc_info->rc_sec_id = -1;

            // Set bill level information
            $this->bo->vch_caption = "Various Parties";

            // Validate each line item for gst information
            $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->gst_input_info->supplier_state_id), 0, 2);
            foreach ($this->bo->vch_tran->Rows() as $vtran) {
                $this->validateLineRC($vtran);
            }
        } else {
            foreach ($this->bo->vch_tran->Rows() as &$vtran) {
                //clear all line item gst information
                $vtran['supp_gstin'] = "";
                $vtran['is_ctp'] = false;
                $vtran['bill_no'] = "";
                $vtran['bill_dt'] = '1970-01-01';
                $vtran['bill_amt'] = 0.00;
                $vtran['roff_amt'] = 0.00;

                // Set the rc_sec and rc
                if ($annex_info->gst_rc_info->apply_rc) {
                    $vtran['gtt_is_rc'] = true;
                    $vtran['gtt_rc_sec_id'] = $annex_info->gst_rc_info->rc_sec_id;
                } else {
                    $vtran['gtt_is_rc'] = false;
                    $vtran['gtt_rc_sec_id'] = -1;
                }
            }
            // Set bill level information
            $this->bo->vch_caption = $annex_info->gst_input_info->supplier_name;


            // Validate Supplier Information
            if ($annex_info->gst_input_info->supplier_name == "" || $annex_info->gst_input_info->supplier_address == "" || $annex_info->gst_input_info->supplier_gstin == "") {
                $this->bo->addBRule('Incomplete Supplier Information. Enter Supplier name, address and GSTIN');
            }

            // validate gst state code with gstin
            $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->gst_input_info->supplier_state_id), 0, 2);
            if (substr($annex_info->gst_input_info->supplier_gstin, 0, 2) != $state_code) {
                $this->bo->addBRule('Statutory Details of the Supplier : GSTIN does not belong to GST State.');
            }
            $this->validateRC();
        }

        // Common Validations
        // 
        // Validate if GST% is zero GST amt cannot be greater than zero
        foreach ($this->bo->vch_tran->Rows() as $row) {
            if ($row['gtt_sgst_pcnt'] == 0 && $row['gtt_sgst_amt'] != 0) {
                $this->bo->addBRule('Account Info/Debits - Row[' . $row['sl_no'] . '] : SGST amount should be zero for Interstate Purchase or exempt items.');
            }
            if ($row['gtt_cgst_pcnt'] == 0 && $row['gtt_cgst_amt'] != 0) {
                $this->bo->addBRule('Account Info/Debits - Row[' . $row['sl_no'] . '] : CGST amount should be zero for Interstate Purchase or exempt items.');
            }
            if ($row['gtt_igst_pcnt'] == 0 && $row['gtt_igst_amt'] != 0) {
                $this->bo->addBRule('Account Info/Debits - Row[' . $row['sl_no'] . '] : IGST amount should be zero for Local Purchase or exempt items.');
            }
        }
        // Validate Supplier state and GSTIN
        if ($annex_info->gst_input_info->vat_type_id == -1 ||
                $annex_info->gst_input_info->supplier_state_id == -1) {
            $this->bo->addBRule("Failed to resolve GST Type/Supplier State. Select Supplier State again.");
        }
        // The bill amt is always available when gst is bill/line level
        if ($this->bo->bill_diff != 0) {
            $this->bo->addBRule("Bill Total does not match. Please correct the document");
        }
        foreach ($this->bo->vch_tran->Rows() as &$vchtran) {
            if($vchtran['gtt_hsn_sc_code'] != 'NONGST') {
                if (!preg_match("/^[\d]+$/", $vchtran['gtt_hsn_sc_code']) || strlen($vchtran['gtt_hsn_sc_code']) > 8) {
                    $this->bo->addBRule("Sl. " . $vchtran['sl_no'] . ": Incorrect HSN/SC code");
                }
            }
            if ($vchtran['gtt_gst_rate_id'] <= 0) {
                $this->bo->addBRule("Sl. " . $vchtran['sl_no'] . ": GST Rate not selected");
            }
            // Set hsn goods/service
            if (preg_match("/^[99]/", $vchtran['gtt_hsn_sc_code'])) {
                $vchtran['gtt_hsn_sc_type'] = "S";
            } else {
                $vchtran['gtt_hsn_sc_type'] = "G";
            }
        }

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

                    if ($this->bo->annex_info->Value()->bill_amt != $ref_credit_total) {
                        $this->bo->addBRule('Ref Ledger total should match with the Bill Amt for Journal Account');
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

                if ($this->bo->annex_info->Value()->bill_amt != $credit_total) {
                    $this->bo->addBRule('Sub head total should match with the Bill Amt for Journal Account.');
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

    protected function validateDuplicateTranAccount() {
        // Allow duplicate tran accounts. 
        // This is special in gstPymt.
    }

    public function validateBeforeUnpost() {
        parent::validateBeforeUnPost();

        // If reconciled, don't allow to unpost PAYV  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from ac.vch_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be unposted.');
            }
        }

        // If Self Invoice is created then don't allow to unpost PAYV  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.si_tran where ref_id=:pvoucher_id
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
            $this->bo->addBRule('Cannot Unpost as Self Invoice - ' . $msgstr . ' is already generated.');
        }
    }

    private function validateRC() {
        $annex_info = $this->bo->annex_info->Value();
        if (strlen($annex_info->gst_input_info->supplier_gstin) != 2 && !preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $annex_info->gst_input_info->supplier_gstin)) {
            $this->bo->addBRule("Incorrect Supplier GSTIN");
        }
        $registered_supplier = false;
        if (preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $annex_info->gst_input_info->supplier_gstin)) {
            $registered_supplier = true;
        }

        if (!$registered_supplier && !$annex_info->gst_rc_info->apply_rc) {
            $this->bo->addBRule("Supply received from unregistered dealer is subject to reverse charge");
        }
        if ($annex_info->gst_rc_info->apply_rc) {
            if (intval($annex_info->gst_rc_info->rc_sec_id) < 1) {
                $this->bo->addBRule("Select the section under which GST reverse charge is applied");
            }
        }

        // Validate based on vat type
        if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SGST_CGST) {
            if ($registered_supplier && $annex_info->gst_rc_info->apply_rc && $annex_info->gst_rc_info->rc_sec_id == 94) {
                $this->bo->addBRule("Registered Supplier cannot be subject to reverse charge u/s 9(4)");
            }
        } else if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_COMPOS) {
            if ($annex_info->gst_rc_info->apply_rc) {
                $this->bo->addBRule("Composition Taxable Person cannot be subject to reverse charge");
            }
        } else if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IGST) {
            if ($registered_supplier && $annex_info->gst_rc_info->apply_rc && $annex_info->gst_rc_info->rc_sec_id == 54) {
                $this->bo->addBRule("Registered Supplier cannot be subject to reverse charge u/s 5(4)");
            }
        } else if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IMPORT || $this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SEZ) {
            if (!$annex_info->gst_rc_info->apply_rc) {
                $this->bo->addBRule("Import/SEZ Supply is subject to reverse charge");
            }
        }
    }

    private function validateLineRC(array $vtran) {
        $annex_info = $this->bo->annex_info->Value();
        // validate gst state code with gstin
        $has_error = false;
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $annex_info->gst_input_info->supplier_state_id), 0, 2);
        if (strlen($vtran['gtt_supplier_gstin']) < 2) {
            $this->bo->addBRule('Statutory Details of Supplier[Row# ' . $vtran['sl_no'] . ': GSTIN missing.');
            $has_error = true;
        } elseif (substr($vtran['gtt_supplier_gstin'], 0, 2) != $state_code) {
            $this->bo->addBRule('Statutory Details of Supplier[Row# ' . $vtran['sl_no'] . ': GSTIN does not belong to GST State.');
            $has_error = true;
        }
        if (strlen($vtran['gtt_supplier_gstin']) != 2 && !preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $vtran['gtt_supplier_gstin'])) {
            $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Incorrect Supplier GSTIN");
            $has_error = true;
        }
        if (!$has_error) {
            $registered_supplier = false;
            if (preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $vtran['gtt_supplier_gstin'])) {
                $registered_supplier = true;
            }

            if (!$registered_supplier && !$vtran['gtt_is_rc']) {
                $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Supply received from unregistered dealer is subject to reverse charge");
            }
            if ($vtran['gtt_is_rc']) {
                if (intval($vtran['gtt_rc_sec_id']) < 1) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Select GST reverse charge section");
                }
            }

            // Validate based on vat type
            if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SGST_CGST) {
                if ($registered_supplier && $vtran['gtt_is_rc'] && $vtran['gtt_rc_sec_id'] == 94) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Registered Supplier cannot be subject to reverse charge u/s 9(4)");
                }
            }
            if ($vtran['gtt_is_ctp'] && $vtran['gtt_is_rc']) {
                $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Composition Taxable Person cannot be subject to reverse charge");
            }
            if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IGST) {
                if ($registered_supplier && $vtran['gtt_is_rc'] && $vtran['gtt_rc_sec_id'] == 54) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Registered Supplier cannot be subject to reverse charge u/s 5(4)");
                }
            }
            if ($annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_IMPORT || $annex_info->gst_input_info->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SEZ) {
                if (!$vtran['gtt_is_rc']) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Import/SEZ Supply is subject to reverse charge");
                }
            }

            // Validate ITC (can be used only if GSTIN of supplier is entered or item is under reverse charge
            if (!$registered_supplier && !$vtran['gtt_is_rc'] && $vtran['gtt_apply_itc']) {
                $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": ITC claim allowed only when Supplier is registered and/or Reverse Charge is applied on line item");
            }

            // Validate bill information
            if (strlen($vtran['bill_no']) == 0 || strtotime($vtran['bill_dt']) > strtotime($this->bo->doc_date) || $vtran['bill_amt'] == 0) {
                $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Incomplete/incorrect Bill No, date or amount");
            }
            if ($vtran['gtt_is_rc']) {
                if (bccomp($vtran['debit_amt'], bcsub($vtran['bill_amt'], $vtran['roff_amt'], 2), 2) != 0) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Bill amount does not match transaction amount");
                }
            } else {
                $tax_amt = bcadd(bcadd($vtran['gtt_sgst_amt'], $vtran['gtt_cgst_amt'], 2), $vtran['gtt_igst_amt'], 2);
                $net_bill_amt = bcsub($vtran['bill_amt'], $vtran['roff_amt'], 2);
                if (bccomp($vtran['debit_amt'], bcsub($net_bill_amt, $tax_amt, 2), 2) != 0) {
                    $this->bo->addBRule("Sl. " . $vtran['sl_no'] . ": Bill amount does not match transaction amount");
                }
            }
        }
    }

}
