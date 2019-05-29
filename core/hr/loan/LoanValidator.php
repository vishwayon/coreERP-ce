<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\loan;

use YaLinqo\Enumerable;

/**
 * Description of loan
 *
 * @author valli
 */
class LoanValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateLoanEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        for ($rowIndex = 0; $rowIndex < count($this->bo->loan_tran->Rows()); $rowIndex++) {
            $this->bo->loan_tran->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
        }
        if ($this->bo->employee_id == -1) {
            $this->bo->addBRule('Employee not selected.');
        }

        if ($this->bo->interest_percentage > 100) {
            $this->bo->addBRule('Interest Percentage cannot be greater than 100');
        }

        if ($this->bo->no_of_installments <= 0) {
            $this->bo->addBRule('No of Installments should be greater than zero');
        }

        if ($this->bo->no_of_installments != count($this->bo->loan_tran->Rows())) {
            $diff = $this->bo->no_of_installments - count($this->bo->loan_tran->Rows());
            if ($diff >= 0) {
                $this->bo->addBRule('Installment Principal and Installment Interest has to be entered for ' . $diff . ' Installments.');
            } else {
                $this->bo->addBRule('Extra ' . abs($diff) . " Installment Principal and Installment Interest has entered. Remove extra Installment Principal and Installment Interest.");
            }
        }

//        $tot_principal = round(Enumerable::from($this->bo->loan_tran->Rows())->sum('$a==>$a["installment_principal"]'), \app\cwf\vsla\Math::$amtScale);
//        $tot_interest = round(Enumerable::from($this->bo->loan_tran->Rows())->sum('$a==>$a["installment_interest"]'), \app\cwf\vsla\Math::$amtScale);
//
//        if ($this->bo->loan_principal <> $tot_principal) {
//            $this->bo->addBRule('Installment Principal total does not match with installment principal details in grid.');
//        }
//
//        if ($this->bo->loan_interest <> $tot_interest) {
//            $this->bo->addBRule('Installment Interest total does not match with installment interest details in grid.');
//        }


        if (count($this->bo->loan_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one loan record is required.');
        }

        /* Check Installments Principal/Installments Interest should not be less than or equal to zero */
        $i = 0;
        $tran_len =count($this->bo->loan_tran->Rows());
        foreach ($this->bo->loan_tran->Rows() as $row) {
            $i = $i + 1;
            if ($row['installment_principal'] <= 0) {
                $this->bo->addBRule('Invalid Installment Principal - Row No.' . $i);
            }
            if ($row['installment_interest'] < 0) {
                $this->bo->addBRule('Invalid Installment Interest - Row No.' . $i);
            }
            if($i == $tran_len){
                $this->bo->loan_to_date = $row['installment_date'];
            }
        }

        $dt_payroll_date = \app\core\hr\payroll\worker\PayrollWorker::GetPayrollMaxFromToDate($this->bo->employee_id);

        if ($dt_payroll_date !== null) {
            if (count($this->bo->loan_tran->Rows()) > 0) {
                if (count($dt_payroll_date->Rows()) > 0) {
                    if ($dt_payroll_date->Rows()[0]['payroll_to_date'] != NULL) {

                        if ($this->bo->loan_id == '' || ($this->bo->loan_id != '' && strtotime($this->bo->loan_from_date) != strtotime($this->bo->original_loan_from_date))) {
                            if ($this->bo->loan_from_date <= $dt_payroll_date->Rows()[0]['payroll_to_date']) {
                                $this->bo->addBRule('Payroll already exists upto ' . $dt_payroll_date->Rows()[0]['payroll_to_date'] . ' - Invalid Loan from date.');
                            }
                        }
                    }
                }
            }
        } else {
            $this->bo->addBRule('Single payroll not generated for this employee, loan cannot be saved.');
        }

        $i = 0;
        foreach ($this->bo->loan_tran->Rows() as $row) {

            $i = $i + 1;

            if (strtotime($row['installment_date']) < strtotime($this->bo->loan_from_date)) {
                $this->bo->addBRule('Installment Date should be greater than or equal to Installment Start Date. Row No:' . $i);
            }

            if ($dt_payroll_date !== null) {
                if (count($dt_payroll_date->Rows()) > 0 && $dt_payroll_date->Rows()[0]['payroll_to_date'] != NULL) {

                    if ($row['loan_repaid'] = false && strtotime($row['installment_date']) > strtotime($dt_payroll_date->Rows()[0]['payroll_to_date'])) {
                        $this->bo->addBRule('Installment Date cannot less than the last payroll date' . $i);
                    }
                }
            }
        }

        if ($this->bo->loan_id != '') {
            if ($dt_payroll_date !== null) {
                if (count($dt_payroll_date->Rows()) > 0) {
                    if ($dt_payroll_date->Rows()[0]['payroll_to_date'] != NULL) {

                        //No of Installment repaid         
                        $cmm = new \app\cwf\vsla\data\SqlCommand();
                        $cmm->setCommandText('select count(*) as no_installment_repaid from hr.loan_repayment where employee_id=:pemployee_id and loan_id=:ploan_id');
                        $cmm->addParam('pemployee_id', $this->bo->employee_id);
                        $cmm->addParam('ploan_id', $this->bo->loan_id);
                        $dt_loan_repayment_detail = \app\cwf\vsla\data\DataConnect::getData($cmm);

                        if (count($dt_loan_repayment_detail->Rows()) > 0) {
                            $no_installment_repaid = $dt_loan_repayment_detail->Rows()[0]['no_installment_repaid'];
                        }
                        if ($no_installment_repaid > 0) {
                            if ($this->bo->original_no_of_installments > $no_installment_repaid) {
                                if ($this->bo->no_of_installments < $no_installment_repaid) {
                                    $this->bo->addBRule($no_installment_repaid . ' installment of loan has been already been paid.  You cannot decrease beyond it. But You can increase it.');
                                }
                            } else {
                                $this->bo->addBRule('Loan has been already repaid. Now you cannot change loan details.');
                            }
                            if (strtotime($this->bo->loan_from_date) != strtotime($this->bo->original_loan_from_date)) {
                                $this->bo->addBRule('Payrolls already generated after loan date. Now cannot change loan from date, only no of installments can be modified');
                            }
                        }
                    }
                }
            }
        }
    }

    public function validateLoanEditFormBeforeDelete() {
        // conduct default form validations
        $this->validateBeforeDelete($this->bo);
    }

    public function validateBeforeDelete() {

        if ($this->bo->loan_id != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select count(*) as count from hr.loan_repayment Where loan_id = :ploan_id");
            $cmm->addParam('ploan_id', $this->bo->loan_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                if ($result->Rows()[0]['count'] > 0) {
                    $this->bo->addBRule('Loan document cannot be deleted since ' . $result->Rows()[0]['count'] . ' installments for the Loan are already paid.');
                }
            }
        }
    }

    public function validateBeforePost() {
        
    }

    public function validateBeforeUnPost() {
        // Validate Loan Repayment
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select count(*) as count from hr.loan_repayment Where loan_id = :ploan_id");
        $cmm->addParam('ploan_id', $this->bo->loan_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['count'] > 0) {
                $this->bo->addBRule('Loan document cannot be Unpost since ' . $result->Rows()[0]['count'] . ' installments for the Loan are already paid.');
            }
        }
    }
}
