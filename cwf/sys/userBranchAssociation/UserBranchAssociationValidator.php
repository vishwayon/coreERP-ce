<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userBranchAssociation;

/**
 * Description of UserBranchAssociationValidator
 *
 * @author vaishali
 */
class UserBranchAssociationValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateUserBranchAssociationEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {
        // Validate user row
        if(count($this->bo->user_to_branch->Rows())==0){
          $this->bo->addBRule('User is required.');}
        
        // Validate duplicate selected user(s)        
        $userCount=0;   
        foreach ($this->bo->user_to_branch->Rows() as $row) {           
           $userCount=0;
           foreach ($this->bo->user_to_branch->Rows() as $row1) {
               if($row['user_id']==$row1['user_id']){
                   $userCount+=1;
               }
           }
        }
        if($userCount>1)
        {
            $this->bo->addBRule('Duplicate user(s) not allowed.');             
        }      
    }
}
