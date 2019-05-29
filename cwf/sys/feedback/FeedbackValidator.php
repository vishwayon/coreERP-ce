<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\feedback;


/**
 * Description of AssetBookValidator
 *
 * @author girish
 */
class FeedbackValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateFeedbackEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {      
       
    }
    
    public function validateAdminFeedbackEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateAdminBusinessRules();
    }
    
    private function validateAdminBusinessRules() {        
        
        if($this->bo->is_closed){
            $this->bo->closed_by=\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName();
            if($this->bo->remarks == ''){                
                $this->bo->addBRule('Closure Remarks are compulsory to close feedback.');
            }
        }  
        else{
            $this->bo->closed_by = '';
            $this->bo->remarks = '';
        }
            
    }
    
    public function validateAdminFeedbackEditFormBeforeDelete() {        
    }
}
