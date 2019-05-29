<?php
namespace app\core\hr\payroll\worker;

class PayrollWorker{
    
        
    public static function GetPayrollMaxFromToDate($employee_id){    
     
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select max(pay_from_date) as payroll_from_date, max(pay_to_date) as payroll_to_date from hr.payroll_control a
                              inner join hr.payroll_tran b on a.payroll_id=b.payroll_id 
                              where b.employee_id=:pemployee_id group by b.employee_id');
        $cmm->addParam('pemployee_id', $employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())== 1){
            return $result;            
        }      
        
        return null;
    }
}