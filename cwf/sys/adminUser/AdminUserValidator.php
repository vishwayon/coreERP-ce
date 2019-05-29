<?php

namespace app\cwf\sys\adminUser;

class AdminUserValidator extends \app\cwf\sys\user\UserValidator{
    
    public $policy;
    
    public function validateAdminUserEditForm(){   
        parent::validateUserEditForm();
    }
}

