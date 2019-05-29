<?php

namespace app\core\hr\gratuity\worker;

use YaLinqo\Enumerable;

class GratuityCalculator {

    private $gratuity_from_date;
    private $gratuity_to_date;
    private $dtgratuity_formula;
    private $emo_amt_monthly = 0;

    /** @var \app\cwf\vsla\data\DataTable */
    private $gratuity_tran;

    public function __construct($employee_id, $gratuity_from_date, $gratuity_to_date) {

        $this->employee_id = $employee_id;
        $this->gratuity_from_date = $gratuity_from_date;
        $this->gratuity_to_date = $gratuity_to_date;
        $this->initGratuityTran();
    }

    private function initGratuityTran() {
        // Initialise gratuity_tran
        $dt = new \app\cwf\vsla\data\DataTable();
        $dt->addColumn('sl_no', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $dt->addColumn('slab_from', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $dt->addColumn('slab_from_original', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $dt->addColumn('slab_to', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $dt->addColumn('slab_to_original', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $dt->addColumn('slab_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('gratuity_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('amount', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $dt->addColumn('unpaid_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->gratuity_tran = $dt;
    }

    public function calc_gratuity() {
        $total_work_days = 0;
        $emo_amt_monthly_gratuity = 0;
        $un_paid_days = 0;

//        $todate=new \DateTime($this->gratuity_to_date);
//        $fromdate=new \DateTime($this->gratuity_from_date);
        $emp_workday_detail = GratuityWorker::GetContinousServiceYearforGratuity($this->employee_id, $this->gratuity_from_date, $this->gratuity_to_date);
        $total_work_days = $emp_workday_detail['total_days']->days - $emp_workday_detail['days_absent'];

        $slab_from = $this->gratuity_from_date;
        $slab_from_original = $this->gratuity_from_date;

        $dtEffectivePayPlan = GratuityWorker::GetEffEmployeePayPlan($this->employee_id, $this->gratuity_to_date);
        $this->dtgratuity_formula = $this->get_gratuity_formula();
        $drformula;

        if (count($dtEffectivePayPlan->Rows()) > 0) {
            if (count($this->dtgratuity_formula->Rows()) > 0) {
                $i = 0;

                $emo_amt_monthly_gratuity = $this->get_emo_amt_monthly_for_gratuity($dtEffectivePayPlan->Rows()[0]['employee_payplan_id']);

                while ($total_work_days > 0) {

                    $i = $i + 1;
                    if ($i > count($dtEffectivePayPlan->Rows())) {
                        //get the last row for remaining entries
                        //'Use Last formula slab for remaining years of service
                        $drformula = $this->dtgratuity_formula->Rows()[count($dtEffectivePayPlan->Rows()) - 1];
                    } else {
                        $drformula = $this->dtgratuity_formula->Rows()[$i - 1];
                    }

                    //                $slab_to_original = strtotime("+" . $drformula['days_worked'] . " day",  strtotime($slab_from_original)); 

                    $temp_slab_to_original = strtotime("+" . $drformula['days_worked'] . "days", strtotime($slab_from_original));
                    $slab_to_original = date("Y-m-d", $temp_slab_to_original);

                    if ($slab_to_original > $this->gratuity_to_date) {
                        // This ensures that leave is fetched only upto the To Date As mentioned in Report
                        $slab_to_original = $this->gratuity_to_date;
                    }

                    $un_paid_days = GratuityWorker::GetDaysAbsentWithoutPayGratuity($this->employee_id, $slab_from_original, $slab_to_original);

                    $temp_slab_to = strtotime("+" . intval($drformula['days_worked'] + $un_paid_days) . "days", strtotime($slab_from));
                    $slab_to = date("Y-m-d", $temp_slab_to);

                    $drgratuity_tran = $this->gratuity_tran->NewRow();
                    $drgratuity_tran["sl_no"] = $i;
                    $drgratuity_tran["slab_from"] = $slab_from;
                    $drgratuity_tran["slab_from_original"] = $slab_from_original;
                    $drgratuity_tran["unpaid_days"] = $un_paid_days;

                    if ($this->gratuity_to_date <= $slab_to) {
                        $slab_to = $this->gratuity_to_date;
                    }
                    if ($this->gratuity_to_date <= $slab_to_original) {
                        $slab_to_original = $this->gratuity_to_date;
                    }

                    $drgratuity_tran["slab_to"] = $slab_to;
                    $drgratuity_tran["slab_to_original"] = $slab_to_original;

                    $todate = new \DateTime($slab_to);
                    $fromdate = new \DateTime($slab_from);
                    $no_of_days = $todate->diff($fromdate);

                    $drgratuity_tran["slab_days"] = ($no_of_days->days) - $un_paid_days;

                    $drgratuity_tran["gratuity_days"] = intval($drgratuity_tran["slab_days"] / $drformula['days_worked'] * $drformula['gratuity_days']);

                    $drgratuity_tran["amount"] = $emo_amt_monthly_gratuity / 30 * $drgratuity_tran["gratuity_days"];

                    $slab_from = $slab_to;
                    $slab_from_original = $slab_to_original;
                    $total_work_days = $total_work_days - $drformula['days_worked'];

                    $this->gratuity_tran->addrow($drgratuity_tran);
                }
            }
        }

        return $this->gratuity_tran;
    }

    private function get_gratuity_formula() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select * from  hr.gratuity_formula";
        $cmm->setCommandText($sql);

        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    private function get_emo_amt_monthly_for_gratuity($employee_payplan_id) {

        $amt = 0;

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select coalesce(sum(amt),0) as amt_monthly from hr.employee_payplan_detail a
                inner join hr.payhead b on a.payhead_id=b.payhead_id where employee_payplan_id=:pemployee_payplan_id
                and b.payhead_type='E' and b.Incl_In_Gratuity='t'";
        $cmm->setCommandText($sql);
        $cmm->addParam('pemployee_payplan_id', $employee_payplan_id);

        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt->Rows()) > 0) {
            $amt = $dt->Rows()[0]['amt_monthly'];
        }

        return $amt;
    }

}
