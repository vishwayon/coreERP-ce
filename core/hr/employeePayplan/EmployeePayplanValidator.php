<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\employeePayplan;
use YaLinqo\Enumerable;

/**
 * Description of employeePayplanValidator
 *
 * @author Valli
 */

class EmployeePayplanValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateEmployeePayplanEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate                
        if(!$this->bo->is_effective_to_date){
            $this->bo->effective_to_date = null;
        } 
        else{
            if(strtotime($this->bo->effective_from_date) > strtotime($this->bo->effective_to_date)){
                $this->bo->addBRule('Effective From should be less than Effective To.');
            }
        }        
        
        
        // Validate effective from should always be 1 day of the month
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select en_pay_period from hr.payroll_group where payroll_group_id = (select payroll_group_id from hr.employee where employee_id = :pemployee_id)');
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt->Rows()) > 0){
            // Do not validate if the employee is a new joinee (His join date could be between a month)
            $cmmPPCount = new \app\cwf\vsla\data\SqlCommand();
            $cmmPPCount->setCommandText("select employee_payplan_id from hr.employee_payplan where employee_id = :pemployee_id And employee_payplan_id <> :pemployee_payplan_id");
            $cmmPPCount->addParam('pemployee_id', $this->bo->employee_id);
            $cmmPPCount->addParam('pemployee_payplan_id', $this->bo->employee_payplan_id);
            $dtPPCount = \app\cwf\vsla\data\DataConnect::getData($cmmPPCount);
            if(count($dtPPCount->Rows()) > 0) {
                // Existing Employee. Pay plan should be from begining of the month
                if($dt->Rows()[0]['en_pay_period'] == 2){                
                    if(strtotime($this->bo->effective_from_date)  != strtotime(date("Y-m-01", strtotime($this->bo->effective_from_date)))) {                    
                        $this->bo->addBRule('For Monthly pay period effective from date should be first day of the month.');
                    }
                }
                else {
                    if((strtotime($this->bo->effective_from_date)  != strtotime(date("Y-m-01", strtotime($this->bo->effective_from_date)))) || (strtotime($this->bo->effective_from_date)  != strtotime(date("Y-m-16", strtotime($this->bo->effective_from_date)))) ){
                        $this->bo->addBRule('For Bi-Monthly pay period effective from date should be 1st or 16th of the month.');
                    }
                }
            }
        } 
        
        foreach ($this->bo->epp_detail_emo_tran->Rows() as &$refemorow){
            $refemorow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refemorow['payhead_id']);
        }
        
        foreach ($this->bo->epp_detail_ded_tran->Rows() as &$refdedrow){
            $refdedrow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refdedrow['payhead_id']);
        }
        
        foreach ($this->bo->epp_detail_cc_tran->Rows() as &$refccrow){
            $refccrow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refccrow['payhead_id']);
        }
        
        // Validate Emoluments
        $RowNo = 0;
        foreach ($this->bo->epp_detail_emo_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Employee Payplan Detail(s) Emoluments - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_details'] = ''){
                $this->bo->addBRule('Employee Payplan Detail(s) Emoluments - Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }

        $list = Enumerable::from($this->bo->epp_detail_emo_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Employee Payplan Detail(s) Emoluments - Duplicate Employee Payplan Details not allowed.');
            }
        }  
        
        // Validate Deductions
        $RowNo = 0;
        foreach ($this->bo->epp_detail_ded_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Employee Payplan Detail(s) Deductions - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_details'] = ''){
                $this->bo->addBRule('Employee Payplan Detail(s) Deductions - Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }

        $list = Enumerable::from($this->bo->epp_detail_ded_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Employee Payplan Detail(s) Deductions - Duplicate Employee Payplan Details not allowed.');
            }
        }  
        
        // Validate Company Contributions
        $RowNo = 0;
        foreach ($this->bo->epp_detail_cc_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Employee Payplan Detail(s) Company Contributions - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_details'] = ''){
                $this->bo->addBRule('Employee Payplan Detail(s) Company Contributions - Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }

        $list = Enumerable::from($this->bo->epp_detail_cc_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Employee Payplan Detail(s) Company Contributions Duplicate Employee Payplan Details not allowed.');
            }
        }        
        
        // Check whether effective from date is greater than Resign Date of employee.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select join_date, is_resign_date, resign_date from hr.employee where employee_id = :pemployee_id');
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt->Rows()) > 0){
            if($dt->Rows()[0]['is_resign_date']){
                if(strtotime($this->bo->effective_from_date) > strtotime($dt->Rows()[0]['resign_date'])){
                    $this->bo->addBRule("Payplan Date cannot be after Employee's Resign Date " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dt->Rows()[0]['resign_date']) . ".");
                }
            }
            if(strtotime($this->bo->effective_from_date) < strtotime($dt->Rows()[0]['join_date'])){
                $this->bo->addBRule("Payplan Date cannot be before Employee's Join Date " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dt->Rows()[0]['join_date']). ".");
            }
        }     
        
        
        // Check whether Payplan already exists for the selected effective date
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if($this->bo->employee_payplan_id != -1){
            $cmm->setCommandText('select count(*) cnt from hr.employee_payplan where employee_id = :pemployee_id And effective_from_date = :peffective_from_date and employee_payplan_id <>:pemployee_payplan_id');            
            $cmm->addParam('pemployee_payplan_id', $this->bo->employee_payplan_id );
        }
        else{
            $cmm->setCommandText('select count(*) cnt from hr.employee_payplan where employee_id = :pemployee_id And effective_from_date = :peffective_from_date');
        }
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $cmm->addParam('peffective_from_date', $this->bo->effective_from_date);
        $dt_efd = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt_efd->Rows()) > 0){
            if($dt_efd->Rows()[0]['cnt'] > 0){
                $this->bo->addBRule("Payplan for this Date already exists.");
            }
        }
        
        //Check whether selected effective from date comes before Payroll generated date
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(pay_to_date) pay_to_date from hr.payroll_control where finyear = :pfinyear and payroll_group_id = (Select payroll_group_id from hr.employee where employee_id = :pemployee_id)');
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt->Rows()) > 0){
            if(strtotime($this->bo->effective_from_date) < strtotime($dt->Rows()[0]['pay_to_date'])){
                $this->bo->addBRule("Payroll is already generated upto : " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dt->Rows()[0]['pay_to_date']) . ". Effective From Date should be after this date.");
            }
        }  
    }
        
    public function validateBeforeDelete() {
        // conduct default form validations
        parent::validateBeforeDelete();
                 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(effective_from_date) effective_from_date from hr.employee_payplan where employee_id = :pemployee_id');
        $cmm->addParam('pemployee_id', $this->bo->employee_id );
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        if(count($dt->Rows()) > 0){
            if(strtotime($this->bo->effective_from_date) < strtotime($dt->Rows()[0]['effective_from_date'])){
                $this->bo->addBRule("Payplan exists after current Payplan. Cannnot delete current Payplan.");
            }
        } 
        
        $payoll_date = EmployeePayplanWorker::GetMaxPayrollDate($this->bo->employee_id);
        if($payoll_date != null){
            if(strtotime($this->bo->effective_from_date) < strtotime($payoll_date)){
                $this->bo->addBRule("Payroll already generated for the given date. Cannnot delete current Payplan.");
            }
        }
    }
}
