<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\cashAccLimit;
use YaLinqo\Enumerable;

/**
 * Description of CashAccLimitValidator
 *
 * @author Priyanka
 */

class CashAccLimitValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateCashAccLimitEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        // Validate duplicate branch  
        $branch_lst = Enumerable::from($this->bo->cash_acc_limit_tran->Rows())->groupBy('$a==>$a["branch_id"]')->ToList();
        foreach($branch_lst as $item) {
            if(count($item)> 1){
                $this->bo->addBRule('Duplicate branch not allowed.');
            }
        }
        
        // Validate duplicate account
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select account_id from ac.cash_acc_limit where account_id=:paccount_id and cash_acc_limit_id!=:pcash_acc_limit_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $cmm->addParam('pcash_acc_limit_id', $this->bo->cash_acc_limit_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Limits already set for the selected account.');
        }
    }
     
    public function validateBeforeDelete() {
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('Select * from ar.invoice_control where income_type_id=:pincome_type_id');        
//        $cmm->addParam('pincome_type_id', $this->bo->income_type_id);
//        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        if(count($result->Rows())>0) {
//            $this->bo->addBRule('Cannot delete Income Type as it is used in Invoice.');
//        } 
//        
//        if($this->bo->is_system_created){
//            $this->bo->addBRule('Cannot delete Income Type as it is System Generated.');
//        }
//        parent::validateBeforeDelete();
    }
}