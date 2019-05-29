<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\tdsPayment;

use YaLinqo\Enumerable;

/**
 * Description of TDSPayment
 *
 * @author Priyanka
 */
class TDSPaymentValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateTDSPaymentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select currency, sub_currency, currency_system from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If ($this->bo->amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        if (!$this->bo->cheque_number) {
            
        } else {
            if ($this->bo->cheque_date == null) {
                $this->bo->addBRule('Select valid cheque date.');
            }
        }
        // validate cheque date if PDC true
        if ($this->bo->annex_info->Value()->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }

        foreach ($this->bo->bill_tds_tran->Rows() as &$row) {
            if ($row['bill_tds_tran_id'] == '') {
                $row['bill_tds_tran_id'] = $row['voucher_id'];
            }
            if (strtotime($row['doc_date']) > strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Date cannot be less than Deduction date:' . $row['doc_date'] . '.');
            }
        }
    }

    public function validateBeforeUnpost() {
        // If depreciation document for the period is created then don't allow to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select challan_bsr, challan_serial from tds.tds_payment_control where voucher_id = :pvoucher_id and status = 5");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['challan_bsr'] != '' || $result->Rows()[0]['challan_bsr'] != '') {
                $this->bo->addBRule('Cannot Unpost as Challan Info already updated.');
            }
        }
    }

    public function validateBeforePost() {
        // do nothing for now
    }

}
