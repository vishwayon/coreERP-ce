<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenGratuity extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }
    
    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran)    {
      
        if ($drprtran["en_mode_pay_generation"]=="FinalSettlement"){       
            
            $drprtran["gratuity_from_date" ] = \app\core\hr\gratuity\worker\GratuityWorker::GetEmployeeJoiningDate($this->employee_id);
            $drprtran["gratuity_to_date" ] = $this->to_date ;
            
            $gratuity = new \app\core\hr\gratuity\worker\GratuityCalculator($this->employee_id, $drprtran["gratuity_from_date" ], $drprtran["gratuity_to_date" ]);
            
            $dt_gratuity_tran=$gratuity->calc_gratuity();
            $two_yrs_wages_amt= \app\core\hr\gratuity\worker\GratuityWorker::GetTwoYearsWagesAmt ($this->employee_id, $drprtran["gratuity_to_date" ]);

            $drprtran["gratuity_days"] = round(Enumerable::from($dt_gratuity_tran->Rows())->sum('$a==>$a["gratuity_days"]'), \app\cwf\vsla\Math::$amtScale);
            $drprtran["gratuity_amt"] = round(Enumerable::from($dt_gratuity_tran->Rows())->sum('$a==>$a["amount"]'), \app\cwf\vsla\Math::$amtScale);
            
            $reducible_amt=0;
            
            if ($drprtran["gratuity_amt"] >$two_yrs_wages_amt)
            {
                $reducible_amt= $drprtran["gratuity_amt"] - $two_yrs_wages_amt;                
            }
            
            $drprtran["reducible_amt"]= $reducible_amt;
            
            $net_gratuity_amt = $drprtran["gratuity_amt" ] - $reducible_amt;
            
            $service_year= \app\core\hr\gratuity\worker\GratuityWorker::GetContinousServiceYearforGratuity($this->employee_id, $drprtran["gratuity_from_date" ], $drprtran["gratuity_to_date" ] );
            
            if ($drprtran["en_resign_type"]=="Resigned"){
                if ($service_year["service_year"] >= 5){
                    $drprtran["net_gratuity_amt" ] = $net_gratuity_amt;
                }
                else{
                    $drprtran["net_gratuity_amt" ] = 0;
                }
            }
            else if ($drprtran["en_resign_type"]=="Terminated"){
                 $drprtran["net_gratuity_amt" ] = $net_gratuity_amt;
            }
            
            If (count($dt_gratuity_tran->Rows())>0){
                
                $sl_no=0;
                //Fill Gratuity Details
                foreach($dt_gratuity_tran->Rows() as $drgratuity_item)
                {

                    $sl_no = $sl_no + 1;  
                    
                    $drgratuity = $dtprtrangratuitydetail->NewRow();
                    $drgratuity['sl_no'] = $sl_no;
                    $drgratuity['slab_from_date'] = $drgratuity_item['slab_from']; 
                    $drgratuity['slab_to_date'] = $drgratuity_item['slab_to']; 
                    $drgratuity['slab_days'] = $drgratuity_item['slab_days']; 
                    $drgratuity['gratuity_days'] = $drgratuity_item['gratuity_days']; 
                    $drgratuity['gratuity_amt'] = $drgratuity_item['amount']; 
                    $drgratuity['unpaid_days'] = $drgratuity_item['unpaid_days']; 
                    
                    $dtprtrangratuitydetail->AddRow($drgratuity); 
                }
            }
        }
    }
}
