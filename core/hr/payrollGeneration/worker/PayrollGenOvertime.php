<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenOvertime extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }
    
    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran)    {
       $total_ot_hr=0;
       $total_ot_holiday_hr=0;
       $total_ot_special_hr=0;
       $total_ot_amt=0;
       $total_ot_holiday_amt=0;
       $total_ot_special_amt=0;
       $total_overtime_amt=0;
       
        //Check Whether Overtime is applicable for this payrollgroup
       if ( PayrollService::getInstance()->Payroll_Group_Include_OT($this->payroll_group_id)==true)
       {
           $ot_from_date=$this->from_date;
           $ot_to_date=$this->to_date;
           
           //OT Calculated for the Current Month if required to calcualte for the prev month it has to be handled thru settings
           $drprtran['ot_from_date']=$this->from_date;
           $drprtran['ot_to_date']=$this->to_date;
           
           $dt_payplan = $this->Get_Eff_Payplan($ot_from_date, $ot_to_date);
           
           foreach($dt_payplan->Rows() as $row)
           {
              $dt_overtime_details =  $this->Get_Overtime_Details();           
              $ot_hr = 0;
              $ot_holiday_hr=0;
              $ot_special_hr=0;
              $ot_amt=0;
              $ot_holiday_amt=0;
              $ot_special_amt=0;
              if (count($dt_overtime_details->Rows())>0) {
                  $ot_rate=$dt_payplan->Rows()[0]['ot_rate'];
                  $ot_holiday_rate=$dt_payplan->Rows()[0]['ot_holiday_rate'];
                  $ot_special_rate=$dt_payplan->Rows()[0]['ot_special_rate'];
                  
                  if ($ot_rate !=0 ) {
                      $ot_hr=Enumerable::from($dt_overtime_details->Rows())->sum('$a==>$a["overtime"]');
                     $ot_amt=$ot_hr * ($ot_rate/60);
                  }                  
                  $total_ot_hr=$total_ot_hr + $ot_hr;
                  $total_ot_amt=$total_ot_amt + $ot_amt;
                  
                  if ($ot_holiday_rate !=0 ) {
                      $ot_holiday_hr=Enumerable::from($dt_overtime_details->Rows())->sum('$a==>$a["ot_holiday"]');
                      $ot_holiday_amt=$ot_holiday_hr * ($ot_holiday_rate/60);
                  }                  
                  $total_ot_holiday_hr=$total_ot_holiday_hr + $ot_holiday_hr;
                  $total_ot_holiday_amt=$total_ot_holiday_amt + $ot_holiday_amt;
                  
                  if ($ot_special_rate !=0 ) {
                      $ot_special_hr=Enumerable::from($dt_overtime_details->Rows())->sum('$a==>$a["ot_special"]');
                      $ot_special_amt=$ot_special_hr * ($ot_special_rate/60);
                  }                  
                  $total_ot_special_hr=$total_ot_special_hr + $ot_special_hr;
                  $total_ot_special_amt=$total_ot_special_amt + $ot_special_amt;
                  
              }
           }
           
           $total_overtime_amt=$total_ot_amt + $total_ot_holiday_amt + $total_ot_special_amt;
          
            
           $drprtran['tot_ot_hr']=$total_ot_hr;
           $drprtran['tot_ot_holiday_hr']=$total_ot_holiday_hr;
           $drprtran['tot_ot_special_hr']=$total_ot_special_hr;
           $drprtran['tot_ot_amt']=$total_ot_amt;
           $drprtran['tot_ot_holiday_amt']=$total_ot_holiday_amt;
           $drprtran['tot_ot_special_amt']=$total_ot_special_amt;
           $drprtran['tot_overtime_amt']=$total_overtime_amt;
  
           
           if ($total_overtime_amt <> 0 ) {           
               $drprtrandetail = $dtprtrandetail->NewRow();
               $drprtrandetail['employee_id'] = $this->employee_id ;
               $drprtrandetail['employee_fullname'] = $this->employee_fullname ;
               $overtime_payhead_id =  PayrollService::getInstance()->GetOvertimePayheadID();
               $drprtrandetail['payhead_id'] = $overtime_payhead_id;
               $drprtrandetail['payhead'] =  PayrollService::getInstance()->GetOvertimePayhead($overtime_payhead_id);
               $drprtrandetail['payhead_type'] = "E";
               $drprtrandetail['monthly_or_onetime'] = 1;
               $drprtrandetail['emolument_amt'] =$total_overtime_amt;
               $dtprtrandetail->AddRow($drprtrandetail); 
           }       
       }
        
    }

    private function Get_Eff_Payplan($ot_from_date, $ot_to_date) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from hr.sp_emp_eff_payplan(:pemp_id, :pfrom_date, :pto_date)');
        $cmm->addParam('pemp_id', $this->employee_id);
        $cmm->addParam('pfrom_date', $ot_from_date);
        $cmm->addParam('pto_date', $ot_to_date);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function Get_Overtime_Details() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.attendance_id, a.employee_id, a.attendance_date, a.overtime, a.ot_holiday, a.ot_special from hr.attendance a
                             where a.employee_id=:pemp_id and a.attendance_date between :pfrom_date and :pto_date  and a.attendance_date not in 
                             (select a.attendance_date from hr.attendance a inner join hr.leave b on a.employee_id=b.employee_id where a.employee_id=:pemp_id and a.attendance_date between b.from_date and b.to_date)');
        $cmm->addParam('pemp_id', $this->employee_id);
        $cmm->addParam('pfrom_date', $this->from_date);
        $cmm->addParam('pto_date', $this->to_date);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

}
