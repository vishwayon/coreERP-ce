<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration;

/**
 * Description of PayrollGenerationEventHandler
 *
 * @author Valli
 */
class PayrollGenerationEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
                
        if ($this->bo->payroll_id == "" or $this->bo->payroll_id == "-1") {
            $this->bo->payroll_id = "";
            $this->bo->status = 0;

            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');

            if (strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $this->bo->doc_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }


            if (array_key_exists('formData', $criteriaparam)) {
                $this->bo->payroll_group_id = $criteriaparam['formData']['SelectPayrollGroup']['payroll_group_id'];                               
                $this->bo->pay_from_date = $criteriaparam['formData']['SelectPayrollGroup']['pay_from_date'];
                $this->bo->pay_to_date = $criteriaparam['formData']['SelectPayrollGroup']['pay_to_date'];

                foreach ($criteriaparam['formData']['PayheadCustomAmount'] as $tran) {
                    $newRow = $this->bo->payroll_custom_tran->newRow();
                    $newRow['employee_payplan_detail_id'] = $tran['employee_payplan_detail_id'];
                    $newRow['employee_id'] = $tran['employee_id'];
                    $newRow['payhead_id'] = $tran['payhead_id'];
                    $newRow['payhead_type'] = $tran['payhead_type'];
                    if ($tran['payhead_type'] == 'E' || $tran['payhead_type'] == 'C') {
                        $newRow['emolument_amt'] = $tran['amt'];
                    } else {
                        $newRow['deduction_amt'] = $tran['amt'];
                    }
                    $this->bo->payroll_custom_tran->AddRow($newRow);
                }
            } else {
                // Set From Date & To date
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Select date(max(pay_to_date) + INTERVAL '1 Day') as max_pay_to_date from hr.payroll_control where finYear = :pfinyear");
                $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
                $dtresult = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dtresult->Rows()) > 0) {
                    if ($dtresult->Rows()[0]['max_pay_to_date'] !== null) {
                        $from_date = $dtresult->Rows()[0]['max_pay_to_date'];
                    } else {
                        $from_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
                    }
                    if ($from_date > \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')) {
                        $from_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
                        $month = date("m", strtotime($from_date));
                        $year = date("Y", strtotime($from_date));
                        $from_date = date("Y-m-d", strtotime("01-" . $month . "-" . $year));
                    }

                    $this->bo->pay_from_date = $from_date;

                    $month = date("m", strtotime($from_date));
                    $year = date("Y", strtotime($from_date));
                    $noofdays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $end_date = date("Y-m-d", strtotime($noofdays . "-" . $month . "-" . $year));
                    $this->bo->pay_to_date = $end_date;
                }
            }
        } else {
            // Fetch Text for id fields
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.payroll_id,a.payroll_tran_id,a.employee_id, b.employee_no, b.full_employee_name
                                  from hr.payroll_tran a inner join hr.employee b on a.employee_id=b.employee_id
                                  where a.payroll_id = :ppayroll_id');
            $cmm->addParam('ppayroll_id', $this->bo->payroll_id);

            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                foreach ($this->bo->payroll_tran->Rows() as &$refpayroll_tran_row) {
                    foreach ($result->Rows() as $row) {
                        if ($row['payroll_tran_id'] == $refpayroll_tran_row['payroll_tran_id']) {
                            $refpayroll_tran_row['employee_no'] = $row['employee_no'];
                            $refpayroll_tran_row['employee_fullname'] = $row['full_employee_name'];
                            break;
                        }
                    }
                }
            }

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.payroll_id,a.payroll_tran_id, a.payroll_tran_detail_id,a.employee_id, b.full_employee_name, a.payhead_id, c.payhead 
                                  from hr.payroll_tran_detail a inner join hr.employee b on a.employee_id=b.employee_id
                                  inner join hr.payhead c on a.payhead_id=c.payhead_id where a.payroll_id = :ppayroll_id');
            $cmm->addParam('ppayroll_id', $this->bo->payroll_id);

            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                foreach ($this->bo->payroll_tran->Rows() as &$refpayroll_tran_row) {
                    foreach ($refpayroll_tran_row['payroll_tran_detail']->Rows() as &$refpayroll_tran_detail_row) {
                        foreach ($result->Rows() as $row) {
                            if ($row['payroll_tran_detail_id'] == $refpayroll_tran_detail_row['payroll_tran_detail_id']) {
                                $refpayroll_tran_detail_row['employee_fullname'] = $row['full_employee_name'];
                                $refpayroll_tran_detail_row['payhead'] = $row['payhead'];
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        $this->bo->payroll_custom_tran_temp = new \app\cwf\vsla\data\DataTable(); 
        $this->bo->payroll_custom_tran_temp->cloneColumns($this->bo->payroll_custom_tran);  
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $this->bo->payroll_custom_tran_temp->addColumn('employee_fullname', $phpType, $default, 320, 0, false);
        $this->bo->payroll_custom_tran_temp->addColumn('payhead', $phpType, $default, 50, 0, false);
        $this->fillPayrollCustomTranTemp();
    }
    
    
    
    private function fillPayrollCustomTranTemp(){   
        $rowcount=count($this->bo->payroll_custom_tran_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->payroll_custom_tran_temp->removeRow(0);
        }
       
        foreach ($this->bo->payroll_custom_tran->Rows() as $row) {
            $newRow = $this->bo->payroll_custom_tran_temp->NewRow();
            $newRow['payroll_custom_tran_id'] = $row['payroll_custom_tran_id'];
            $newRow['payroll_id'] = $row['payroll_id'];
            $newRow['employee_id'] = $row['employee_id'];
            $newRow['payhead_id'] = $row['payhead_id'];
            $newRow['payhead_type'] = $row['payhead_type'];
            $newRow['emolument_amt'] = $row['emolument_amt'];
            $newRow['deduction_amt'] = $row['deduction_amt'];
            $newRow['employee_fullname'] = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Employee.xml', 'full_employee_name', 'employee_id', $row['employee_id']);
            $newRow['payhead'] = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead', 'payhead_id', $row['payhead_id']);
            
            
            if ($row['payhead_type'] == 'E' || $row['payhead_type'] == 'c'){
                $newRow['amt'] = $row['emolument_amt'];
            }
            else{
                $newRow['amt'] = $row['deduction_amt'];
            }
            $newRow['employee_payplan_detail_id'] = $row['employee_payplan_detail_id'];
            $this->bo->payroll_custom_tran_temp->AddRow($newRow);
        }   
        foreach($this->bo->payroll_custom_tran_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('payroll_custom_tran_temp', $cols);
    }

}
