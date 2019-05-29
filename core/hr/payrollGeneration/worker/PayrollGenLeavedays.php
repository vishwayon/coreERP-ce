<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenLeavedays extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }

    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran) {
        if (count($dtprtrandetailtemp->Rows()) > 0) {

            foreach ($dtprtrandetailtemp->Rows() as &$dritem) {
                if ($dritem['employee_id'] == $this->employee_id) {
                    $paytodate = new \DateTime($dritem['calc_eff_to_date']);
                    $payfromdate = new \DateTime($dritem['calc_eff_from_date']);
                    $pay_days = $paytodate->diff($payfromdate);

                    $todate = new \DateTime($this->to_date);
                    $fromdate = new \DateTime($this->from_date);
                    $total_days = $todate->diff($fromdate);
                    // Check days in month from settings if eff to date and eff from date are same as payroll from date and to date
//                    if (strtotime($this->from_date) == strtotime($dritem['calc_eff_from_date']) && strtotime($this->to_date) == strtotime($dritem['calc_eff_to_date'])) {
                    $days_in_month = \app\core\hr\payrollGeneration\worker\PayrollService::getInstance()->GetDaysInMonth();
//                    }
                    if ($days_in_month != 0) {
                        $dritem['total_days'] = $days_in_month;
                    } else {
                        $dritem['total_days'] = $total_days->days + 1;
                    }
//                    if (($total_days->days + 1) < $days_in_month) {
//                        $dritem['total_days'] = $total_days->days + 1;
//                    } else {
//                        $dritem['total_days'] = $days_in_month;
//                    }
                    $dritem['no_pay_days'] = $this->GetNoofLeavedays($this->employee_id, $dritem['calc_eff_from_date'], $dritem['calc_eff_to_date']);

                    // If employee is on leave for entire month set total days to no pay days
                    if ($dritem['no_pay_days'] > $dritem['total_days']) {
                        $dritem['total_days'] = $dritem['no_pay_days'];
                    }
                    if (strtotime($this->from_date) == strtotime($dritem['calc_eff_from_date']) && strtotime($this->to_date) == strtotime($dritem['calc_eff_to_date'])) {
                        $dritem['pay_days'] = $days_in_month - $dritem['no_pay_days'];
                    } else {
                        $dritem['pay_days'] = $pay_days->days + 1 - $dritem['no_pay_days'];
                    }
                }
            }

            $nopaydays = 0;
            $paydays = 0;
            $totaldays = 0;

            $month = date("m", strtotime($this->from_date));
            $year = date("Y", strtotime($this->from_date));
            $totaldays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $days_in_month = \app\core\hr\payrollGeneration\worker\PayrollService::getInstance()->GetDaysInMonth();
            if ($days_in_month != 0) {
                $totaldays = $days_in_month;
            }

            //For an employee get distinct employeepayplan, nopaydays, paydays
            $lst = Enumerable::from($dtprtrandetailtemp->Rows())->where('$a==>$a["employee_id"] == ' . $this->employee_id)->distinct('$a==>$a["emp_payplan_id"]')->toList();

            foreach ($lst as $lstitem) {
                $nopaydays = $nopaydays + $lstitem['no_pay_days'];
                $paydays = $paydays + $lstitem['pay_days'];
            }

            if ($nopaydays > $totaldays) {
                $totaldays = $nopaydays;
            }

            $drprtran['no_pay_days'] = $nopaydays;
            $drprtran['pay_days'] = $paydays;
            $drprtran['total_days'] = $totaldays;
        }
    }

    public function GetNoofLeavedays($emp_id, $from_date, $to_date) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select COALESCE(sum(no_pay_days),0) no_pay_days from (select a.leave_type_id, b.paid_leave,  
            ((case when a.to_date > :pto_date then :pto_date else a.To_Date end) -
            (case when a.from_date < :pfrom_date then :pfrom_date else a.from_date end )) + 1  no_pay_days 
            from   hr.leave  a inner join hr.leave_type b  on a.leave_type_ID = b.leave_type_id 
             Where a.employee_id = :pemp_id and a.from_date <= :pto_date and a.to_Date >= :pfrom_date) a where paid_leave=false");
        $cmm->addParam('pemp_id', $emp_id);
        $cmm->addParam('pfrom_date', $from_date);
        $cmm->addParam('pto_date', $to_date);
        $nopaydays = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($nopaydays->Rows()) > 0) {
            return $nopaydays->Rows()[0]['no_pay_days'];
        } else {
            return 0;
        }
    }
}
