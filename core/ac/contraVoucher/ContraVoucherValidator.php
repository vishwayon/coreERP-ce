<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\contraVoucher;

use YaLinqo\Enumerable;

/**
 * Description of ContraVoucherValidator
 *
 * @author Priyanka
 */
class ContraVoucherValidator extends \app\core\ac\base\VoucherBaseValidator {

    public function validateContraVoucherEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        parent::validateBusinessRules();

        // Validate Duplicate accounts
        $accArray = array();
        array_push($accArray, \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id') . "_" . $this->bo->account_id);
        foreach ($this->bo->vch_tran->Rows() as $row) {
            array_push($accArray, $row['branch_id'] . "_" . $row['account_id']);
        }
        foreach ($accArray as $row) {
            $accCount = 0;
            foreach ($accArray as $row1) {
                if ($row == $row1) {
                    $accCount += 1;
                }
            }
            if ($accCount > 1) {
                $this->bo->addBRule('Duplicate accounts not allowed in Account Info.');
                break;
            }
        }
        
        // Validate control debit and credit amount
        if($this->bo->debit_amt > 0 && $this->bo->credit_amt > 0 ){
            $this->bo->addBRule('Both debit and credit amount cannot be greater than zero.'); 
        }
    }

    public function validateBeforePost() {
        parent::validateBeforePost();
        $row_no = 0;
        foreach ($this->bo->vch_tran->Rows() as $row) {
            $row_no = $row_no + 1;
            if ($row['credit_amt'] > 0) {
                $amt = parent::validateCashAccLimitOnPost($row['credit_amt'], $row['account_id'], $this->bo->doc_date);
                if ($amt > 0) {
                    $this->bo->addBRule('Account Info [' . $row_no . ']: Credits cannot be greater than balance limit ' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($amt) . ' for the selected account.');
                }
            }
        }
    }

}
