<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\accountHead;

/**
 * Description of AssetBookValidator
 *
 * @author girish
 */
class AccountHeadValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateAccountHeadEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        $rowcount = count($this->bo->acc_head_hidden->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->acc_head_hidden->removeRow(0);
        }
        foreach ($this->bo->acc_head_hidden_temp->Rows() as $rowtemp) {
            if ($rowtemp['is_hidden'] == False) {
                $newRow = $this->bo->acc_head_hidden->NewRow();
                $newRow['acc_head_hidden_id'] = '';
                $newRow['branch_id'] = $rowtemp['branch_id'];
                $newRow['account_id'] = $this->bo->account_id;
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $this->bo->acc_head_hidden->AddRow($newRow);
            }
        }

        // Validate duplicate Account Code
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ac_AcHeadCodeReqd');
        if ($value == '1') {
            if ($this->bo->account_code == '') {
                $this->bo->addBRule('Account Code is required.');
            }

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select account_code from ac.account_head where account_code ilike :paccount_code and account_id!=:paccount_id');
            $cmm->addParam('paccount_code', $this->bo->account_code);
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Account Code already exists. Duplicate Code not allowed.');
            }
        }

        // Validate duplicate Account
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select account_head from ac.account_head where account_head ilike :paccount_head and account_id!=:paccount_id');
        $cmm->addParam('paccount_head', $this->bo->account_head);
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Account already exists. Duplicate Account not allowed.');
        }

        if ($this->bo->account_type_id == 30) {
            // Validate multiple Profit And Loss Accounts
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select count(account_id) as cnt from ac.account_head where account_type_id = 30 and account_id!=:paccount_id');
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                if ($result->Rows()[0]['cnt'] > 0) {
                    $this->bo->addBRule('Profit & Loss A/c already exists. Multiple Accounts with Account Type Profit & Loss A/c not allowed.');
                }
            }
        }
        
        // Validate only one of the Ref ledger and subhead dimension is selected
        if($this->bo->is_ref_ledger == true && $this->bo->sub_head_dim_id != -1){
            $this->bo->addBRule('Only one of the Reference ledger and Sub Head dimension should be selected.');
        }
        
        // For Cash/Bank account subhead dimension/ref ledger is not allowed
        $acTypes = [1, 2, 49]; // List of blocked account types
        if(in_array($this->bo->account_type_id, $acTypes) && ($this->bo->is_ref_ledger == true || $this->bo->sub_head_dim_id != -1)){            
            $this->bo->addBRule('Reference ledger/Sub Head dimension is not allowed for the selected Account Type.');
        }
    }

}
