<?php

namespace app\core\hr\controllers;

use app\cwf\vsla\base\WebFormController;
use YaLinqo\Enumerable;

class FormController extends WebFormController {

    public function actionGeneratepayroll() {
        $payDateFrom = \yii::$app->request->post('payDateFrom');
        $payDateTo = \yii::$app->request->post('payDateTo');
        $payrollGroupId = \yii::$app->request->post('payrollGroupId');
        $dtPayrollCustomTran = \yii::$app->request->post('dtPayrollCustomTran');
        $dtPayrollCustomTranTemp = json_decode($dtPayrollCustomTran, true);
        // Build PayrollGeneration BO
        $inparam = array();

        // Create instance of  PayrollGeneration BO
        $bopath = '../core/hr/payrollGeneration/PayrollGeneration.xml';
        $bo = new \app\cwf\vsla\xmlbo\XboBuilder($bopath);
        $boInst = $bo->buildBO($inparam);
        
        $classinst= new \app\core\hr\payrollGeneration\worker\PayrollGenTemp($payDateFrom, $payDateTo, $payrollGroupId, $boInst->payroll_tran, $dtPayrollCustomTranTemp);
        
        $paygenWorker= new \app\core\hr\payrollGeneration\worker\PayrollGenerator(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'),"Payroll", -1, 0);
        $paygenWorker->GeneratePayroll($classinst); 

        $result = array();

        $result['payroll_tran_loan_detail'] = $classinst->PayrollTranLoanDetail();
        $result['payroll_tran_detail'] = $classinst->PayrollTranDetail();
        $result['payroll_tran'] = $classinst->PayrollTran();

        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionCalculateovertime($attendancedate, $inhrs, $inmins, $outhrs, $outmins) {
        $result = \app\core\hr\attendance\AttendanceWorker::CalculateOvertime($attendancedate, $inhrs, $inmins, $outhrs, $outmins);
        return json_encode($result);
    }

    public function actionGetpayheaddetail($payhead_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select payhead_id, payhead, payhead_type, monthly_or_onetime from hr.payhead where payhead_id=:ppayhead_id');
        $cmm->addParam('ppayhead_id', $payhead_id);
        $dtpayhead = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        if (count($dtpayhead->Rows()) > 0) {
            $result['payhead_detail'] = $dtpayhead;
            $result['status'] = 'ok';
        }
        return json_encode($result);
    }

//    public function actionGetotrateoncalcmethodchanged($pay_schedule_detail_tran){
//        $pay_detail_tran_temp = json_decode($pay_schedule_detail_tran, true);
//        $result = \app\core\hr\paySchedule\PayScheduleWorker::CalcOTRateonSelectionChanged($pay_detail_tran_temp);
//        
//        $result['status']='ok';
//        return json_encode($result);
//    }    

    public function actionIspayplancreated($pay_schedule_id) {
        $payplan_created = FALSE;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) as cnt from hr.employee_payplan where pay_schedule_id = :ppay_schedule_id and schedule_type = 0');
        $cmm->addParam('ppay_schedule_id', $pay_schedule_id);
        $dtcr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtcr->Rows()) > 0) {
            if ($dtcr->Rows()[0]['cnt'] > 0) {
                $payplan_created = true;
            }
        }

