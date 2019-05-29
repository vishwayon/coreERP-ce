<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\creditNote;
use YaLinqo\Enumerable;

/**
 * Description of Credit NoteValidator
 *
 * @author Kaustubh
 */

class CreditNoteValidator extends \app\core\ap\bill\BillValidator {
    
    public function validateCreditNoteEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        parent::validateBusinessRules();
    }
    
    protected function validateBill(){          
        //Broken rule if bill amt is zero

        if( $this->bo->net_bill_amt<>0) {
            $this->bo->addBRule('Credit diff should be zero.');
        }

        if($this->bo->fc_type_id !=0){
            //Broken rule if bill amt is zero
            if( $this->bo->bill_amt_fc==0) {
                $this->bo->addBRule('Credit Amount FC is required');
            }            
            
            //Broken rule if amt in Bill Info Tran is zero
            $RowNo = 0;
            foreach ($this->bo->bill_tran->Rows() as $rowBillTran) {
                $RowNo++;
                 if($rowBillTran['debit_amt_fc']==0){
                     $this->bo->addBRule('Credit Note Information - Row[' . $RowNo . '[ : Amount FC is required');
                 }
            }
            
            if( $this->bo->net_bill_amt_fc<>0) {
                $this->bo->addBRule('Credit diff FC should be zero.');
            }
        }
    }
    
    public function validateBeforeUnpost() {
        parent::validateBeforeUnpost();
    }
}