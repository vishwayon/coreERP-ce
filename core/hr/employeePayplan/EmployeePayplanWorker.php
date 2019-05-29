<?php

namespace app\core\hr\employeePayplan;

class EmployeePayplanWorker{
    
    public static function GetMinDateOnNew($employee_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(a.pay_to_date) as pay_date
                                from (Select max(pay_to_date) as pay_to_date from hr.payroll_control 
                                        where payroll_group_id = (Select payroll_group_id from hr.employee where employee_id = :pemployee_id)
                                        Union All 
                                        select COALESCE(effective_to_date, effective_from_date) from hr.employee_payplan
                                        Where employee_id = :pemployee_id
                                                And effective_from_date = (select max(effective_from_date) from hr.employee_payplan Where employee_id = :pemployee_id)) a');
        $cmm->addParam('pemployee_id', $employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())== 1){
            return $result->Rows()[0]['pay_date'];
        }
        
        return null;
    }
    
    public static function GetMaxPayrollDate($employee_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(a.pay_to_date) as pay_date from hr.payroll_control a
                                        where payroll_group_id = (Select payroll_group_id from hr.employee where employee_id = :pemployee_id)');
        $cmm->addParam('pemployee_id', $employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())== 1){
            return $result->Rows()[0]['pay_date'];
        }
        
        return null;
    }
    
    public static function GetMaxEffectivePayplanDate($employee_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(a.effective_from_date) as effective_from_date from hr.employee_payplan a
                                where employee_id = :pemployee_id');
        $cmm->addParam('pemployee_id', $employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())== 1){
            return $result->Rows()[0]['effective_from_date'];
        }
        
        return null;
    }
}