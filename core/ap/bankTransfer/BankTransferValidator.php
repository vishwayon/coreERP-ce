<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\bankTransfer;

use YaLinqo\Enumerable;

/**
 * Description of BankTransfer
 *
 * @author Valli
 */
class BankTransferValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateBankTransferEditForm() {
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
        If ($this->bo->credit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->credit_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }

        if ($this->bo->fc_type_id == 0) {
            $row['credit_amt_fc'] = 0;
            $row['net_credit_amt_fc'] = 0;
        }

        if (count($this->bo->pymt_tran->Rows()) == 0) {
            $this->bo->addBRule('Enter atleast one row in Bank Transfer Detail.');
        }
        
        
        foreach ($this->bo->pymt_tran->Rows() as $row) {
            if ($row['debit_amt'] <= 0) {
                $this->bo->addBRule('Details - Row[' . $row['sl_no'] . '] : Net Pay amt cannot be zero.');
            }
            if ($row['vch_date'] > $this->bo->doc_date) {
                $this->bo->addBRule('Details - Row[' . $row['sl_no'] . '] : Voucher date cannot be greater than Document Date.');
            }
        }

    }

    public function validateBeforeDelete() {       
        parent::validateBeforeDelete();
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
        
        If ($this->bo->credit_amt == 0) {
            $this->bo->addBRule('Total Amount should be greater than zero.');
        }
    }
    
    public function validateBeforeUnpost() {
         
    }
}
