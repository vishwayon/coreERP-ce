<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration\worker;

abstract class PayrollGenBase {

    const NOT_APPLICABLE = 0;

    protected $parent_worker = null;
    protected $from_date, $to_date;
    protected $employee_id = -1;
    protected $payroll_group_id = -1;
    protected $employee_fullname = null;
    protected $pay_method = self::NOT_APPLICABLE;

    public function __construct($parent_worker, $payroll_method) {
        $this->parent_worker = $parent_worker;
        $this->pay_method = $payroll_method;
    }

    public function Initialise($fromdate, $todate, $payrollgroupid, $empid, $empname) {
        $this->from_date = $fromdate;
        $this->to_date = $todate;
        $this->payroll_group_id = $payrollgroupid;
        $this->employee_id = $empid;
        $this->employee_fullname = $empname;
    }
    
    protected function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran)
    {}
}