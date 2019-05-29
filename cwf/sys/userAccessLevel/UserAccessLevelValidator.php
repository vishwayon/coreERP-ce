<?php

namespace app\cwf\sys\userAccessLevel;
use app\cwf\vsla\security\PasswordPolicy;

class UserAccessLevelValidator extends \app\cwf\vsla\xmlbo\ValidatorBase{
    
    public $policy;
    
    public function validateUserAccessLevelEditForm(){    
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
    }
}

