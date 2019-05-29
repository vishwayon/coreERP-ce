<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration\worker;

class PayrollService {

    private static $instance = null;
    private $dtPayHead = null;
    private $dtEmolumentHead = null;
    private $dtDeductionHead = null;
    private $dtPayrollGroup = null;
    private $overtime_payhead_id = -1;
    private $overtime_payhead = '';
    private $loan_payhead_id = -1;
    private $loan_payhead = '';
    private $noticepay_payhead_id = -1;
    private $noticepay_payhead = '';
    private $days_in_month = 0;

    private function __construct() {
        $this->dtPayHead = $this->InitialisePayHead();
        $this->dtEmolumentHead = $this->InitialiseEmolumentPayHead();
        $this->dtDeductionHead = $this->InitialiseDeductionPayHead();
        $this->dtCompanyContriHead = $this->InitialiseCompanyContriPayHead();
        $this->dtPayrollGroup = $this->InitialisePayrollGroup();
        $this->InitiliseOvertimePayhead();
        $this->InitiliseLoanPayhead();
        $this->InitiliseNoticepayPayhead();
        $this->SetDaysInMonth();
    }

    public static function createInstance() {
        PayrollService::$instance = new PayrollService();
    }

    public static function getInstance() {
        if (PayrollService::$instance == null) {
            PayrollService::$instance = new PayrollService();
        }
        return PayrollService::$instance;
    }

    public function PayHead() {
        return $this->dtPayHead;
    }

    public function EmolumentHead() {
        return $this->dtEmolumentHead;
    }

    public function DeductionHead() {
        return $this->dtDeductionHead;
    }

    public function CompanyContriHead() {
        return $this->dtCompanyContriHead;
    }

    public function GetOvertimePayheadID() {
        return $this->overtime_payhead_id;
    }

    public function GetOvertimePayhead() {
        return $this->overtime_payhead;
    }

    public function GetLoanPayheadID() {
        return $this->loan_payhead_id;
    }

    public function GetLoanPayhead() {
        return $this->loan_payhead;
    }

    public function GetNoticePayheadID() {
        return $this->noticepay_payhead_id;
    }

    public function GetNoticePayhead() {
        return $this->noticepay_payhead;
    }

    public function GetDaysInMonth() {
        return $this->days_in_month;
    }

    public function Payroll_Group_Include_OT($payrollgroup_id) {
        if (count($this->dtPayrollGroup->Rows()) > 0) {
            $dr;
            $count = 0;
            foreach ($this->dtPayrollGroup->Rows() as $row) {
                if ($row['payroll_group_id'] == $payrollgroup_id) {
                    $count = $count + 1;
                    if ($count == 1) {
                        $dr = $row;
                        break;
                    }
                }
            }

            if ($count == 1) {
                return $dr['overtime_applicable'];
            }
        }
        return 0;
    }

    private function SetDaysInMonth() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select value from sys.settings where key = 'hr_days_in_month'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->days_in_month = (int) $dt->Rows()[0]['value'];
        }
    }

    private function InitialisePayHead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead_type, payhead from hr.payhead");
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function InitialiseEmolumentPayHead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead_type, payhead from hr.payhead where payhead_type='E'");
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function InitialiseDeductionPayHead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead_type, payhead from hr.payhead where payhead_type='D'");
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function InitialiseCompanyContriPayHead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead_type, payhead from hr.payhead where payhead_type='C'");
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    private function InitiliseOvertimePayhead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead from hr.payhead where payhead_type='O'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->overtime_payhead_id = $dt->Rows()[0]['payhead_id'];
            $this->overtime_payhead = $dt->Rows()[0]['payhead'];
        }
    }

    private function InitialisePayrollGroup() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from hr.payroll_group');
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    private function InitiliseLoanPayhead() {
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText("select value from sys.settings where key='hr loan recovery payhead'");
//        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        if (count($dt->Rows()) > 0) {
//            $this->loan_payhead_id = $dt->Rows()[0]['value'];
//        }
//
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText("select payhead from hr.payhead where payhead_id=:ploan_payhead_id");
//        $cmm->addParam('ploan_payhead_id', $this->loan_payhead_id);
//        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        if (count($dt->Rows()) > 0) {
//            $this->loan_payhead = $dt->Rows()[0]['payhead'];
//        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead_id, payhead from hr.payhead where payhead_type='L'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->loan_payhead_id = $dt->Rows()[0]['payhead_id'];
            $this->loan_payhead = $dt->Rows()[0]['payhead'];
        }
    }

    private function InitiliseNoticepayPayhead() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select value from sys.settings where key='hr_notice_pay_payhead'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->noticepay_payhead_id = $dt->Rows()[0]['value'];
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select payhead from hr.payhead where payhead_id=:pnoticepay_payhead_id");
        $cmm->addParam('pnoticepay_payhead_id', $this->noticepay_payhead_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->noticepay_payhead = $dt->Rows()[0]['payhead'];
        }
    }

}
