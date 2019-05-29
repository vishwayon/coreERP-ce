<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration;

/**
 * Description of PayrollGenerationValidator
 *
 * @author valli
 */
class PayrollGenerationValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validatePayrollGenerationEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        $rowIndex = 0;
        foreach ($this->bo->payroll_tran->Rows() as &$ref_tran_row) {
            for ($rowIndex = 0; $rowIndex < count($ref_tran_row['payroll_tran_detail']->Rows()); $rowIndex++) {
                $ref_tran_row['payroll_tran_detail']->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
            }
        }


        if (count($this->bo->payroll_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one employee payroll is required.');
        }

        for ($rowIndex = 0; $rowIndex < count($this->bo->payroll_tran->Rows()); $rowIndex++) {
            $this->bo->payroll_tran->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
        }

        //Payroll Group Selection
        if ($this->bo->payroll_group_id == -1) {
            $this->bo->addBRule('Payroll Group not selected.');
        }

        if ($this->bo->gross_emolument_amt == 0 && $this->bo->gross_deduction_amt == 0) {
            $this->bo->addBRule('Emolument and Deduction both cannot be zero');
        }

        //Pay From Date  validation
        if (strtotime($this->bo->pay_from_date) > strtotime($this->bo->pay_to_date)) {
            $this->bo->addBRule('Pay From Date cannot be greater than Pay To Date');
        }
        
        //Doc Date  validation
        if (strtotime($this->bo->doc_date) < strtotime($this->bo->pay_from_date)) {
            $this->bo->addBRule('Payroll Date cannot be less than Pay From Date');
        }

        //Validation  to check  if payroll already generated for a given date range  
        if ($this->bo->payroll_id == '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from hr.payroll_control Where pay_from_date between :ppay_from_date and :ppay_to_date and payroll_group_id = :ppayroll_group_id');

            $cmm->addParam('ppay_from_date', $this->bo->pay_from_date);
            $cmm->addParam('ppay_to_date', $this->bo->pay_to_date);
            $cmm->addParam('ppayroll_group_id', $this->bo->payroll_group_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Payroll already generated for the period.');
            }
        }

        //Check to validate if Organisational details(Branch Id, Department Id, Sub head Id, Cost head Id) associated with each employee part of the payroll
        //Check to validate if employees deduction(excluding loan repayment) exceeds emoluments 

        $str_emp_list = '';
        foreach ($this->bo->payroll_tran->Rows() as $row) {
            if (($row['tot_emolument_amt'] - $row['tot_deduction_amt']) < 0) {
                if ($str_emp_list == '') {
                    $str_emp_list = $row['employee_fullname'];
                } else {
                    $str_emp_list = $str_emp_list . ',' . $row['employee_fullname'];
                }
            }
        }

        if ($str_emp_list <> '') {
            $this->bo->addBRule('List of Employees for whom total Deduction amount exceeds Total Emolument amount are ' . $str_emp_list);
        }

        // Set Amt In Words   

        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        foreach ($this->bo->payroll_tran->Rows() as &$payroll_tran_row) {

            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $payroll_tran_row['net_amt']);
            $payroll_tran_row['amt_in_words'] = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }
    }

    public function validateBeforeDelete() {
        // conduct default form validations
        parent::validateBeforeDelete();

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select max(pay_from_date) as max_pay_from_date from hr.payroll_control Where payroll_group_id = :ppayroll_group_id");
        $cmm->addParam('ppayroll_group_id', $this->bo->payroll_group_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($this->bo->pay_from_date < $result->Rows()[0]['max_pay_from_date']) {
                $this->bo->addBRule('Subsequent Payroll exists. So cannot delete current Payroll. Please Delete all the succeeding Payrolls to proceed with this Deletion.');
            }
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select count(*) as rec_count from hr.payroll_control  a  inner join hr.payroll_tran  b on a.payroll_id = b.payroll_id where a.payroll_id = :ppayroll_id and b.voucher_ID <>''");
        $cmm->addParam('ppayroll_id', $this->bo->payroll_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['rec_count'] > 0) {
                $this->bo->addBRule('Cannot delete as Payment is already generated for the Payroll.');
            }
        }
    }

    public function validateBeforeUnpost() {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select count(*) as rec_count from hr.payroll_control  a  inner join hr.payroll_tran  b on a.payroll_id = b.payroll_id where a.payroll_id = :ppayroll_id and b.voucher_ID <>''");
        $cmm->addParam('ppayroll_id', $this->bo->payroll_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['rec_count'] > 0) {
                $this->bo->addBRule('Cannot Unpost as Payment is already generated for the Payroll.');
            }
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

}
