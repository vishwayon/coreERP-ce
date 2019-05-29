<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenCompanyContribution extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }

    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran) {
        if (count($dtprtrandetailtemp->Rows()) > 0) {
            foreach ($dtprtrandetailtemp->Rows() as &$drtrandetailitem) {
                if ($drtrandetailitem['employee_id'] == $this->employee_id && $drtrandetailitem['payhead_type'] == 'C') {                    
                    if ($drtrandetailitem['en_pay_type'] == 3) {// Get user entered amount if pay type is Prompt on Payroll Generation
                        $drtrandetailitem['emolument_amt'] = PayrollGenHelper::GetPromtAmt($drtrandetailitem, $dtpayrollcustomtran);
                    }
                    else{
                        // Calculate the base amount
                        $drtrandetailitem['emolument_amt'] = PayrollGenHelper::CalculatePayAmt($dtprtrandetailtemp, $drtrandetailitem);
                    }
                }
            }
            
            $testgrp = Enumerable::from($dtprtrandetailtemp->Rows())->where('$a==>$a["employee_id"]==' . $this->employee_id . '&& $a["payhead_type"]=="C"')->groupBy('$a==>$a["payhead_id"]')->toArray();        
            foreach($testgrp as &$item) {
                $drprtranitem = $dtprtrandetail->NewRow();
                $drprtranitem['employee_id'] = $this->employee_id;
                $drprtranitem['employee_fullname'] = $this->employee_fullname;
                $drprtranitem['payhead_id'] = $item[0]['payhead_id'];
                $drprtranitem['payhead'] = $item[0]['payhead'];
                $drprtranitem['payhead_type'] = "C";
                $drprtranitem['monthly_or_onetime'] = 1;

                $paydays = round(Enumerable::from($item)->sum('$a==>$a["pay_days"]'), \app\cwf\vsla\Math::$amtScale);
                $nodays = round(Enumerable::from($item)->sum('$a==>$a["no_pay_days"]'), \app\cwf\vsla\Math::$amtScale);
                $emolument_amt = round(Enumerable::from($item)->sum('$a==>$a["emolument_amt"]'), \app\cwf\vsla\Math::$amtScale);

                $drprtranitem['pay_days'] = $paydays;
                $drprtranitem['no_pay_days'] = $nodays;
                $drprtranitem['emolument_amt'] = $emolument_amt;

                $dtprtrandetail->AddRow($drprtranitem);
            }
        }
    }
}
