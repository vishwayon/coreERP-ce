<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration\worker;

interface IPayrollGenWorker {

    public function PayDateFrom();

    public function PayDateTo();

    public function PayrollGroupId();

    public function PayrollTran();

    public function PayrollTranDetail();

    public function PayrollTranLoanDetail();
    
    public function PayrollTranGratuityDetail();
        
    public function PayrollTranDetailTemp();

    public function PayrollCustomTran();
}
