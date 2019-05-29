<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenPayhead extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }
    
    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran)    {       
        $dtEffPayPlan = $this->GetEffPayplan();
        $dtPayhead = null;

        foreach ($dtEffPayPlan->Rows() as $drrow) {

            $dtPayhead = $this->FetchPayheads($drrow['employee_payplan_id']);

            foreach ($dtPayhead->Rows() as $payhead_item) {
                $newRow = $dtprtrandetailtemp->NewRow();
                $newRow['employee_id'] = $drrow['employee_id'];
                $newRow['employee_fullname'] = $drrow['full_employee_name'];
                $newRow['emp_payplan_id'] = $drrow['employee_payplan_id'];
                $newRow['calc_eff_from_date'] = $drrow['calc_effective_from_date'];
                $newRow['calc_eff_to_date'] = $drrow['calc_effective_to_date'];
                $newRow['payhead_id'] = $payhead_item['payhead_id'];
                $newRow['payhead'] = $payhead_item['payhead'];
                $newRow['payhead_type'] = $payhead_item['payhead_type'];
                $newRow['incl_in_nopay'] = $payhead_item['incl_in_nopay'];
                if ($payhead_item['payhead_type'] == "E" || $payhead_item['payhead_type'] == "C") {
                    $newRow['emolument_amt'] = $payhead_item['amt'];
                    $newRow['deduction_amt'] = 0;
                } else {
                    $newRow['deduction_amt'] = $payhead_item['amt'];
                    $newRow['emolument_amt'] = 0;
                }

                $newRow['pay_perc'] = $payhead_item['pay_perc'];
                $newRow['pay_on_perc'] = $payhead_item['pay_on_perc'];
                $newRow['pay_on_min_amt'] = $payhead_item['pay_on_min_amt'];
                $newRow['pay_on_max_amt'] = $payhead_item['pay_on_max_amt'];
                $newRow['min_pay_amt'] = $payhead_item['min_pay_amt'];
                $newRow['max_pay_amt'] = $payhead_item['max_pay_amt'];
                $newRow['en_round_type'] = $payhead_item['en_round_type'];
                $newRow['step_id'] = $payhead_item['step_id'];
                $newRow['en_pay_type'] = $payhead_item['en_pay_type'];
                $newRow['parent_details'] = $payhead_item['parent_details'];
                $newRow['employee_payplan_detail_id'] = $payhead_item['employee_payplan_detail_id'];
                
                $dtprtrandetailtemp->AddRow($newRow);
            } 
            
        }
        
        //Section to insert Notice pay amount in Final Settlement calculation with amount entered by the user at the time of document creation
        if ($drprtran["en_mode_pay_generation"]=="FinalSettlement"){             
          
            if ($drprtran["notice_pay"] > 0){
                $drprtranitem = $dtprtrandetail->NewRow();
                $drprtranitem['employee_id'] = $this->employee_id ;
                $drprtranitem['employee_fullname'] = $this->employee_fullname;
                $drprtranitem['payhead_id'] =  PayrollService::getInstance()->GetNoticePayheadID();
                $drprtranitem['payhead'] = PayrollService::getInstance()->GetNoticePayhead();
                $drprtranitem['payhead_type'] = "E";
                $drprtranitem['monthly_or_onetime'] = 1;
                $drprtranitem['emolument_amt'] =$drprtran["notice_pay"];
                $dtprtrandetail->AddRow($drprtranitem);  
            }
            
        }
    }

    public function GetEffPayplan() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from hr.sp_emp_eff_payplan(:pemp_id, :pfrom_date, :pto_date)');
        $cmm->addParam('pemp_id', $this->employee_id);
        $cmm->addParam('pfrom_date', $this->from_date);
        $cmm->addParam('pto_date', $this->to_date);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function FetchPayHeads($emp_payplan_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select c.payhead_id,d.payhead,d.payhead_type,c.amt, d.incl_in_nopay, c.pay_perc, c.pay_on_perc, c.pay_on_min_amt,
                                    c.pay_on_max_amt, c.min_pay_amt, c.max_pay_amt, c.en_round_type, c.step_id, c.en_pay_type, c.parent_details,
                                    c.employee_payplan_detail_id
                              from hr.employee_payplan a 
                              inner join hr.employee_payplan_detail c on a.employee_payplan_id=c.employee_payplan_id 
                              inner join hr.payhead d on c.payhead_id=d.payhead_id 
                              where a.employee_payplan_id=:pemp_payplan_id and d.monthly_or_onetime=1 order by c.step_id');
        $cmm->addParam('pemp_payplan_id', $emp_payplan_id);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
}
