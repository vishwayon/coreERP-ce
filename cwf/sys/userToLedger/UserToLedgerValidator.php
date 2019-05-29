<?php

namespace app\cwf\sys\userToLedger;

class UserToLedgerValidator extends \app\cwf\vsla\xmlbo\ValidatorBase{
    
    public function validateUserToLedgerEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {
       // Validate duplicate accounts
        $accArray = array();
        foreach ($this->bo->user_to_ledger->Rows() as $row) {
            array_push($accArray, $row['account_id']);
        }
        foreach ($accArray as $row) {
            $accCount = 0;
            foreach ($accArray as $row1) {
                if ($row == $row1) {
                    $accCount += 1;
                }
            }
            if ($accCount > 1) {
                $this->bo->addBRule('Duplicate accounts not allowed.');
                break;
            }
        }    
    }
}

