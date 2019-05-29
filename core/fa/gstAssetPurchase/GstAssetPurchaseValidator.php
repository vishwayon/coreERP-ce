<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\gstAssetPurchase;

use YaLinqo\Enumerable;

/**
 * Description of GstAssetPurchaseValidator
 *
 * @author Priyanka
 */
class GstAssetPurchaseValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGstAssetPurchaseEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        // Convert qty to int
        
        foreach($this->bo->ap_tran->Rows() as &$ref_ap_row){
            $ref_ap_row['asset_qty'] = intval($ref_ap_row['asset_qty']);
        }
        
        if (count($this->bo->ap_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one row in Account Info is required.');
        }

        // If depreciation document for the period is created then don't allow to make purchase        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT max(dep_date_to) as max_date  FROM fa.ad_control where company_id=:pcompany_id And branch_id=:pbranch_id");
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if (strtotime($result->Rows()[0]['max_date']) >= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Asset Purchase is not allowed because Depreciation upto ' . $result->Rows()[0]['max_date'] . ' is calculated.');
            }
        }

        //  Validate duplicate bill no for a supplier
        if ($this->bo->bill_no != 'BNR') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from ap.fn_validate_bill_no(:paccount_id, :pbill_no, :pbill_date, :pvoucher_id)');
            $cmm->addParam('pvoucher_id', $this->bo->ap_id);
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $cmm->addParam('pbill_no', $this->bo->bill_no);
            $cmm->addParam('pbill_date', $this->bo->bill_date);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Bill No '. $this->bo->bill_no .' Dt. '.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']).' already entered for the selected Supplier in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
            } else {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Select bill_no, bill_date, voucher_id from ac.rl_pl "
                        . " where account_id=:paccount_id and bill_no ilike :pbill_no and bill_date = :pbill_date and voucher_id!=:pvoucher_id");
                $cmm->addParam('pvoucher_id', $this->bo->ap_id);
                $cmm->addParam('paccount_id', $this->bo->account_id);
                $cmm->addParam('pbill_no', $this->bo->bill_no);
                $cmm->addParam('pbill_date', $this->bo->bill_date);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    $this->bo->addBRule('Bill No '. $this->bo->bill_no .' Dt. '.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']).' already used for the selected Ledger Account in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
                }
            }
        }

        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->en_purchase_type == 0) {
                if ($acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash account.');
                }
            } else if ($this->bo->en_purchase_type == 1) {
                if ($acc_type_id != 1) {
                    $this->bo->addBRule('Please select Bank account.');
                }
            } else if ($this->bo->en_purchase_type == 2) {
                if ($acc_type_id != 12) {
                    $this->bo->addBRule('Please select Credit account.');
                }
            } else if ($this->bo->en_purchase_type == 3) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 23 || $acc_type_id == 24 || $acc_type_id == 21 || $acc_type_id == 22 || $acc_type_id == 18 || $acc_type_id == 38) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            }
        }
        // validate cheque date if PDC true
        if ($this->bo->annex_info->Value()->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }
        // validate gst state code with gstin
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->gst_input_info->supplier_state_id), 0, 2);

        if (substr($this->bo->annex_info->Value()->gst_input_info->supplier_gstin, 0, 2) != $state_code && $state_code != "98") {
            $this->bo->addBRule('GSTIN does not belong to GST State.');
        }

        if ($this->bo->annex_info->Value()->gst_input_info->supplier_gstin != $state_code) {
            if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $this->bo->annex_info->Value()->gst_input_info->supplier_gstin)) {
                $this->bo->addBRule('Invalid GSTIN.');
            }
        }
        
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

        $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt);
        $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
    }

    public function validateBeforeUnpost() {
        // If depreciation document for the period is created then don't allow to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from fa.asset_dep_ledger
                                where asset_item_id in (Select asset_item_id from fa.asset_item_ledger where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
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
            $this->bo->addBRule('Cannot Unpost as depreciation doc(s) - ' . $msgstr . ' are already generated.');
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as rec_count From fa.asset_item_ledger Where en_asset_tran_type<>0  
			And asset_item_id In (Select asset_item_id From fa.asset_item Where voucher_ID=:pvoucher_id)");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
        $resultapitem = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($resultapitem->Rows()) > 0) {
            if ($resultapitem->Rows()[0]['rec_count'] > 0) {
                $this->bo->addBRule('The Asset Items created from this document have been used in other documents. Unpost failed.');
            }
        }
        
        
        // If Payment already made not allowed to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select b.voucher_id from ac.rl_pl_alloc b
                                where b.rl_pl_id in (select a.rl_pl_id from ac.rl_pl a
                                                                where a.voucher_id=:pvoucher_id)
                                group by b.voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
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
            $this->bo->addBRule('Cannot Unpost as Payaments(s) - ' . $msgstr . ' are already made against this Purchase.');
        }
        
        // If reconciled, don't allow to unpost  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from fa.ap_control where ap_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be unposted.');
            }
        }

    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

    protected function validateRC() {        
        $registered_supplier = false;
        if (preg_match("/[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}/", $this->bo->annex_info->Value()->gst_input_info->supplier_gstin)) {
            $registered_supplier = true;
        }

        if (!$registered_supplier && !$this->bo->annex_info->Value()->gst_rc_info->apply_rc && $this->bo->en_purchase_type == 2) {
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
            if ($this->bo->vat_type_id == \app\core\tx\vatType\VatTypeValidator::GST_PURCH_SGST_CGST) {
                $this->bo->annex_info->Value()->gst_rc_info->rc_sec_id = 94;
            } else {
                $this->bo->annex_info->Value()->gst_rc_info->rc_sec_id = 54;
            }
            $gst_rc_sec = intval($this->bo->annex_info->Value()->gst_rc_info->rc_sec_id);
            if ($gst_rc_sec < 1) {
                $this->bo->addBRule("Select the section under which GST reverse charge is applied");
            } else {
                foreach ($this->bo->ap_tran->Rows() as &$dr) {
                    $dr['gtt_is_rc'] = true;
                    $dr['gtt_rc_sec_id'] = $gst_rc_sec;
                }
            }
        } else {
            foreach ($this->bo->ap_tran->Rows() as &$dr) {
                $dr['gtt_is_rc'] = false;
                $dr['gtt_rc_sec_id'] = -1;
            }
        }
        
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->gst_input_info->supplier_state_id), 0, 2);
        if ($state_code != "98" && $state_code != "99" && $this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
            if ($this->bo->annex_info->Value()->gst_input_info->supplier_gstin != $state_code) {
                // He is therefore a gst registered supplier
                if ($this->bo->annex_info->Value()->gst_rc_info->rc_sec_id == 94) {
                    $this->bo->addBRule('Taxable Supply received from GST registered supplier cannot be subject to reverse charge');
                }
            }
        }

        if ($this->bo->annex_info->Value()->gst_input_info->supplier_gstin == $state_code && $this->bo->annex_info->Value()->gst_rc_info->apply_rc == false && $this->bo->en_purchase_type == 2) {
            $this->bo->addBRule('Unregistered Dealer purchase is subject to reverse charge. Apply Reverse Charge on bill.');
        }
        if ($this->bo->annex_info->Value()->gst_input_info->supplier_gstin != $state_code && $this->bo->annex_info->Value()->gst_rc_info->apply_rc == true) {
            $this->bo->addBRule('Cannot Apply Reverse Charge on bill for Registered Dealer purchase.');
        }        

        // Validate ITC (can be used only if GSTIN of supplier is entered or item is under reverse charge
        foreach ($this->bo->ap_tran->Rows() as $gtt_row) {
            if ($gtt_row['gtt_apply_itc'] && !$gtt_row['gtt_is_rc']) {
                if (!$registered_supplier) {
                    $this->bo->addBRule("ITC claim allowed only when Supplier is registered and/or Reverse Charge is applied on line item");
                    break;
                }
            }
        }
    }
}
