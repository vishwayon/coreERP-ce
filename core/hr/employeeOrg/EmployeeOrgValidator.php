<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\employeeOrg;
use YaLinqo\Enumerable;

/**
 * Description of EmployeeOrgValidator
 *
 * @author Valli
 */

class EmployeeOrgValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateEmployeeOrgEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
       
    }
}