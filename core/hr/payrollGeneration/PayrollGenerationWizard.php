<?php

namespace app\core\hr\payrollGeneration;
use YaLinqo\Enumerable;

class PayrollGenerationWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectPayrollGroup':
                $this->setSelectPayrollGroup($data);
                break;
            case 'PayheadCustomAmount':
                $this->setPayheadCustomAmount($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectPayrollGroup($data){

        $from_date = date("Y-m-01", strtotime($data->SelectPayrollGroup->pay_month));
        $to_date = date("Y-m-t", strtotime($data->SelectPayrollGroup->pay_month));

//        $data->SelectPayrollGroup->pay_from_date = $from_date;
//        $data->SelectPayrollGroup->pay_to_date = $to_date;
        
        if($data->SelectPayrollGroup->payroll_group_id==-1){
            array_push($this->brokenrules, 'Please select Payroll Group to proceed.');
        }
        
        $this->data['SelectPayrollGroup']=array();
        if($data->SelectPayrollGroup->payroll_group_id !=-1){            
            $this->data['SelectPayrollGroup']['payroll_group_id']=$data->SelectPayrollGroup->payroll_group_id;
            $this->data['SelectPayrollGroup']['pay_month']=$data->SelectPayrollGroup->pay_month;
            $this->data['SelectPayrollGroup']['pay_from_date']=$from_date;
            $this->data['SelectPayrollGroup']['pay_to_date']=$to_date;
        }
    }
    private function setPayheadCustomAmount($data){
        $selectedRows = 0;
        $this->data['PayheadCustomAmount']=array();
               
        foreach ($data->PayheadCustomAmount as $payhead) {
//            if((bool)$payhead->selected===true){
                $selectedRows = $selectedRows + 1;
                array_push($this->data['PayheadCustomAmount'], $payhead);
//            }
        }
    }
}