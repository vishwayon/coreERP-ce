<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\leave;
use YaLinqo\Enumerable;

/**
 * Description of LeaveValidator
 *
 * @author Valli
 */

class LeaveValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateLeaveEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
       
        if(strtotime($this->bo->applied_on) > strtotime($this->bo->from_date))
        {
           $this->bo->addBRule('Applied On cannot be greater than Leave From');
        }
        
        
        if(strtotime($this->bo->from_date) > strtotime($this->bo->to_date))
        {
           $this->bo->addBRule('Leave From cannot be greater than Leave To');
        }
        
         
        if(!$this->bo->is_authorised_on){
            $this->bo->authorised_on = null;
        }  
        else{            
            if(strtotime($this->bo->applied_on)> strtotime($this->bo->authorised_on))
            {
               $this->bo->addBRule('Applied On cannot be greater than Authorised Date');
            }
            if(strtotime($this->bo->authorised_on) >= strtotime($this->bo->from_date))
            {
               $this->bo->addBRule('Authorised Date should be less than Leave From.');
            }
            if($this->bo->authorised_by_emp_id == -1)
            {
               $this->bo->addBRule('Authorised By is required.');
            }          
            if(strtotime($this->bo->authorised_on) == strtotime('1970-01-01'))
            {
               $this->bo->addBRule('Authorised Date is required.');
            }            
        }        
         
        if ($this->bo->employee_id == $this->bo->replacing_emp_id)
        {
             $this->bo->addBRule('Replacing Employee cannot be same as applying employee');
        }
        
        if($this->bo->replacement_required){
            if($this->bo->replacing_emp_id == -1)
            {
               $this->bo->addBRule('Replaced by is required.');
            }            
        }
        
        if($this->bo->is_rejoin_date){            
            if(strtotime($this->bo->rejoin_date) == strtotime('1970-01-01'))
            {
               $this->bo->addBRule('Rejoined Date is required.');
            }            
        }
        
          // Validate Payplan exists for the employee  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from hr.employee_payplan where employee_id=:pemployee_id');
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())<=0) {
            $this->bo->addBRule('No Payplan Exists for this Employee');
        }
        
        // Validate duplicate Leave 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select max(to_date) as max_date from hr.leave where employee_id=:pemployee_id And leave_id !=:pleave_id');
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $cmm->addParam('pleave_id', $this->bo->leave_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())> 0) {
            if(strtotime($result->Rows()[0]['max_date']) >= strtotime($this->bo->from_date)){
                $this->bo->addBRule('Leave upto ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['max_date']) . ' already entered. Cannot enter duplicate leave.');
            }
        }
                
        // Validate if payroll generated
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.pay_from_date, a.pay_to_date, b.employee_id from hr.payroll_control a
                                inner join hr.payroll_tran b on a.payroll_id = b.payroll_id
                                where employee_id = :pemployee_id and :pto_date <= a.pay_to_date And :pfrom_date >= a.pay_from_date');
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $cmm->addParam('pto_date', $this->bo->to_date);
        $cmm->addParam('pfrom_date', $this->bo->from_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())> 0) {
                $this->bo->addBRule('Payroll already generated for the selected period.');
        }
        
    }
}