        $result = array();
        $result['payplan_created'] = $payplan_created;
        $result['status'] = 'ok';
        if ($payplan_created) {
            $result['msg'] = 'Cannot Edit! Payplan already created for this schedule.';
        } else {
            $result['msg'] = '';
        }
        return json_encode($result);
    }

    public function actionGetpayscheduledetails($pay_schedule_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.*, b.payhead, c.ot_rate, c.ot_holiday_rate, c.ot_special_rate, c.description as pay_schedule_desc 
                                from hr.pay_schedule_detail a
                                inner join hr.payhead b on a.payhead_id = b.payhead_id
                                inner join hr.pay_schedule c on a.pay_schedule_id = c.pay_schedule_id
                            where a.pay_schedule_id = :ppay_schedule_id order by step_id');
        $cmm->addParam('ppay_schedule_id', $pay_schedule_id);
        $dt_pay_detail = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($dt_pay_detail->Rows() as &$item) {
            if ($item['parent_pay_schedule_details'] != '') {
                $arr = explode(',', $item['parent_pay_schedule_details']);
                for ($a = 0; $a < count($arr); $a++) {
                    $lst = Enumerable::from($dt_pay_detail->Rows())->where('$b==>$b["pay_schedule_detail_id"] == ' . $arr[$a])->toList();
                    if (count($lst) > 0) {
                        $item['parent_pay_schedule_details'] = str_replace($arr[$a], 'step:' . $lst[0]['step_id'], $item['parent_pay_schedule_details']);
                    }
                }
            }
        }

        $result = array();
        $result['pay_detail'] = $dt_pay_detail;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionCalculategratuity($employeeId, $gratuityFromDate, $gratuityToDate) {

        $gratuity = new \app\core\hr\gratuity\worker\GratuityCalculator($employeeId, $gratuityFromDate, $gratuityToDate);

        $result = array();
        $result['gratuity_tran'] = $gratuity->calc_gratuity();
        $result['two_yrs_wages_amt'] = \app\core\hr\gratuity\worker\GratuityWorker::GetTwoYearsWagesAmt($employeeId, $gratuityToDate);
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionCalcinstallment($installmentFrom, $noOfInstallments) {

        $result = array();

        $dtinstallment = new \app\cwf\vsla\data\DataTable();
        $dtinstallment->addColumn('sl_no', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $dtinstallment->addColumn('install_date', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');

        for ($i = 1; $i <= $noOfInstallments; $i++) {

            $newRow = $dtinstallment->NewRow();

            $newRow['sl_no'] = $i;
            $noofmonths = '+ ' . $i . ' months';
            $newRow['install_date'] = date("Y-m-d", strtotime($noofmonths, strtotime($installmentFrom)));
            $dtinstallment->AddRow($newRow);
        }

        $result['loan_tran'] = $dtinstallment;
        $result['status'] = 'ok';

        return json_encode($result);
    }

    public function actionIspayrollgenerated($employee_id, $employee_payplan_id, $effective_from_date) {
        $payroll_generated = FALSE;
        $payroll_date = \app\core\hr\employeePayplan\EmployeePayplanWorker::GetMaxPayrollDate($employee_id);
        $effective_date = \app\core\hr\employeePayplan\EmployeePayplanWorker::GetMaxEffectivePayplanDate($employee_id);

        if ($payroll_date != null) {
            if (strtotime($payroll_date) >= strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $payroll_generated = true;
            }
        }

        if ($employee_payplan_id != -1) {
            if ($payroll_date != null) {
                if (strtotime($effective_date) <= strtotime($payroll_date)) {
                    $payroll_generated = true;
                }
            }
            if (strtotime($effective_from_date) <= strtotime($payroll_date)) {
                $payroll_generated = true;
            }
        }

        $result = array();
        $result['payroll_generated'] = $payroll_generated;
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionCalcfinalsettlement($finsetFromDate, $finsetToDate, $employeeId, $noticePay){
     
        // Build FinalSettlement BO
        $inparam= array();
        $dttemp=null;
        // Create instance of  FinalSettlement BO
        $bopath='../core/hr/finalSettlement/FinalSettlement.xml';
        $bo = new \app\cwf\vsla\xmlbo\XboBuilder($bopath);
        $boInst = $bo->buildBO($inparam);
        
        $classinst= new \app\core\hr\payrollGeneration\worker\PayrollGenTemp($finsetFromDate, $finsetToDate, -1, $boInst->fin_set_payroll_tran,$dttemp);
        
        $paygenWorker= new \app\core\hr\payrollGeneration\worker\PayrollGenerator(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'), "FinalSettlement", $employeeId, $noticePay);

        $paygenWorker->GeneratePayroll($classinst); 

        $result = array();        
        
        $result['fin_set_payroll_tran_gratuity_detail']=$classinst->PayrollTranGratuityDetail();
        $result['fin_set_payroll_tran_detail']=$classinst->PayrollTranDetail();
        $result['fin_set_payroll_tran']=$classinst->PayrollTran();
       
        
        $result['status']='ok';
        
        return json_encode($result);
    }
    

}
