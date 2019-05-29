<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\cashPayment;

/**
 * Description of CashPaymentValidator
 *
 * @author vaishali
 */
class CashPaymentValidator extends \app\core\ac\base\VoucherBaseValidator {
    
    public function validateCashPaymentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {       
        parent::validateBusinessRules();       
    }
    
    public function validateBeforePost() {
        parent::validateBeforePost();
        $amt = parent::validateCashAccLimitOnPost($this->bo->credit_amt, $this->bo->account_id, $this->bo->doc_date);
        if($amt > 0){
            $this->bo->addBRule('Amount Paid cannot be greater than balance limit ' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($amt) . ' for selected account.');
        }
    }
}
