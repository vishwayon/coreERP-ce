<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\gratuity;

/**
 * Description of gratuity
 *
 * @author valli
 */

class GratuityValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateGratuityEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() 
    {
        
        if(count($this->bo->gratuity_tran->Rows())==0){
          $this->bo->addBRule('Atleast one gratuity record is required.');}
                  
        for ($rowIndex=0;$rowIndex< count ($this->bo->gratuity_tran->Rows());$rowIndex++) 
        {
            $this->bo->gratuity_tran->Rows()[$rowIndex]['sl_no']=$rowIndex+1;
        }   

        // Check if Pay plan created for an employee 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.employee_id, a.employee_payplan_id, a.pay_schedule_id, a.grade_id, 
                              b.effective_from_date, a.effective_to_date
                              from hr.employee_payplan a inner join (select employee_id, max(effective_from_date) as effective_from_date 
                                                                     from hr.employee_payplan where  employee_id = :pemployee_id and 
                                                                     effective_from_date <= :peffectivefromdate group by employee_id) b 
                              on a.employee_id=b.employee_id and a.effective_from_date= b.effective_from_date");
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $cmm->addParam('peffectivefromdate', $this->bo->effective_from_date );
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt->Rows()) > 0){
            $this->bo->addBRule("No Pay Plan created as on selected gratuity to date");
        } 

        
//        $total_work_days =$this->bo->effective_to_date->diff($this->bo->effective_from_date);
//        if($total_work_days < 0){
//            $this->bo->addBRule("Cannot calculate gratuity ... Gratuity from date of the selected employee is greater than gratuity to date ");
//        }
//        
        if(strtotime($this->bo->effective_to_date) < strtotime($this->bo->effective_from_date))
        {
            $this->bo->addBRule('To Date cannot be less than From Date ');
        }
        
        if ($this->bo->total_amt<=0)
        {
            $this->bo->addBRule('Amount cannot be zero');
        }
        
        $employee_gratuity_detail = worker\GratuityWorker::GetContinousServiceYearforGratuity($this->bo->employee_id,$this->bo->gratuity_from_date, $this->bo->gratutity_to_date);
        if ($employee_gratuity_detail['service_year']<=0)
        {
            $this->bo->addBRule("Employee has not Completed his One Year of Service. Hence Not Entitled for Gratuity...");
        } 
        
    }
     
    public function validateLoanEditFormBeforeDelete() 
    {
        // conduct default form validations
        $this->validateBeforeDelete($this->bo);
 
    }    

}
