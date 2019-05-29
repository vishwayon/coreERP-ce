<?php

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

class PayrollGenLoan extends PayrollGenBase {

    public function __construct($parent_worker) {
        parent::__construct($parent_worker, PayrollGenBase::NOT_APPLICABLE);
    }

    public function Docalculation(&$drprtran, $dtprtrandetail, $dtprtranloandetail, $dtprtrangratuitydetail, $dtprtrandetailtemp, $dtpayrollcustomtran) {
        $loan_amount = 0;
        $installment_principal = 0;
        $installment_interest = 0;

        $drprtranitem = $dtprtrandetail->NewRow();
        $drprtranitem['employee_id'] = $this->employee_id;
        $drprtranitem['employee_fullname'] = $this->employee_fullname;
        $drprtranitem['payhead_id'] = PayrollService::getInstance()->GetLoanPayheadID();
        $drprtranitem['payhead'] = PayrollService::getInstance()->GetLoanPayhead();
        $drprtranitem['payhead_type'] = "D";
        $drprtranitem['monthly_or_onetime'] = 1;

        if ($drprtran["en_mode_pay_generation"] == "FinalSettlement") {

            $drprtranitem['deduction_amt'] = $this->GetPendingLoanAmountForSettlement($this->employee_id, $this->from_date);
        } else if ($drprtran["en_mode_pay_generation"] == "Payroll") {

            //Get Loan amount 
            $dt_loan_detail = $this->GetLoandetail($this->employee_id, $this->from_date, $this->to_date);

            If (count($dt_loan_detail->Rows()) > 0) {

                $installment_principal = round(Enumerable::from($dt_loan_detail->Rows())->sum('$a==>$a["installment_principal"]'), \app\cwf\vsla\Math::$amtScale);
                $installment_interest = round(Enumerable::from($dt_loan_detail->Rows())->sum('$a==>$a["installment_interest"]'), \app\cwf\vsla\Math::$amtScale);
                $loan_amount = $installment_principal + $installment_interest;

                $drprtranitem['deduction_amt'] = $loan_amount;

                $sl_no = 0;

                //Fill Loan Repayments Details
                foreach ($dt_loan_detail->Rows() as $drloan_item) {

                    $sl_no = $sl_no + 1;
                    $drloan = $dtprtranloandetail->NewRow();
                    $drloan['sl_no'] = $sl_no;
                    $drloan['employee_id'] = $this->employee_id;
                    $drloan['employee_fullname'] = $this->employee_fullname;
                    $drloan['payhead_id'] = PayrollService::getInstance()->GetLoanPayheadID();
                    $drloan['payhead'] = PayrollService::getInstance()->GetLoanPayhead($drloan['payhead_id']);
                    $drloan['loan_id'] = $drloan_item['loan_id'];
                    $drloan['installment_principal'] = $drloan_item['installment_principal'];
                    $drloan['installment_interest'] = $drloan_item['installment_interest'];
                    $drloan['installment_amount'] = $drloan_item['installment_principal'] + $drloan_item['installment_interest'];
                    $dtprtranloandetail->AddRow($drloan);
                }
            }
        }

        $dtprtrandetail->AddRow($drprtranitem);
    }

    public function GetLoandetail($emp_id, $from_date, $to_date) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.loan_id, COALESCE(sum(a.installment_principal)) installment_principal,
                              COALESCE(sum(a.installment_interest)) installment_interest from hr.loan_tran a inner join hr.loan_control b on a.loan_id = b.loan_id
                              Where a.employee_id = :pemp_id and a.installment_date between :pfrom_date and :pto_date 
                                    and b.status = 5
                              group by a.loan_id");
        $cmm->addParam('pemp_id', $emp_id);
        $cmm->addParam('pfrom_date', $from_date);
        $cmm->addParam('pto_date', $to_date);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        return $dt;
    }

    public function GetPendingLoanAmountForSettlement($emp_id, $from_date) {

        $loan_settlement_amt = 0;

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select COALESCE(sum(a.installment_principal)) as loanamt from hr.loan_tran a inner join hr.loan_control b on a.loan_id = b.loan_id 
                              where a.employee_id=:pemp_id and a.installment_date >= :pfrom_date and b.status = 5");
        $cmm->addParam('pemp_id', $emp_id);
        $cmm->addParam('pfrom_date', $from_date);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt->Rows()) > 0) {
            $loan_settlement_amt = $dt->Rows()[0]['loanamt'];
        }

        return $loan_settlement_amt;
    }

}
