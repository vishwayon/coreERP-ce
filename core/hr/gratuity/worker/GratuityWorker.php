<?php
namespace app\core\hr\gratuity\worker;

class GratuityWorker{
    
    public static function GetEmployeeJoiningDate($employee_id){    
     
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select join_date from hr.employee where employee_id=:pemployee_id');
        $cmm->addParam('pemployee_id', $employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())== 1){
            return $result->Rows()[0]['join_date'];            
        }      
        
        return null;
    }
    
    
     public static function GetContinousServiceYearforGratuity($employee_id, $gratuity_from_date, $gratuity_to_date){
        
        $Gratuity_detail = array();
       
        $Gratuity_detail['joindate'] = GratuityWorker::GetEmployeeJoiningDate($employee_id); 
        
        $Gratuity_detail['days_absent'] = GratuityWorker::GetDaysAbsentWithoutPayGratuity($employee_id, $gratuity_from_date, $gratuity_to_date);
        
        $todate=new \DateTime($gratuity_to_date);
        $fromdate=new \DateTime($Gratuity_detail['joindate']);
        $Gratuity_detail['total_days'] = $todate-> diff($fromdate);     
                
        $Gratuity_detail['service_year']= round((($Gratuity_detail['total_days']->days -  $Gratuity_detail['days_absent'] )/365),2) ;
             
        return $Gratuity_detail;
            
    }
    
            
    public static function GetDaysAbsentWithoutPayGratuity($employee_id, $gratuity_from_date, $gratuity_to_date){
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select coalesce((sum(DATE_PART('day', case when to_date > :pgratuity_to_date then :pgratuity_to_date else to_date::timestamp end - from_date::timestamp))),0) as daysabsent
                              from hr.leave a inner join hr.leave_type b on a.leave_type_id=b.leave_type_id
                              where a.employee_id=:pemployee_id and b.paid_leave='t' and a.from_date <= :pgratuity_from_date 
                              and a.to_date >= :pgratuity_to_date and a.authorised_on is not null");
        $cmm->addParam('pemployee_id', $employee_id);
        $cmm->addParam('pgratuity_from_date', $gratuity_from_date);
        $cmm->addParam('pgratuity_to_date', $gratuity_to_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        return $result->Rows()[0]['daysabsent'];
    }
    
    public static function GetEffEmployeePayPlan($employee_id, $gratuity_to_date){
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select a.employee_id,a.employee_payplan_id, a.pay_schedule_id, a.grade_id, 
                b.max_effective_from_date, a.effective_to_date from hr.employee_payplan a
                inner join (select employee_id, max(effective_from_date) max_effective_from_date from hr.employee_payplan  
                where employee_id=:pemployee_id and effective_from_date<=:peffective_date group by employee_id) b
                on a.employee_id=b.employee_id and a.effective_from_date=b.max_effective_from_date";
        $cmm->setCommandText($sql);
        $cmm->addParam('pemployee_id', $employee_id);
        $cmm->addParam('peffective_date', $gratuity_to_date);
        
        return \app\cwf\vsla\data\DataConnect::getData($cmm);        
    }
    
    public static function GetEmoAmountMonthly($pay_schedule_id) {        
        $amt=0;        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select coalesce(sum(amt),0) as amt_monthly from hr.pay_schedule_detail a
                inner join hr.payhead b on a.payhead_id=b.payhead_id where pay_schedule_id=:ppay_schedule_id
                and b.payhead_type='E'";
        $cmm->setCommandText($sql);
        $cmm->addParam('ppay_schedule_id', $pay_schedule_id);
        
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);        
        if (count($dt->Rows())>0){
            $amt=$dt->Rows()[0]['amt_monthly'];
        }        
        return $amt;
    }
    
    public static function GetTwoYearsWagesAmt ($employee_id, $gratuity_to_date){       
      
        $emo_amt_monthly=0;
        $dtEffectivePayPlan= GratuityWorker::GetEffEmployeePayPlan($employee_id,$gratuity_to_date);    
        
        if (count($dtEffectivePayPlan->Rows())>0){                       
            $emo_amt_monthly = GratuityWorker::GetEmoAmountMonthly($dtEffectivePayPlan->Rows()[0]['pay_schedule_id']);
        }
        
        return $emo_amt_monthly * 24;
    }
        
  
}
