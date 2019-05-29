<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollPayment;

/**
 * Description of PayrollPaymentEventHandler
 *
 * @author Priyanka
 */
class PayrollPaymentEventHandler extends \app\core\ac\base\VoucherBaseEventHandler {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $this->bo->vch_tran->getColumn("dc")->default = "D";
        $this->bo->setTranColDefault('vch_tran', 'dc', "D");
        $this->bo->liability_account_id = -1;
        $this->CreatePayItemTemp();
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->dc = 'C';
            $this->bo->txn_type = 0;
            $this->GetPayItems($criteriaparam['formData']['SelectPayItems']['selected_pay_items']);
        } else {
            $this->GetPayItems('');
            if(count($this->bo->vch_tran->Rows()) > 0){
                $this->bo->liability_account_id = $this->bo->vch_tran->Rows()[0]['account_id'];
            }
        }
    }

    private function GetPayItems($sel_pay_items) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($sel_pay_items == '') {
            $cmm->setCommandText('select * from hr.fn_get_payroll_pay_items(:pbranch_id, :pto_date, :pvoucher_id) a
                                where voucher_id = :pvoucher_id
                                order by a.voucher_id');
        } else {
            $cmm->setCommandText("select * from hr.fn_get_payroll_pay_items(:pbranch_id, :pto_date, :pvoucher_id) a
                                Where a.payroll_tran_id in (" . $sel_pay_items . ")
                                order by a.voucher_id");
        }
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pto_date', date("Y-m-d", time()));
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($dt->Rows() as $row) {
            $newRow = $this->bo->pay_items_tran->NewRow();
            $newRow['payroll_id'] = $row['payroll_id'];
            $newRow['payroll_tran_id'] = $row['payroll_tran_id'];
            $newRow['doc_date'] = $row['doc_date'];
            $newRow['pay_from_date'] = $row['pay_from_date'];
            $newRow['pay_to_date'] = $row['pay_to_date'];
            $newRow['branch_id'] = $row['branch_id'];
            $newRow['payroll_tran_id'] = $row['payroll_tran_id'];
            $newRow['employee_id'] = $row['employee_id'];
            $newRow['tot_emolument_amt'] = $row['tot_emolument_amt'];
            $newRow['tot_deduction_amt'] = $row['tot_deduction_amt'];
            $newRow['employee_no'] = $row['employee_no'];
            $newRow['full_employee_name'] = $row['full_employee_name'];
            $newRow['net_amt'] = $row['net_amt'];
            $newRow['pay_month'] = $row['pay_month'];
            $this->bo->pay_items_tran->AddRow($newRow);
        }
    }

    private function CreatePayItemTemp() {
        // Create temp table for Payroll Item
        $this->bo->pay_items_tran = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->pay_items_tran->addColumn('employee_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->pay_items_tran->addColumn('payroll_id', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->pay_items_tran->addColumn('payroll_tran_id', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->pay_items_tran->addColumn('employee_no', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->pay_items_tran->addColumn('full_employee_name', $phpType, $default, 320, $scale, $isUnique);
        $this->bo->pay_items_tran->addColumn('pay_month', $phpType, $default, 50, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->pay_items_tran->addColumn('tot_emolument_amt', $phpType, $default, 0, 4, $isUnique);
        $this->bo->pay_items_tran->addColumn('tot_deduction_amt', $phpType, $default, 0, 4, $isUnique);
        $this->bo->pay_items_tran->addColumn('net_amt', $phpType, $default, 0, 4, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->pay_items_tran->addColumn('doc_date', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->pay_items_tran->addColumn('pay_from_date', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bool');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->pay_items_tran->addColumn('is_select', $phpType, $default, 0, $scale, $isUnique);

        foreach ($this->bo->pay_items_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('pay_items_tran', $cols);
    }

    public function afterSave($cn) {
        parent::afterSave($cn);

        // Reset voucher_id in Payroll Tran 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("update hr.payroll_tran set voucher_id = '' where voucher_id = :pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        
        
        // Update voucher_id in Payroll Tran 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("update hr.payroll_tran set voucher_id = :pvoucher_id where voucher_id = '' And payroll_tran_id = :ppayroll_tran_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        
        foreach($this->bo->pay_items_tran->Rows() as $row){
            $cmm->addParam('ppayroll_tran_id', $row['payroll_tran_id']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
    
    public function onDelete($cn, $tablename) {
        if($tablename == 'ac.vch_control'){
            // Update voucher_id in Payroll Tran 
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("update hr.payroll_tran set voucher_id = '' where voucher_id = :pvoucher_id");
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

            // Delete from ac.vch_tran

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("delete from ac.vch_control where voucher_id  = :pvoucher_id");
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
}
