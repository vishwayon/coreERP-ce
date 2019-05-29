<?php

namespace app\cwf\sys\userProfile;
use app\cwf\vsla\security\PasswordPolicy;

class UserProfileValidator extends \app\cwf\vsla\xmlbo\ValidatorBase{
    
    public $policy;
    
    public function validateUserProfileEditForm(){ 
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        
        $rules['min_length'] = 8;
        $rules['max_length'] = 64;
        $this->policy = new PasswordPolicy($rules);
        $this->policy->min_lowercase_chars = 1;
        $this->policy->min_uppercase_chars = 1;
        $this->policy->min_numeric_chars = 1;
        
        // conduct business rule validations
        $this->validateBusinessRules();
        $this->validatepasskey();
    }
    
    public function validatepasskey(){
        $valid = $this->policy->validate($this->bo->user_pass);
        if(!$valid){
            foreach( $this->policy->get_errors() as $k=>$error ){
                $this->bo->addBRule($error);
            }
        }
    }
    
    private function validateBusinessRules() {           
        if($this->bo->old_user_pass!=$this->bo->old_entered_pass){  
            $this->bo->addBRule('Old password does not match.');
        }     
        
        if($this->bo->user_pass==$this->bo->old_user_pass){  
            $this->bo->addBRule('New password should be different from New Password.');
        }
        
        if($this->bo->user_pass!=$this->bo->user_pass_confirm){  
            $this->bo->addBRule('Password and Confirm password should match.');
        }
    }
}

