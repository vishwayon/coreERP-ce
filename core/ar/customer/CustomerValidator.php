<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customer;

/**
 * Description of CustomerValidator
 *
 * @author Shrishail
 */
class CustomerValidator extends \app\core\ac\accountHead\AccountHeadValidator {

    public function validateCustomerEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        $this->bo->account_head = $this->bo->customer;
        $this->bo->account_code = $this->bo->customer_code;

        // get the group id for control account
        $this->fetchGroupForAccount();

        parent::validateBusinessRules();

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select customer from ar.customer where customer ilike :pcustomer and customer_id!=:pcustomer_id');
        $cmm->addParam('pcustomer', $this->bo->customer);
        $cmm->addParam('pcustomer_id', $this->bo->customer_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Customer already exists. Duplicate customer not allowed.');
        }

        // Validate duplicate Customer
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ar_CustomerCodeReqd');
        if ($value == '1') {
            if ($this->bo->customer_code == '') {
                $this->bo->addBRule('Customer Code is required.');
            }
        }

        if ($this->bo->customer_code != '') {
            // Validate duplicate Customer code
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select customer_code from ar.customer where customer_code ilike :pcustomer_code and customer_id!=:pcustomer_id');
            $cmm->addParam('pcustomer_code', $this->bo->customer_code);
            $cmm->addParam('pcustomer_id', $this->bo->customer_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Customer Code already exists. Duplicate Code not allowed.');
            }
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

        // Validate customer default bank (only one bank can be marked as default bank)        
        $count = 0;
        if (count($this->bo->customer_bank_info_tran->Rows()) <> 0) {
            foreach ($this->bo->customer_bank_info_tran->Rows() as $row) {
                if ($row['default_bank'] == true) {
                    $count += 1;
                }
            }
            if ($count == 0) {
                $this->bo->addBRule('Default bank is required.');
            }
            if ($count > 1) {
                $this->bo->addBRule('Only one bank can be marked as default bank.');
            }
        }

        // update gst_state_id and GSTIN of customer in billing address
        $this->bo->customer_address_tran->Rows()[0]['gst_state_id'] = $this->bo->annex_info->Value()->tax_info->gst_state_id;
        $this->bo->customer_address_tran->Rows()[0]['gstin'] = $this->bo->annex_info->Value()->tax_info->gstin;


//        $this->bo->customer_shipping_address_tran->Rows()[0]['contact_person'] = $this->bo->customer_address_tran->Rows()[0]['contact_person'] ;
//        $this->bo->customer_shipping_address_tran->Rows()[0]['fax'] = $this->bo->customer_address_tran->Rows()[0]['fax'] ;
//        $this->bo->customer_shipping_address_tran->Rows()[0]['mobile'] = $this->bo->customer_address_tran->Rows()[0]['mobile'] ;
//        $this->bo->customer_shipping_address_tran->Rows()[0]['phone'] = $this->bo->customer_address_tran->Rows()[0]['phone'] ;
//        $this->bo->customer_shipping_address_tran->Rows()[0]['email'] = $this->bo->customer_address_tran->Rows()[0]['email'] ;

        if ($this->bo->annex_info->Value()->is_overridden == false) {
            $this->bo->customer_name = $this->bo->customer;
        }

        // validate gst stae code with gstin
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->tax_info->gst_state_id), 0, 2);

        if (substr($this->bo->annex_info->Value()->tax_info->gstin, 0, 2) != $state_code && $state_code != "98") {
            $this->bo->addBRule('Tax Regn Details : GSTIN does not belong to GST State.');
        }

        if ($this->bo->annex_info->Value()->tax_info->gstin != $state_code) {
            if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $this->bo->annex_info->Value()->tax_info->gstin)) {
                $this->bo->addBRule('Tax Regn Details : Invalid GSTIN.');
            }
        }

        $row_no = 0;
        foreach ($this->bo->annex_info->Value()->ship_addrs as $ship_row) {
            $row_no = $row_no + 1;
            $ship_row->sl_no = $row_no;
        }
        foreach ($this->bo->annex_info->Value()->ship_addrs as $ship_row) {
            $ship_state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $ship_row->gst_state_id), 0, 2);

            if ($ship_row->gst_state_id == null) {
                $this->bo->addBRule('Shipping Address - Row[' . $ship_row->sl_no . '] : GST State is required.');
            }
            if (substr($ship_row->gstin, 0, 2) != $ship_state_code && $ship_state_code != "98") {
                $this->bo->addBRule('Shipping Address - Row[' . $ship_row->sl_no . '] : GSTIN does not belong to GST State.');
            }
            if ($ship_row->gstin != $ship_state_code) {
                if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $ship_row->gstin)) {
                    $this->bo->addBRule('Shipping Address - Row[' . $ship_row->sl_no . '] : Invalid GSTIN.');
                }
            }
        }


        // Validate duplicate PAN
        if (!$this->bo->annex_info->Value()->tax_info->dup_pan && $this->bo->annex_info->Value()->tax_info->pan != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select customer from ar.customer where (annex_info->'tax_info'->>'pan')::varchar ilike :ppan and customer_id!=:pcustomer_id");
            $cmm->addParam('ppan', $this->bo->annex_info->Value()->tax_info->pan);
            $cmm->addParam('pcustomer_id', $this->bo->customer_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('PAN already exists for ' . $result->Rows()[0]['customer'] . '. Duplicate Code not allowed.');
            }
        }

        // Validate duplicate GSTIN
        if (!$this->bo->annex_info->Value()->tax_info->dup_gstin && $this->bo->annex_info->Value()->tax_info->gstin != $state_code) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select customer from ar.customer where (annex_info->'tax_info'->>'gstin')::varchar ilike :pgstin and customer_id!=:pcustomer_id");
            $cmm->addParam('pgstin', $this->bo->annex_info->Value()->tax_info->gstin);
            $cmm->addParam('pcustomer_id', $this->bo->customer_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('GSTIN already exists for ' . $result->Rows()[0]['customer'] . '. Duplicate Code not allowed.');
            }
        }

        // Validate Diff GST Reg name
        if ($this->bo->annex_info->Value()->tax_info->diff_gst_name && $this->bo->annex_info->Value()->tax_info->gst_reg_name == '') {
            $this->bo->addBRule('GST Registered Name is required');
        }
    }

    private function fetchGroupForAccount() {
        // get group id for control account
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select group_id from ac.account_head where account_id=:pcontrol_account_id');
        $cmm->addParam('pcontrol_account_id', $this->bo->control_account_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->group_id = $result->Rows()[0]['group_id'];
        }
    }

}
