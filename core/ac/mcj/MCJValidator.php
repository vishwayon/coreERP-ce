<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\mcj;
use YaLinqo\Enumerable;

/**
 * Description of MCJValidator
 *
 * @author Priyanka
 */
class MCJValidator extends \app\core\ac\base\VoucherBaseValidator {
    
    public function validateMCJEditForm() {
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
        array_push($accArray, \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id') . "_". $this->bo->account_id);
        foreach ($this->bo->vch_tran->Rows() as $row) {        
            array_push($accArray, $row['branch_id'] . "_". $row['account_id']);
        }
        foreach ($accArray as $row) {           
            $accCount=0;
            foreach ($accArray as $row1) {
                if($row==$row1){
                    $accCount+=1;
                }
            }
            if($accCount>1){
                $this->bo->addBRule('Duplicate accounts not allowed in Account Info.'); 
                break;
            }
        }
        
        // Validate control debit and credit amount
        if($this->bo->debit_amt > 0 && $this->bo->credit_amt > 0 ){
            $this->bo->addBRule('Both debit and credit amount cannot be greater than zero.'); 
        }
        
        if($this->bo->debit_amt == 0 && $this->bo->credit_amt == 0 ){
            $this->bo->addBRule('Debit/Credit amount is required'); 
        }
        
        if(!$this->bo->is_reversal){
            $this->bo->reversal_date = null;
        }  
        else{
            $todate = new \DateTime($this->bo->reversal_date);
            $fromdate = new \DateTime($this->bo->doc_date);
            $total_days = $todate->diff($fromdate);
            if ($total_days->days != 1){
                $this->bo->addBRule('Reversal Date should always be One day after the Document date.');
            }
        }
    }
}
