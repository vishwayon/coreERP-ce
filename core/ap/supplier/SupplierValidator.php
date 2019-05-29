<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplier;

/**
 * Description of Supplier
 *
 * @author Vaishali
 */
class SupplierValidator extends \app\core\ac\accountHead\AccountHeadValidator {

    public function validateSupplierEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        $this->bo->account_head = $this->bo->supplier;
        $this->bo->account_code = $this->bo->supplier_code;

        // get the group id for selected account
        $this->fetchGroupForAccount();

        parent::validateBusinessRules();

        // Validate duplicate Supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select supplier from ap.supplier where supplier ilike :psupplier and supplier_id!=:psupplier_id');
        $cmm->addParam('psupplier', $this->bo->supplier);
        $cmm->addParam('psupplier_id', $this->bo->supplier_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Supplier already exists. Duplicate supplier not allowed.');
        }

        // Validate duplicate Supplier
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ap_SupplierCodeReqd');
        if ($value == '1') {
            if ($this->bo->supplier_code == '') {
                $this->bo->addBRule('Supplier Code is required.');
            }

            if ($this->bo->supplier_code <> '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select supplier_code from ap.supplier where supplier_code ilike :psupplier_code and supplier_id!=:psupplier_id');
                $cmm->addParam('psupplier_code', $this->bo->supplier_code);
                $cmm->addParam('psupplier_id', $this->bo->supplier_id);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    $this->bo->addBRule('Supplier Code already exists. Duplicate Code not allowed.');
                }
            }
        }

        foreach ($this->bo->supplier_tax_info_tran->Rows() as &$ref_row) {
            if ($ref_row['is_tds_applied'] == True) {
                if ($ref_row['tds_person_type_id'] == -1) {
                    $this->bo->addBRule('TDS Person Type is required.');
                }
                if ($ref_row['tds_section_id'] == -1) {
                    $this->bo->addBRule('TDS Section is required.');
                }
            } else {
                $ref_row['tds_person_type_id'] = -1;
                $ref_row['tds_section_id'] = -1;
                $ref_row['pan'] = '';
            }
            $ref_row['pan'] = $this->bo->annex_info->Value()->satutory_details->pan;
        }

        if ($this->bo->credit_limit_type == 0 || $this->bo->credit_limit_type == 1) {
            if ($this->bo->credit_limit != 0) {
                $this->bo->addBRule('Credit Limit should be zero for No Credit and Unlimited Credit.');
            }
        }

        if ($this->bo->credit_limit_type == 2) {
            if ($this->bo->credit_limit == 0) {
                $this->bo->addBRule('Credit Limit cannot be zero.');
            }
        }
        if ($this->bo->annex_info->Value()->is_overridden == false) {
            $this->bo->supplier_name = $this->bo->supplier;
        }
        // update gst_state_id and GSTIN of customer in billing address
        $this->bo->supplier_address_tran->Rows()[0]['gst_state_id'] = $this->bo->annex_info->Value()->satutory_details->gst_state_id;
        $this->bo->supplier_address_tran->Rows()[0]['gstin'] = $this->bo->annex_info->Value()->satutory_details->gstin;

        // validate gst stae code with gstin
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->satutory_details->gst_state_id), 0, 2);

        if (substr($this->bo->annex_info->Value()->satutory_details->gstin, 0, 2) != $state_code && $state_code != "98") {
            $this->bo->addBRule('Statutory Details of the Organisation : GSTIN does not belong to GST State.');
        }

        if ($this->bo->annex_info->Value()->satutory_details->gstin != $state_code) {
            if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $this->bo->annex_info->Value()->satutory_details->gstin)) {
                $this->bo->addBRule('Statutory Details of the Organisation : Invalid GSTIN.');
            }
        }

        if ($this->bo->annex_info->Value()->satutory_details->is_ctp && strlen($this->bo->annex_info->Value()->satutory_details->gstin) != 15) {
            $this->bo->addBRule('Composition Taxable Person requires valid GSTIN.');
        }

        $row_no = 0;
        foreach ($this->bo->annex_info->Value()->branch_addrs as $branch_row) {
            $row_no = $row_no + 1;
            $ship_state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $branch_row->gst_state_id), 0, 2);

            if ($branch_row->gst_state_id == null) {
                $this->bo->addBRule('Branch Address - Row[' . $row_no . '] : GST State is required.');
            }
            if (substr($branch_row->gstin, 0, 2) != $ship_state_code) {
                $this->bo->addBRule('Branch Address - Row[' . $row_no . '] : GSTIN does not belong to GST State.');
            }
            if ($branch_row->gstin != $ship_state_code) {
                if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $branch_row->gstin)) {
                    $this->bo->addBRule('Branch Address - Row[' . $row_no . '] : Invalid GSTIN.');
                }
            }
        }
        // Validate duplicate PAN
        if (!$this->bo->annex_info->Value()->satutory_details->dup_pan && $this->bo->annex_info->Value()->satutory_details->pan != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select supplier from ap.supplier where (annex_info->'satutory_details'->>'pan')::varchar=:ppan and supplier_id!=:psupplier_id");
            $cmm->addParam('ppan', $this->bo->annex_info->Value()->satutory_details->pan);
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('PAN already exists for ' . $result->Rows()[0]['supplier'] . '. Duplicate Code not allowed.');
            }
        }

        // Validate duplicate GSTIN
        if (!$this->bo->annex_info->Value()->satutory_details->dup_gstin && $this->bo->annex_info->Value()->satutory_details->gstin != $state_code) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select supplier from ap.supplier where (annex_info->'satutory_details'->>'gstin')::varchar=:pgstin and supplier_id!=:psupplier_id");
            $cmm->addParam('pgstin', $this->bo->annex_info->Value()->satutory_details->gstin);
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('GSTIN already exists for ' . $result->Rows()[0]['supplier'] . '. Duplicate Code not allowed.');
            }
        }

        // Validate Diff GST Reg name
        if ($this->bo->annex_info->Value()->satutory_details->diff_gst_name && $this->bo->annex_info->Value()->satutory_details->gst_reg_name == '') {
            $this->bo->addBRule('GST Registered Name is required');
        }
    }

    private function fetchGroupForAccount() {
        // Validate duplicate Supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select group_id from ac.account_head where account_id=:pcontrol_account_id');
        $cmm->addParam('pcontrol_account_id', $this->bo->control_account_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->group_id = $result->Rows()[0]['group_id'];
        }
    }

}
