<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\employee;
use YaLinqo\Enumerable;

/**
 * Description of EmployeeValidator
 *
 * @author Valli
 */

class EmployeeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateEmployeeEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
                      
        if(!$this->bo->is_resign_date){
            $this->bo->resign_date = null;
        }  
          // Validate duplicate Employee
          $cmm = new \app\cwf\vsla\data\SqlCommand();
          $cmm->setCommandText('Select full_employee_name from hr.employee where full_employee_name ilike :pfull_employee_name and employee_id!=:pemployee_id');
          $cmm->addParam('pfull_employee_name', $this->bo->full_employee_name);
          $cmm->addParam('pemployee_id', $this->bo->employee_id);
          $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
          if(count($result->Rows())>0) {
            $this->bo->addBRule('Employee already exists. Duplicate employee not allowed.');
          }
        
      
          if($this->bo->employee_no!=''){
            // Validate duplicate Employee no
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select employee_no from hr.employee where employee_no ilike :pemployee_no and employee_id!=:pemployee_id');
            $cmm->addParam('pemployee_no', $this->bo->employee_no);
            $cmm->addParam('pemployee_id', $this->bo->employee_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0) {
                $this->bo->addBRule('Employee no already exists. Duplicate employee no not allowed.');
            }           
        }
        
        if($this->bo->gender == -1){
            $this->bo->addBRule('Gender is required.');
        }
        
        if($this->bo->marital_status == "-1"){
            $this->bo->addBRule('Marital Status is required.');
        }

        // 
        // Validate Employee default bank (only one bank can be marked as default bank)        
        $count=0;   
        if (count($this->bo->employee_bank_info_tran->Rows())<>0){
            foreach ($this->bo->employee_bank_info_tran->Rows() as $row) {           
                if($row['default_bank']==true){
                       $count+=1;
                }
            }
            if($count==0)
            {
                $this->bo->addBRule('Default bank is required.');             
            }   
            if($count>1)
            {
                $this->bo->addBRule('Only one bank can be marked as default bank.');             
            }
        }
        
        if($this->bo->is_resign_date){
            if(strtotime($this->bo->join_date) > strtotime($this->bo->resign_date)){
                $this->bo->addBRule('Resign Date should be greater than Join Date.');    
            }
        }
    }
}