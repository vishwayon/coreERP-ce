<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\loan;

/**
 * Description of LoanEventHandler
 *
 * @author Valli
 */
class LoanEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->loan_id == "" or $this->bo->loan_id == "-1") {
            $this->bo->loan_id = "";
            $this->bo->no_of_installments = 0;
            $this->bo->status = 0;
            $this->bo->employee_id = $criteriaparam['formData']['SelectEmployee']['employee_id'];
            $this->bo->en_calculate_by = 1;
        } else {
            $this->bo->original_loan_from_date = $this->bo->loan_from_date;
            $this->bo->original_no_of_installments = $this->bo->no_of_installments;
            $this->bo->original_interest_percentage = $this->bo->interest_percentage;
            $this->bo->original_installment_principal = $this->bo->installment_principal;
            $this->bo->original_installment_interest = $this->bo->installment_interest;

            $dt_payroll_date = \app\core\hr\payroll\worker\PayrollWorker::GetPayrollMaxFromToDate($this->bo->employee_id);
            if ($dt_payroll_date != null) {
                if (count($dt_payroll_date->Rows()) > 0) {
                    foreach ($this->bo->loan_tran->Rows() as &$row) {
                        if ($dt_payroll_date->Rows()[0]['payroll_to_date'] >= $row['installment_date']) {
                            $row['loan_repaid'] = true;
                            $this->bo->loan_repaid = true;
                        } else {
                            $row['loan_repaid'] = false;
                        }
                    }
                } else {
                    $row['loan_repaid'] = false;
                }
            } else {
                $row['loan_repaid'] = false;
            }

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from hr.loan_repayment where loan_id=:ploan_id');
            $cmm->addParam('ploan_id', $this->bo->loan_id);
            $dt_loan_repayment_detail = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($dt_loan_repayment_detail->Rows()) > 0) {
                foreach ($this->bo->loan_tran->Rows() as &$loan_tran_row) {
                    foreach ($dt_loan_repayment_detail->Rows() as $row) {
                        if ($loan_tran_row['loan_tran_id'] == $row['loan_tran_id']) {
                            $loan_tran_row['payroll_id'] = $row['payroll_id'];
                        }
                    }
                }
            }
            
            // Set Loan recovery starts from date 
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select min(installment_date) as installment_date from hr.loan_tran where loan_id=:ploan_id');
            $cmm->addParam('ploan_id', $this->bo->loan_id);
            $dt_loan_detail = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($dt_loan_detail->Rows()) > 0) {
                $this->bo->loan_recovery_from = $dt_loan_detail->Rows()[0]['installment_date'];                
            }
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);

        foreach ($this->bo->loan_tran->Rows() as &$loan_tran_row) {
            $loan_tran_row['employee_id'] = $this->bo->employee_id;
        }
        
        $to_date = strtotime('+ ' . ($this->bo->no_of_installments). ' month', strtotime($this->bo->loan_from_date));
//        $this->bo->loan_to_date = date("Y-m-d", $to_date);
    }

    public function afterSave($cn) {
        parent::afterSave($cn);

        $this->bo->original_loan_from_date = $this->bo->loan_from_date;
        $this->bo->original_no_of_installments = $this->bo->no_of_installments;
        $this->bo->original_interest_percentage = $this->bo->interest_percentage;
        $this->bo->original_installment_principal = $this->bo->installment_principal;
        $this->bo->original_installment_interest = $this->bo->installment_interest;
    }

}
