<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\accountGroup;

/**
 * Description of AccountGroupValidator
 *
 * @author Kaustubh
 */

class AccountGroupValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAccountGroupEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // *** conduct business rule validations
        $this->validateBusinessRules();     
    }
    
    private function validateBusinessRules() {        
        
        $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id');
        
        // *** Validate duplicate Group Name within the ParentGroup and Company
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($this->bo->group_id <> -1){
            $cmm->setCommandText('SELECT COUNT(*) no_of_rows FROM ac.account_group '
                    . ' WHERE company_id=:pcompany_id AND parent_key=:pparent_key AND '
                    . ' group_name=:pgroup_name AND group_id<>:pgroup_id');
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $cmm->addParam('pparent_key', $this->bo->parent_key);
            $cmm->addParam('pgroup_name', $this->bo->group_name);
            $cmm->addParam('pgroup_id', $this->bo->group_id);
        }
        else{
            $cmm->setCommandText('SELECT COUNT(*) no_of_rows FROM ac.account_group '
                    . ' WHERE company_id=:pcompany_id AND group_name=:pgroup_name AND '
                    . ' parent_key=(SELECT group_key FROM ac.account_group WHERE group_id=:pparent_group_id)');
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $cmm->addParam('pgroup_name', $this->bo->group_name);
            $cmm->addParam('pparent_group_id', $this->bo->parent_group_id);
        }
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        foreach ($result->Rows() as $row) {
            if($row['no_of_rows']>0){
                $this->bo->addBRule('A group with an identical name exists under Destination. Duplicates not allowed.' ); 
            }
        }
        
        // *** Validate if ParentGroup is Changed 
        // *** if changed, restrict move from Asset & Liabilites to Income & Expenses
        if($this->bo->old_parent_group_id <> -1 AND $this->bo->old_parent_group_id <> $this->bo->parent_group_id){            
            $this->validateGroupMove($this->bo->parent_group_id);
        }
        
        if(sizeof($this->bo->getBRules())==0){
            
            // *** Set fields in BO
            if ($this->bo->group_id == '' or $this->bo->group_id == -1){
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('SELECT * FROM ac.sp_account_group_key_create(:pparent_group_id)');
                $cmm->addParam('pparent_group_id', $this->bo->parent_group_id);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

                foreach ($result->Rows() as $row) {
                    $this->bo->parent_key = $row['parent_key'];
                    $this->bo->group_key = $row['group_key'];
                    $this->bo->group_path = $row['group_path'];
                    $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id');
                }
            }
        } 
    }
    
    private function validateGroupMove($new_parent_id){
        // *** Restrict Move from Asset & Libilities to Income & Expenditure
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT (SELECT group_name FROM ac.account_group WHERE group_path = '
                . ' (SELECT left(group_path,2) FROM ac.account_group WHERE group_id = :pgroup_id) AND company_id = :pcompany_id) Source, '
                . ' (SELECT group_name destination FROM ac.account_group WHERE group_path = (select left(group_path, 2) FROM ac.account_group '
                . ' WHERE group_id = :pnew_parent_group_id) AND company_id = :pcompany_id) Destination');
        
        $cmm->addParam('pnew_parent_group_id', $this->bo->parent_group_id);
        $cmm->addParam('pgroup_id', $this->bo->group_id);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        
        $dtAccountGroup = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        foreach ($dtAccountGroup->Rows() as $row) {
            if($row['source'] == "Asset" or $row['source'] == "Liabilities"){
                if($row['destination'] == "Income" or $row['destination'] == "Expenses"){
                    $this->bo->addBRule('Group cannot be moved from Assets/Liabilites to Income/Expenses.' ); 
                }
            }
            
            if($row['source'] == "Income" or $row['source'] == "Expenses"){
                if($row['destination'] == "Asset" or $row['destination'] == "Liabilities"){
                    $this->bo->addBRule('Group cannot be moved from Income/Expenses to Assets/Liablilities.' ); 
                }
            }
        }
        
        // *** Check for the GroupName Already exists in the Newly Moved Group
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT COUNT(*) no_of_rows FROM ac.account_group WHERE parent_key = '
                . ' (SELECT group_key FROM ac.account_group WHERE group_id = :pnew_parent_group_id) '
                . ' AND group_name = :pgroup_name AND company_id = :pcompany_id');
        
        $cmm->addParam('pnew_parent_group_id', $this->bo->parent_group_id);
        $cmm->addParam('pgroup_name', $this->bo->group_name);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        foreach ($result->Rows() as $row) {
            if($row['no_of_rows']>0){
                $this->bo->addBRule('Group Name already exists under destination.' ); 
            }
        }
        
        // *** Ensure that the Target Group is not a Child of the current Group
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM ac.sp_account_group_validate_is_child(:pgroup_id, :ptarget_group_id)');
        
        $cmm->addParam('pgroup_id', $this->bo->group_id);
        $cmm->addParam('ptarget_group_id', $this->bo->parent_group_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($result->Rows() as $row) {
             if($row['pis_child'] == "true"){
                $this->bo->addBRule('Destination group cannot be the child of the moved group.' ); 
            }
        }
    }

    public function validateBeforeDelete() {  
        
         // *** conduct default form validations
        parent::validateBeforeDelete();
        
        // *** Check Whether this Group has any Sub Groups
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) no_of_rows from ac.account_group where parent_key = :pgroup_key AND company_id = :pcompany_id');
        $cmm->addParam('pgroup_key', $this->bo->group_key);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($result->Rows() as $row) {
             if($row['no_of_rows']>0){
                $this->bo->addBRule('Group cannot be deleted as it contains Sub Groups.' ); 
            }
        }
        
        // *** Check Whether Account Entry for Group id Exists in AccountHead
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) no_of_rows from ac.account_head where group_id = :pgroup_id');
        $cmm->addParam('pgroup_id', $this->bo->group_id);
        
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($result->Rows() as $row) {
            if($row['no_of_rows']>0){
                $this->bo->addBRule('Group cannot be deleted as it contains Accounts.' ); 
            }
        }
    }
}
