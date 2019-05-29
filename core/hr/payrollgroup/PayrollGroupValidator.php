<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollgroup;
use YaLinqo\Enumerable;

/**
 * Description of PayrollGroupValidator
 *
 * @author Valli
 */

class PayrollGroupValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validatePayrollGroupEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate duplicate PayrollGroup
  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select payroll_group from hr.payroll_group where payroll_group ilike :ppayroll_group and payroll_group_id!=:ppayroll_group_id');
        $cmm->addParam('ppayroll_group', $this->bo->payroll_group);
        $cmm->addParam('ppayroll_group_id', $this->bo->payroll_group_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Payroll Group already exists. Duplicate Payroll Group not allowed.');
        }
    }
}