<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\paySchedule;

use YaLinqo\Enumerable;

/**
 * Description of payScheduleEventHandler
 *
 * @author valli
 */
class PayScheduleEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // temp table for assocated employees
        $this->bo->associated_employee = new \app\cwf\vsla\data\DataTable();
        $this->bo->associated_employee->addColumn('employee_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->bo->associated_employee->addColumn('employee_name', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');

        foreach ($this->bo->pay_schedule_detail_tran->Rows() as &$item) {
            if ($item['parent_pay_schedule_details'] != '') {
                $arr = explode(',', $item['parent_pay_schedule_details']);
                for ($a = 0; $a < count($arr); $a++) {
                    $lst = Enumerable::from($this->bo->pay_schedule_detail_tran->Rows())->where('$b==>$b["pay_schedule_detail_id"] == ' . $arr[$a])->toList();
                    if (count($lst) > 0) {
                        $item['parent_pay_schedule_details'] = str_replace($arr[$a], 'step:' . $lst[0]['step_id'], $item['parent_pay_schedule_details']);
                    }
                }
            }
        }

        // Create Tran table for Emolument Payheads
        $this->bo->pay_schedule_detail_emo_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_emo_tran->cloneColumns($this->bo->pay_schedule_detail_tran);
        foreach ($this->bo->pay_schedule_detail_emo_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('pay_schedule_detail_emo_tran', $cols);

        // copy of emolument tran table
        $this->bo->pay_schedule_detail_copy_emo_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_copy_emo_temp->cloneColumns($this->bo->pay_schedule_detail_tran);

        // Create Tran table for Deduction Payheads
        $this->bo->pay_schedule_detail_ded_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_ded_tran->cloneColumns($this->bo->pay_schedule_detail_tran);
        foreach ($this->bo->pay_schedule_detail_ded_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('pay_schedule_detail_ded_tran', $cols);

        // copy of deduction tran table
        $this->bo->pay_schedule_detail_copy_ded_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_copy_ded_temp->cloneColumns($this->bo->pay_schedule_detail_tran);

        // Create Tran table for Company Contribution Payheads
        $this->bo->pay_schedule_detail_cc_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_cc_tran->cloneColumns($this->bo->pay_schedule_detail_tran);
        foreach ($this->bo->pay_schedule_detail_cc_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('pay_schedule_detail_cc_tran', $cols);

        // copy of company contribution tran table
        $this->bo->pay_schedule_detail_copy_cc_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->pay_schedule_detail_copy_cc_temp->cloneColumns($this->bo->pay_schedule_detail_tran);

        foreach ($this->bo->pay_schedule_detail_tran->Rows() as $row) {            
            if($row['payhead_type']=='E'){
                $newRow = $this->bo->pay_schedule_detail_emo_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_emo_tran->AddRow($newRow);
                
                $newRow = $this->bo->pay_schedule_detail_copy_emo_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_copy_emo_temp->AddRow($newRow);
            }            
            if($row['payhead_type']=='D'){
                $newRow = $this->bo->pay_schedule_detail_ded_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_ded_tran->AddRow($newRow);                
                
                $newRow = $this->bo->pay_schedule_detail_copy_ded_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_copy_ded_temp->AddRow($newRow);
            }          
            if($row['payhead_type']=='C'){
                $newRow = $this->bo->pay_schedule_detail_cc_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_cc_tran->AddRow($newRow);                
                
                $newRow = $this->bo->pay_schedule_detail_copy_cc_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->pay_schedule_detail_copy_cc_temp->AddRow($newRow);
            }
        }
        $this->bo->pay_schedule_copy_id = -1;

        if ($this->bo->pay_schedule_id != -1) {
            $this->getAssociatedEmployee();
        }

        // Create temp table to copy Pay schedule
        $this->bo->detail_temp_for_copy = new \app\cwf\vsla\data\DataTable();
        $this->bo->detail_temp_for_copy->cloneColumns($this->bo->pay_schedule_detail_tran);
        foreach ($this->bo->detail_temp_for_copy->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('detail_temp_for_copy', $cols);
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);

        if ($tablename == 'hr.pay_schedule_detail') {

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM hr.pay_schedule_detail '
                    . 'WHERE pay_schedule_detail_id=:ppay_schedule_detail_id ');
            
            // Delete deleted rows from database for emoluments
            foreach ($this->bo->pay_schedule_detail_copy_emo_temp->Rows() as $temprow) {
                $deletedrow = true;
                foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as $row) {
                    if ($row['pay_schedule_detail_id'] == $temprow['pay_schedule_detail_id']) {
                        $deletedrow = false;
                        break;
                    }
                }
                if ($deletedrow) {
                    $cmm->addParam('ppay_schedule_detail_id', $temprow['pay_schedule_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }
            // Delete deleted rows from database  for deductions
            foreach ($this->bo->pay_schedule_detail_copy_ded_temp->Rows() as $temprow) {
                $deletedrow = true;
                foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as $row) {
                    if ($row['pay_schedule_detail_id'] == $temprow['pay_schedule_detail_id']) {
                        $deletedrow = false;
                        break;
                    }
                }
                if ($deletedrow) {
                    $cmm->addParam('ppay_schedule_detail_id', $temprow['pay_schedule_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }
            // Delete deleted rows from database  for Company Contribution
            foreach ($this->bo->pay_schedule_detail_copy_cc_temp->Rows() as $temprow) {
                $deletedrow = true;
                foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as $row) {
                    if ($row['pay_schedule_detail_id'] == $temprow['pay_schedule_detail_id']) {
                        $deletedrow = false;
                        break;
                    }
                }
                if ($deletedrow) {
                    $cmm->addParam('ppay_schedule_detail_id', $temprow['pay_schedule_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }
                
            $payDetails = array();

            // Save new pay items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('hr.pay_schedule_detail', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            
            // Save emoluments
            foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as &$refpay_schedule_detail_tran_emo_Row) {
                $payDetails = $this->saveTran($refpay_schedule_detail_tran_emo_Row, $payDetails, $ac, $cn);
            }
            
            //Save Deductions
            foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as &$refpay_schedule_detail_tran_ded_Row) {
                $payDetails = $this->saveTran($refpay_schedule_detail_tran_ded_Row, $payDetails, $ac, $cn);
            }
            
            //Save Company Contributions
            foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as &$refpay_schedule_detail_tran_cc_Row) {
                $payDetails = $this->saveTran($refpay_schedule_detail_tran_cc_Row, $payDetails, $ac, $cn);
            }
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        
        // update copy tables on after commit to syn with the tran tables for emoluments, deductions and company contribution
        $rowcount=count($this->bo->pay_schedule_detail_copy_emo_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->pay_schedule_detail_copy_emo_temp->removeRow(0);
        }
        foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as &$refemoitem) {
            if ($refemoitem['parent_pay_schedule_details'] != '') {
                $arr = explode(',', $refemoitem['parent_pay_schedule_details']);
                for ($a = 0; $a < count($arr); $a++) {
                    if(strpos($arr[$a], 'step:')===false){
                        $lst = Enumerable::from($this->bo->pay_schedule_detail_emo_tran->Rows())->where('$b==>$b["pay_schedule_detail_id"] == ' . $arr[$a])->toList();
                        if (count($lst) > 0) {
                            $refemoitem['parent_pay_schedule_details'] = str_replace($arr[$a], 'step:' . $lst[0]['step_id'], $refemoitem['parent_pay_schedule_details']);
                        }
                    }
                }
            }
        }
        foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as $row) { 
            $newRow = $this->bo->pay_schedule_detail_copy_emo_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->pay_schedule_detail_copy_emo_temp->AddRow($newRow);
        }
        
        $rowcount=count($this->bo->pay_schedule_detail_copy_ded_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->pay_schedule_detail_copy_ded_temp->removeRow(0);
        }
        
        foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as &$refdeditem) {
            if ($refdeditem['parent_pay_schedule_details'] != '') {
                $arr = explode(',', $refdeditem['parent_pay_schedule_details']);
                for ($a = 0; $a < count($arr); $a++) {
                    if(strpos($arr[$a], 'step:')===false){
                        $lst = Enumerable::from($this->bo->pay_schedule_detail_ded_tran->Rows())->where('$b==>$b["pay_schedule_detail_id"] == ' . $arr[$a])->toList();
                        if (count($lst) > 0) {
                            $refdeditem['parent_pay_schedule_details'] = str_replace($arr[$a], 'step:' . $lst[0]['step_id'], $refdeditem['parent_pay_schedule_details']);
                        }
                    }
                }
            }
        }
        
        foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as $row) { 
            $newRow = $this->bo->pay_schedule_detail_copy_ded_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->pay_schedule_detail_copy_ded_temp->AddRow($newRow);
        }
        
        $rowcount=count($this->bo->pay_schedule_detail_copy_cc_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->pay_schedule_detail_copy_cc_temp->removeRow(0);
        }
        
        foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as &$refccitem) {
            if ($refccitem['parent_pay_schedule_details'] != '') {
                $arr = explode(',', $refccitem['parent_pay_schedule_details']);
                for ($a = 0; $a < count($arr); $a++) {
                    if(strpos($arr[$a], 'step:')===false){
                        $lst = Enumerable::from($this->bo->pay_schedule_detail_cc_tran->Rows())->where('$b==>$b["pay_schedule_detail_id"] == ' . $arr[$a])->toList();
                        if (count($lst) > 0) {
                            $refccitem['parent_pay_schedule_details'] = str_replace($arr[$a], 'step:' . $lst[0]['step_id'], $refccitem['parent_pay_schedule_details']);
                        }
                    }
                }
            }
        }
        foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as $row) { 
            $newRow = $this->bo->pay_schedule_detail_copy_cc_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->pay_schedule_detail_copy_cc_temp->AddRow($newRow);
        }
    }

    private function getAssociatedEmployee() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.employee_id, c.full_employee_name
                                from hr.employee_payplan  a
                                Inner join hr.employee c on a.employee_id = c.employee_id
                                where a.pay_schedule_id = :ppay_schedule_id
                                        And a.effective_from_date = (select max(effective_from_date) from hr.employee_payplan b 
                                                                        where a.employee_id = b.employee_id)');
        $cmm->addParam('ppay_schedule_id', $this->bo->pay_schedule_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($dt->Rows() as $row) {
            $newRow = $this->bo->associated_employee->NewRow();
            $newRow['employee_id'] = $row['employee_id'];
            $newRow['employee_name'] = $row['full_employee_name'];
            $this->bo->associated_employee->AddRow($newRow);
        }
    }
    
    private function updateTranRowFields($newrow, $row){
        $newRow['pay_schedule_detail_id'] = $row['pay_schedule_detail_id'];
        $newRow['pay_schedule_id'] = $row['pay_schedule_id'];
        $newRow['step_id'] = $row['step_id'];
        $newRow['parent_pay_schedule_details'] = $row['parent_pay_schedule_details'];
        $newRow['description'] = $row['description'];
        $newRow['payhead_id'] = $row['payhead_id'];
        $newRow['payhead_type'] = $row['payhead_type'];
        $newRow['en_pay_type'] = $row['en_pay_type'];
        $newRow['en_round_type'] = $row['en_round_type'];
        $newRow['pay_perc'] = $row['pay_perc'];
        $newRow['pay_on_perc'] = $row['pay_on_perc'];
        $newRow['pay_on_min_amt'] = $row['pay_on_min_amt'];
        $newRow['pay_on_max_amt'] = $row['pay_on_max_amt'];
        $newRow['min_pay_amt'] = $row['min_pay_amt'];
        $newRow['max_pay_amt'] = $row['max_pay_amt'];
        $newRow['amt'] = $row['amt'];
        $newRow['do_not_display'] = $row['do_not_display'];
        return $newRow;
    }
    
    private function saveTran(&$refpay_schedule_detail_tran_Row, $payDetails, $ac, $cn){
        $ParentPayScheduleDetails = $refpay_schedule_detail_tran_Row['parent_pay_schedule_details'];
        foreach ($payDetails as $item) {
            $ParentPayScheduleDetails = str_replace('step:' . $item->step_id, $item->AfterUpdatePayScheduleDetail_ID, $ParentPayScheduleDetails);
        }

        if ($refpay_schedule_detail_tran_Row['pay_schedule_detail_id'] < 0) {
            $cmm = $ac->getInsertCmm();
            $payscheduledetailpkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'hr.pay_schedule_detail', $cn);
        } else {
            $cmm = $ac->getUpdateCmm();
            $payscheduledetailpkid = $refpay_schedule_detail_tran_Row['pay_schedule_detail_id'];
        }
        $cmm->setParamValue('ppay_schedule_detail_id', $payscheduledetailpkid);
        $cmm->setParamValue('ppay_schedule_id', $this->bo->pay_schedule_id);
        $cmm->setParamValue('pstep_id', $refpay_schedule_detail_tran_Row['step_id']);
        $cmm->setParamValue('pparent_pay_schedule_details', $ParentPayScheduleDetails);
        $cmm->setParamValue('pdescription', $refpay_schedule_detail_tran_Row['description']);
        $cmm->setParamValue('ppayhead_id', $refpay_schedule_detail_tran_Row['payhead_id']);
        $cmm->setParamValue('pen_pay_type', $refpay_schedule_detail_tran_Row['en_pay_type']);
        $cmm->setParamValue('pen_round_type', $refpay_schedule_detail_tran_Row['en_round_type']);
        $cmm->setParamValue('ppay_perc', $refpay_schedule_detail_tran_Row['pay_perc']);
        $cmm->setParamValue('ppay_on_perc', $refpay_schedule_detail_tran_Row['pay_on_perc']);
        $cmm->setParamValue('ppay_on_min_amt', $refpay_schedule_detail_tran_Row['pay_on_min_amt']);
        $cmm->setParamValue('ppay_on_max_amt', $refpay_schedule_detail_tran_Row['pay_on_max_amt']);
        $cmm->setParamValue('pmin_pay_amt', $refpay_schedule_detail_tran_Row['min_pay_amt']);
        $cmm->setParamValue('pmax_pay_amt', $refpay_schedule_detail_tran_Row['max_pay_amt']);
        $cmm->setParamValue('pamt', $refpay_schedule_detail_tran_Row['amt']);
        $cmm->setParamValue('pdo_not_display', $refpay_schedule_detail_tran_Row['do_not_display']);
        $cmm->setParamValue('ppayhead_type', $refpay_schedule_detail_tran_Row['payhead_type']);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

        if ($refpay_schedule_detail_tran_Row['pay_schedule_detail_id'] < 0) {
            $payItem = new PayScheduleDetailItem();
            $payItem->step_id = $refpay_schedule_detail_tran_Row['step_id'];
            $payItem->AfterUpdatePayScheduleDetail_ID = $payscheduledetailpkid;
            $payItem->ParentPayScheduleDetails = $refpay_schedule_detail_tran_Row['parent_pay_schedule_details'];

            array_push($payDetails, $payItem);
            $refpay_schedule_detail_tran_Row['pay_schedule_detail_id'] = $payscheduledetailpkid;
            $refpay_schedule_detail_tran_Row['pay_schedule_id'] = $this->bo->pay_schedule_id;
        } else {
            $payItem = new PayScheduleDetailItem();
            $payItem->step_id = $refpay_schedule_detail_tran_Row['step_id'];
            $payItem->AfterUpdatePayScheduleDetail_ID = $refpay_schedule_detail_tran_Row['pay_schedule_detail_id'];
            $payItem->ParentPayScheduleDetails = $refpay_schedule_detail_tran_Row['parent_pay_schedule_details'];

            array_push($payDetails, $payItem);
        }
        return $payDetails;
    }
}

class PayScheduleDetailItem {

    /** @var MethodInfo * */
    public $CurrentPayScheduleDetail_ID;
    public $AfterUpdatePayScheduleDetail_ID;
    public $ParentPayScheduleDetails;
    public $step_id;

}
