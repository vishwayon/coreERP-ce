<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenerator {

    public $payrollGenCalculators = null;
    private $dtPayrollTran = null;
    private $dtPayrollTranDetail = null;
    private $dtEffEmployee = null;
    public $company_id = -1;
    public $branch_id = -1;
    public $finyear = '';
    public $en_mode_paygeneration = '';
    public $employee_id = -1;
    public $notice_pay = 0;

    function __construct($company_id, $branch_id, $finyear, $enmodepaygeneration, $employeeid, $noticepay) {

        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->finyear = $finyear;
        $this->en_mode_paygeneration = $enmodepaygeneration;

        //note : employee_id & notice pay is for final settlement;

        $this->employee_id = $employeeid;
        $this->notice_pay = $noticepay;
    }

    public function GeneratePayroll(IPayrollGenWorker $PayGen_doc) {

        // Initialise 
        $this->InitialiseWorker();

        if ($this->en_mode_paygeneration == "Payroll") {

            $this->dtEffEmployee = $this->FetchEffEmployees($PayGen_doc);

            foreach ($this->dtEffEmployee->Rows() as $row) {
                // For each Effective Employee this would be executed
                // Add the result into the BO     

                $drprtran = $PayGen_doc->PayrollTran()->NewRow();
                $drprtran['employee_id'] = $row['employee_id'];
                $drprtran['employee_fullname'] = $row['employee_name'];
                $drprtran['employee_no'] = $row['employee_no'];
                $drprtran['payroll_group_id'] = $PayGen_doc->PayrollGroupId();
                $drprtran['branch_id'] = $this->branch_id;
                $drprtran['company_id'] = $this->company_id;
                $drprtran['finyear'] = $this->finyear;
                $drprtran['pay_from_date'] = $PayGen_doc->PayDateFrom();
                $drprtran['pay_to_date'] = $PayGen_doc->PayDateTo();
                $drprtran['en_mode_pay_generation'] = "Payroll";
                $drprtran["en_resign_type"] = "Terminated";
                $drprtran["notice_pay"] = $this->notice_pay;

                foreach ($this->payrollGenCalculators as $Payroll_item) {
                    $Payroll_item->Initialise($PayGen_doc->PayDateFrom(), $PayGen_doc->PayDateTo(), $PayGen_doc->PayrollGroupId(), $row['employee_id'], $row['employee_name']);
                    $Payroll_item->Docalculation($drprtran, $PayGen_doc->PayrollTranDetail(), $PayGen_doc->PayrollTranLoanDetail(), $PayGen_doc->PayrollTranGratuityDetail(), $PayGen_doc->PayrollTranDetailTemp(), $PayGen_doc->PayrollCustomTran());
                }
                $drprtran['tot_emolument_amt'] = Enumerable::from($PayGen_doc->dtPayrollTranDetail->Rows())->where('$a==>$a["employee_id"]==' . $row['employee_id'] . ' And $a["payhead_type"]!="C"')->sum('$a==>$a["emolument_amt"]');
                $drprtran['tot_deduction_amt'] = round(Enumerable::from($PayGen_doc->dtPayrollTranDetail->Rows())->where('$a==>$a["employee_id"]==' . $row['employee_id'])->sum('$a==>$a["deduction_amt"]'), \app\cwf\vsla\Math::$amtScale);

                $PayGen_doc->PayrollTran()->AddRow($drprtran);
            }
        } else if ($this->en_mode_paygeneration == "FinalSettlement") {
            $dtempdetails = $this->FetchEmployeedetails();

            if (count($dtempdetails->Rows()) > 0) {

                $drprtran = $PayGen_doc->PayrollTran()->NewRow();
                $drprtran['employee_id'] = $this->employee_id;
                $drprtran['employee_fullname'] = $dtempdetails->Rows()[0]['full_employee_name'];
                $drprtran['employee_no'] = $dtempdetails->Rows()[0]['employee_no'];
                $drprtran['payroll_group_id'] = $dtempdetails->Rows()[0]['payroll_group_id'];
                $drprtran['branch_id'] = $this->branch_id;
                $drprtran['company_id'] = $this->company_id;
                $drprtran['finyear'] = $this->finyear;
                $drprtran['pay_from_date'] = $PayGen_doc->PayDateFrom();
                $drprtran['pay_to_date'] = $PayGen_doc->PayDateTo();
                $drprtran['en_mode_pay_generation'] = "FinalSettlement";
                $drprtran['en_resign_type'] = $dtempdetails->Rows()[0]['en_resign_type'];
                $drprtran['notice_pay'] = $this->notice_pay;

                foreach ($this->payrollGenCalculators as $Payroll_item) {
                    $Payroll_item->Initialise($PayGen_doc->PayDateFrom(), $PayGen_doc->PayDateTo(), $dtempdetails->Rows()[0]['payroll_group_id'], $this->employee_id, $dtempdetails->Rows()[0]['full_employee_name']);
                    $Payroll_item->Docalculation($drprtran, $PayGen_doc->PayrollTranDetail(), $PayGen_doc->PayrollTranLoanDetail(), $PayGen_doc->PayrollTranGratuityDetail(), $PayGen_doc->PayrollTranDetailTemp(), $PayGen_doc->PayrollCustomTran());
                }

                $drprtran['tot_emolument_amt'] = round(Enumerable::from($PayGen_doc->dtPayrollTranDetail->Rows())->where('$a==>$a["employee_id"]==' . $this->employee_id)->sum('$a==>$a["emolument_amt"]'), \app\cwf\vsla\Math::$amtScale);
                $drprtran['tot_deduction_amt'] = round(Enumerable::from($PayGen_doc->dtPayrollTranDetail->Rows())->where('$a==>$a["employee_id"]==' . $this->employee_id)->sum('$a==>$a["deduction_amt"]'), \app\cwf\vsla\Math::$amtScale);

                $PayGen_doc->PayrollTran()->AddRow($drprtran);
            }
        }


        $this->dtPayrollTran = $PayGen_doc->PayrollTran();
        $this->dtPayrollTranDetail = $PayGen_doc->PayrollTranDetail();
        $this->dtPayrollTranLoanDetail = $PayGen_doc->PayrollTranLoanDetail();
        $this->dtPayrollTranGratuityDetail = $PayGen_doc->PayrollTranGratuityDetail();
    }

    private function InitialiseWorker() {
        // Initialise Decorators
        $this->payrollGenCalculators = array();
        array_push($this->payrollGenCalculators, new PayrollGenPayhead($this));
        array_push($this->payrollGenCalculators, new PayrollGenLeavedays($this));
        array_push($this->payrollGenCalculators, new PayrollGenEmolument($this));
        array_push($this->payrollGenCalculators, new PayrollGenDeduction($this));
        array_push($this->payrollGenCalculators, new PayrollGenCompanyContribution($this));
        array_push($this->payrollGenCalculators, new PayrollGenOvertime($this));
        array_push($this->payrollGenCalculators, new PayrollGenLoan($this));
        array_push($this->payrollGenCalculators, new PayrollGenGratuity($this));
    }

    private function FetchEffEmployees(IPayrollGenWorker $PayGen_doc) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from hr.sp_eff_employee(:pfrom_date, :pto_date, :ppayroll_group_id) order by sys.fn_sort_vch(employee_no)');
        $cmm->addParam('pfrom_date', $PayGen_doc->PayDateFrom());
        $cmm->addParam('pto_date', $PayGen_doc->PayDateTo());
        $cmm->addParam('ppayroll_group_id', $PayGen_doc->PayrollGroupId());
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function FetchEmployeedetails() {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from hr.employee where employee_id=:pemployee_id');
        $cmm->addParam('pemployee_id', $this->employee_id);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

}
