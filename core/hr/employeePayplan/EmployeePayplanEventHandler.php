<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\employeePayplan;
use YaLinqo\Enumerable;

/**
 * Description of employeePayplanEventHandler
 *
 * @author Valli
 */

class EmployeePayplanEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
            
        // Create temp table for copy Pay schedule
        $this->bo->pay_schedule_detail_temp = new \app\cwf\vsla\data\DataTable();        
        $this->bo->pay_schedule_detail_temp->cloneColumns($this->bo->epp_detail_tran); 
        foreach($this->bo->pay_schedule_detail_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('pay_schedule_detail_temp', $cols);

        // Update Parent details with Step ID
        foreach($this->bo->epp_detail_tran->Rows() as &$item){
            if($item['parent_details']!=''){
                $arr = explode(',', $item['parent_details']);
                for($a=0; $a < count($arr); $a++){
                    $lst= Enumerable::from($this->bo->epp_detail_tran->Rows())->where('$b==>$b["employee_payplan_detail_id"] == '. $arr[$a])->toList();
                    if(count($lst) > 0){                        
                        $item['parent_details'] = str_replace($arr[$a], 'step:'.$lst[0]['step_id'], $item['parent_details']);                   
                    }
                }
            }
        }

        // Create Tran table for Emolument Payheads
        $this->bo->epp_detail_emo_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_emo_tran->cloneColumns($this->bo->epp_detail_tran);
        foreach ($this->bo->epp_detail_emo_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('epp_detail_emo_tran', $cols);

        // copy of emolument tran table
        $this->bo->epp_detail_copy_emo_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_copy_emo_temp->cloneColumns($this->bo->epp_detail_tran);   

        // Create Tran table for Deduction Payheads
        $this->bo->epp_detail_ded_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_ded_tran->cloneColumns($this->bo->epp_detail_tran);
        foreach ($this->bo->epp_detail_ded_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('epp_detail_ded_tran', $cols);

        // copy of deduction tran table
        $this->bo->epp_detail_copy_ded_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_copy_ded_temp->cloneColumns($this->bo->epp_detail_tran);

        // Create Tran table for Company Contribution Payheads
        $this->bo->epp_detail_cc_tran = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_cc_tran->cloneColumns($this->bo->epp_detail_tran);
        foreach ($this->bo->epp_detail_cc_tran->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('epp_detail_cc_tran', $cols);     
        
        // copy of Company Contribution tran table
        $this->bo->epp_detail_copy_cc_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->epp_detail_copy_cc_temp->cloneColumns($this->bo->epp_detail_tran);
        
        //Seperate rows for E, D and C from fetched tran table
        foreach ($this->bo->epp_detail_tran->Rows() as $row) {
           if($row['payhead_type']=='E'){
                $newRow = $this->bo->epp_detail_emo_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_emo_tran->AddRow($newRow);
                
                $newRow = $this->bo->epp_detail_copy_emo_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_copy_emo_temp->AddRow($newRow);
            }            
            if($row['payhead_type']=='D'){
                $newRow = $this->bo->epp_detail_ded_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_ded_tran->AddRow($newRow);                
                
                $newRow = $this->bo->epp_detail_copy_ded_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_copy_ded_temp->AddRow($newRow);
            }          
            if($row['payhead_type']=='C'){
                $newRow = $this->bo->epp_detail_cc_tran->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_cc_tran->AddRow($newRow);                
                
                $newRow = $this->bo->epp_detail_copy_cc_temp->NewRow();
                $newRow = $this->updateTranRowFields($newRow, $row);
                $this->bo->epp_detail_copy_cc_temp->AddRow($newRow);
            }
        }   
        
        
        if($this->bo->employee_payplan_id == -1){
            $this->bo->employee_id=$criteriaparam['formData']['SelectEmployee']['employee_id'];
            $this->bo->schedule_type=$criteriaparam['formData']['SelectEmployee']['schedule_type'];
            $this->GetCurrentEmpPayPlan();
        }
        
        if(!$this->bo->is_effective_to_date) 
        {
            $this->bo->effective_to_date = '1970-01-01';
        }
        $this->bo->pay_schedule_desc = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/PaySchedule.xml', 'description', 'pay_schedule_id', $this->bo->pay_schedule_id);        
           
    } 
    
    private function GetCurrentEmpPayPlan(){                
        //fetch current payplan for selected employee
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select b.employee_payplan_detail_id, a.pay_schedule_id, a.grade_id, a.ot_rate, a.ot_holiday_rate, a.ot_special_rate,
                                        b.step_id, b.description, b.pay_perc, b.amt, b.payhead_id, b.payhead_type, b.parent_details, b.en_pay_type, b.en_round_type, 
                                        b.pay_on_perc, b.min_pay_amt, b.pay_on_min_amt, b.max_pay_amt, b.pay_on_max_amt, b.do_not_display
                                from hr.employee_payplan a
                                inner join hr.employee_payplan_detail b on a.employee_payplan_id = b.employee_payplan_id
                                where employee_id = :pemployee_id
                                And effective_to_date  is null
                                or effective_from_date = (select max(effective_from_date) from hr.employee_payplan where employee_id = :pemployee_id)
                                And employee_id = :pemployee_id
                                Order by b.step_id');
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())>0){
            // Fetch max payroll date for the employee to set min effective date
            $effecFrom = EmployeePayplanWorker::GetMinDateOnNew($this->bo->employee_id);
            
            if($effecFrom != null){
                $this->bo->effective_from_date = date('Y-m-d', strtotime('+1 days',  strtotime($effecFrom)));
            }
                 
            $this->bo->pay_schedule_id = $result->Rows()[0]['pay_schedule_id'];             
            $this->bo->grade_id = $result->Rows()[0]['grade_id'];
            $this->bo->ot_rate = $result->Rows()[0]['ot_rate'];
            $this->bo->ot_holiday_rate = $result->Rows()[0]['ot_holiday_rate'];
            $this->bo->ot_special_rate = $result->Rows()[0]['ot_special_rate'];  
            
            // Update parent details with step id
            foreach($result->Rows() as &$item){
                if($item['parent_details']!=''){
                    $arr = explode(',', $item['parent_details']);
                    for($a=0; $a < count($arr); $a++){
                        $lst= Enumerable::from($result->Rows())->where('$b==>$b["employee_payplan_detail_id"] == '. $arr[$a])->toList();
                        if(count($lst) > 0){                        
                            $item['parent_details'] = str_replace($arr[$a], 'step:'.$lst[0]['step_id'], $item['parent_details']);                   
                        }
                    }
                }
            }   
            
            //set rows in tran tables
            foreach ($result->Rows() as $row) {                
                if($row['payhead_type']=='E'){
                    $newRow = $this->bo->epp_detail_emo_tran->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_emo_tran->AddRow($newRow);

                    $newRow = $this->bo->epp_detail_copy_emo_temp->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_copy_emo_temp->AddRow($newRow);
                }            
                if($row['payhead_type']=='D'){
                    $newRow = $this->bo->epp_detail_ded_tran->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_ded_tran->AddRow($newRow);                

                    $newRow = $this->bo->epp_detail_copy_ded_temp->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_copy_ded_temp->AddRow($newRow);
                }          
                if($row['payhead_type']=='C'){
                    $newRow = $this->bo->epp_detail_cc_tran->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_cc_tran->AddRow($newRow);                

                    $newRow = $this->bo->epp_detail_copy_cc_temp->NewRow();
                    $newRow = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                    $this->bo->epp_detail_copy_cc_temp->AddRow($newRow);
                }

                $newRow1 = $this->bo->pay_schedule_detail_temp->NewRow();
                $newRow1 = $this->updateTranRowFieldsForCurrEffPayplan($newRow, $row);
                $newRow1['is_select'] = false;
                $this->bo->pay_schedule_detail_temp->AddRow($newRow1);
            }  
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        
        if(!$this->bo->is_effective_to_date) 
        {
            $this->bo->effective_to_date = '1970-01-01';
        }
        $this->UpdateToDate();
        
        // update copy tables on after commit to syn with the tran tables for emoluments, deductions and company contribution
        $rowcount=count($this->bo->epp_detail_copy_emo_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->epp_detail_copy_emo_temp->removeRow(0);
        }
        
        // Update Parent details with Step ID
        foreach($this->bo->epp_detail_emo_tran->Rows() as &$refemoitem){
            if($refemoitem['parent_details']!=''){
                $arr = explode(',', $refemoitem['parent_details']);
                for($a=0; $a < count($arr); $a++){
                    if(strpos($arr[$a], 'step:')===false){
                        $lst= Enumerable::from($this->bo->epp_detail_emo_tran->Rows())->where('$b==>$b["employee_payplan_detail_id"] == '. $arr[$a])->toList();
                        if(count($lst) > 0){                        
                            $refemoitem['parent_details'] = str_replace($arr[$a], 'step:'.$lst[0]['step_id'], $refemoitem['parent_details']);                   
                        }
                    }
                }
            }
        }
        foreach ($this->bo->epp_detail_emo_tran->Rows() as $row) { 
            $newRow = $this->bo->epp_detail_copy_emo_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->epp_detail_copy_emo_temp->AddRow($newRow);
        }
        
        $rowcount=count($this->bo->epp_detail_copy_ded_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->epp_detail_copy_ded_temp->removeRow(0);
        }
        
        // Update Parent details with Step ID
        foreach($this->bo->epp_detail_ded_tran->Rows() as &$refdeditem){
            if($refdeditem['parent_details']!=''){
                $arr = explode(',', $refdeditem['parent_details']);
                for($a=0; $a < count($arr); $a++){
                    if(strpos($arr[$a], 'step:')===false){
                        $lst= Enumerable::from($this->bo->epp_detail_ded_tran->Rows())->where('$b==>$b["employee_payplan_detail_id"] == '. $arr[$a])->toList();
                        if(count($lst) > 0){                        
                            $refdeditem['parent_details'] = str_replace($arr[$a], 'step:'.$lst[0]['step_id'], $refdeditem['parent_details']);                   
                        }
                    }
                }
            }
        }
        foreach ($this->bo->epp_detail_ded_tran->Rows() as $row) { 
            $newRow = $this->bo->epp_detail_copy_ded_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->epp_detail_copy_ded_temp->AddRow($newRow);
        }
        
        $rowcount=count($this->bo->epp_detail_copy_cc_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->epp_detail_copy_cc_temp->removeRow(0);
        }
        
        // Update Parent details with Step ID
        foreach($this->bo->epp_detail_cc_tran->Rows() as &$refccitem){
            if($refccitem['parent_details']!=''){
                $arr = explode(',', $refccitem['parent_details']);
                for($a=0; $a < count($arr); $a++){
                    if(strpos($arr[$a], 'step:')===false){
                        $lst= Enumerable::from($this->bo->epp_detail_cc_tran->Rows())->where('$b==>$b["employee_payplan_detail_id"] == '. $arr[$a])->toList();
                        if(count($lst) > 0){                        
                            $refccitem['parent_details'] = str_replace($arr[$a], 'step:'.$lst[0]['step_id'], $refccitem['parent_details']);                   
                        }
                    }
                }
            }
        }
        foreach ($this->bo->epp_detail_cc_tran->Rows() as $row) { 
            $newRow = $this->bo->epp_detail_copy_cc_temp->NewRow();
            $newRow = $this->updateTranRowFields($newRow, $row);
            $this->bo->epp_detail_copy_cc_temp->AddRow($newRow);
        }
    }     
    
    private function UpdateToDate(){  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select employee_payplan_id, employee_id from hr.employee_payplan
                                where employee_payplan_id <> :pemployee_payplan_id and effective_from_date < :peffective_from_date
                                        And employee_id = :pemployee_id 
                                        And effective_from_date = (Select max(effective_from_date) from hr.employee_payplan 
                                                                    where employee_payplan_id <> :pemployee_payplan_id
                                                                        and effective_from_date < :peffective_from_date
                                                                        And employee_id = :pemployee_id )");
        $cmm->addParam('pemployee_payplan_id', $this->bo->employee_payplan_id);
        $cmm->addParam('peffective_from_date', $this->bo->effective_from_date);
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($dt->Rows())>0){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('update hr.employee_payplan set effective_to_date =:peffective_to_date, is_effective_to_date = true where employee_payplan_id = :pemployee_payplan_id and employee_id = :pemployee_id');
            $cmm->addParam('pemployee_payplan_id', $dt->Rows()[0]['employee_payplan_id']);
            $cmm->addParam('peffective_to_date', date('Y-m-d', strtotime('-1 days',  strtotime($this->bo->effective_from_date))));
            $cmm->addParam('pemployee_id', $dt->Rows()[0]['employee_id']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }
    }
    
    public function afterDeleteCommit() {
        parent::afterDeleteCommit();
        
        // Update effective to date for previous payplan to null
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select employee_payplan_id, employee_id from hr.employee_payplan
                                where employee_id = :pemployee_id  
                                    And effective_from_date = (Select max(effective_from_date) from hr.employee_payplan 
                                                                    where employee_id = :pemployee_id )");
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($dt->Rows())>0){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('update hr.employee_payplan set effective_to_date = null, is_effective_to_date = false where employee_payplan_id = :pemployee_payplan_id and employee_id = :pemployee_id');
            $cmm->addParam('pemployee_payplan_id', $dt->Rows()[0]['employee_payplan_id']);
            $cmm->addParam('pemployee_id', $dt->Rows()[0]['employee_id']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }
    }
    
    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }  
    
    public function onSave($cn, $tablename){
        parent::onSave($cn, $tablename);  
        
        if($tablename=='hr.employee_payplan_detail'){
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM hr.employee_payplan_detail '
                                 . 'WHERE employee_payplan_detail_id=:pemployee_payplan_detail_id ');
            
            // Delete deleted rows from database for emoluments
            foreach($this->bo->epp_detail_copy_emo_temp->Rows() as $temprow){
                $deletedrow=true;
                foreach($this->bo->epp_detail_emo_tran->Rows() as $row){
                     if($row['employee_payplan_detail_id']==$temprow['employee_payplan_detail_id']){
                         $deletedrow=false;
                         break;
                     }
                     $deletedrow=TRUE;
                 }
                if($deletedrow){                    
                    $cmm->addParam('pemployee_payplan_detail_id', $temprow['employee_payplan_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                }
            }
            
            // Delete deleted rows from database  for deductions
            foreach($this->bo->epp_detail_copy_ded_temp->Rows() as $temprow){
                $deletedrow=true;
                foreach($this->bo->epp_detail_ded_tran->Rows() as $row){
                     if($row['employee_payplan_detail_id']==$temprow['employee_payplan_detail_id']){
                         $deletedrow=false;
                         break;
                     }
                     $deletedrow=TRUE;
                 }
                if($deletedrow){                    
                    $cmm->addParam('pemployee_payplan_detail_id', $temprow['employee_payplan_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                }
            }
            
            // Delete deleted rows from database  for Company Contribution
            foreach($this->bo->epp_detail_copy_cc_temp->Rows() as $temprow){
                $deletedrow=true;
                foreach($this->bo->epp_detail_cc_tran->Rows() as $row){
                     if($row['employee_payplan_detail_id']==$temprow['employee_payplan_detail_id']){
                         $deletedrow=false;
                         break;
                     }
                     $deletedrow=TRUE;
                 }
                if($deletedrow){                    
                    $cmm->addParam('pemployee_payplan_detail_id', $temprow['employee_payplan_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                }
            }
            
            
            $payDetails= array();
            
            // Save new pay items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('hr.employee_payplan_detail',  \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            
            // Save Emoluments
            foreach($this->bo->epp_detail_emo_tran->Rows() as &$refepp_detail_emo_tran_Row)
            { 
                $payDetails = $this->saveTran($refepp_detail_emo_tran_Row, $payDetails, $ac, $cn);
            }
            // Save DEductions
            foreach($this->bo->epp_detail_ded_tran->Rows() as &$refepp_detail_ded_tran_Row)
            { 
                $payDetails = $this->saveTran($refepp_detail_ded_tran_Row, $payDetails, $ac, $cn);
            }
            // Save Company Contributions
            foreach($this->bo->epp_detail_cc_tran->Rows() as &$refepp_detail_cc_tran_Row)
            { 
                $payDetails = $this->saveTran($refepp_detail_cc_tran_Row, $payDetails, $ac, $cn);
            }
        }
    }
    
    private function updateTranRowFields($newRow, $row){
        $newRow['employee_payplan_detail_id'] = $row['employee_payplan_detail_id'];
        if(array_key_exists('employee_payplan_id', $row)){
            $newRow['employee_payplan_id'] = $row['employee_payplan_id'];
        }
        else{
            $newRow['employee_payplan_id'] = -1;
        }
        $newRow['step_id'] = $row['step_id'];
        $newRow['parent_details'] =  $row['parent_details'];
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
    
    private function updateTranRowFieldsForCurrEffPayplan($newRow, $row){
        $newRow['step_id'] = $row['step_id'];
        $newRow['parent_details'] =  $row['parent_details'];
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
    
    private function saveTran(&$refepp_detail_tran_Row, $payDetails, $ac, $cn){        
        $ParentDetails=$refepp_detail_tran_Row['parent_details'];
        foreach($payDetails as $item){
            $ParentDetails=  str_replace('step:'.$item->Step_ID,$item->AfterUpdateEPPDetail_ID, $ParentDetails);                   
        }

        if($refepp_detail_tran_Row['employee_payplan_detail_id']<0){
            $cmm = $ac->getInsertCmm();
            $detailpkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'hr.employee_payplan_detail', $cn);
        }
        else{
            $cmm = $ac->getUpdateCmm();
            $detailpkid = $refepp_detail_tran_Row['employee_payplan_detail_id'];
        }
        $cmm->setParamValue('pemployee_payplan_detail_id', $detailpkid);
        $cmm->setParamValue('pemployee_payplan_id', $this->bo->employee_payplan_id);
        $cmm->setParamValue('pstep_id', $refepp_detail_tran_Row['step_id']);
        $cmm->setParamValue('pparent_details', $ParentDetails);
        $cmm->setParamValue('pdescription', $refepp_detail_tran_Row['description']);
        $cmm->setParamValue('ppayhead_id', $refepp_detail_tran_Row['payhead_id']);
        $cmm->setParamValue('ppayhead_type', $refepp_detail_tran_Row['payhead_type']);
        $cmm->setParamValue('pen_pay_type', $refepp_detail_tran_Row['en_pay_type']);
        $cmm->setParamValue('pen_round_type', $refepp_detail_tran_Row['en_round_type']);
        $cmm->setParamValue('ppay_perc', $refepp_detail_tran_Row['pay_perc']);
        $cmm->setParamValue('ppay_on_perc', $refepp_detail_tran_Row['pay_on_perc']);
        $cmm->setParamValue('ppay_on_min_amt', $refepp_detail_tran_Row['pay_on_min_amt']);
        $cmm->setParamValue('ppay_on_max_amt', $refepp_detail_tran_Row['pay_on_max_amt']);
        $cmm->setParamValue('pmin_pay_amt', $refepp_detail_tran_Row['min_pay_amt']);
        $cmm->setParamValue('pmax_pay_amt', $refepp_detail_tran_Row['max_pay_amt']);
        $cmm->setParamValue('pamt', $refepp_detail_tran_Row['amt']);
        $cmm->setParamValue('pdo_not_display', $refepp_detail_tran_Row['do_not_display']);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);  

        if($refepp_detail_tran_Row['employee_payplan_detail_id'] < 0){
            $payItem= new EmployeePayPlanDetailItem();
            $payItem->CurrentEPPDetail_ID=$refepp_detail_tran_Row['employee_payplan_detail_id'];
            $payItem->Step_ID=$refepp_detail_tran_Row['step_id'];
            $payItem->AfterUpdateEPPDetail_ID=$detailpkid;
            $payItem->ParentDetails=$refepp_detail_tran_Row['parent_details'];

            array_push($payDetails,$payItem);
            $refepp_detail_tran_Row['pay_schedule_detail_id']=$detailpkid;
            $refepp_detail_tran_Row['pay_schedule_id']=$this->bo->pay_schedule_id;
            $refepp_detail_tran_Row['employee_payplan_id']=$this->bo->employee_payplan_id;
            $refepp_detail_tran_Row['parent_details']=$ParentDetails;
            $refepp_detail_tran_Row['employee_payplan_detail_id'] = $payItem->AfterUpdateEPPDetail_ID;
        }
        else{
            $payItem= new EmployeePayPlanDetailItem();
            $payItem->CurrentEPPDetail_ID=$refepp_detail_tran_Row['employee_payplan_detail_id'];
            $payItem->Step_ID=$refepp_detail_tran_Row['step_id'];
            $payItem->AfterUpdateEPPDetail_ID=$refepp_detail_tran_Row['employee_payplan_detail_id'];
            $payItem->ParentDetails=$refepp_detail_tran_Row['parent_details'];

            array_push($payDetails,$payItem);
        }
        return $payDetails;
    }
}

class EmployeePayPlanDetailItem{
        /** @var MethodInfo **/
        public $CurrentEPPDetail_ID;
        public $AfterUpdateEPPDetail_ID; 
        public $ParentDetails; 
        public $Step_ID;
    }