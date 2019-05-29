<?php

namespace app\core\hr\employeePayplan;

class EmployeePayplanWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectEmployee':
                $this->setSelectEmployee($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectEmployee($data){
        if($data->SelectEmployee->employee_id==-1){
            array_push($this->brokenrules, 'Please select Employee to proceed.');
        }
        
        if($data->SelectEmployee->schedule_type==-1){
            array_push($this->brokenrules, 'Please select Schedule Type to proceed.');
        }
        
        $this->data['SelectEmployee']=array();
        if($data->SelectEmployee->employee_id !=-1){            
            $this->data['SelectEmployee']['employee_id']=$data->SelectEmployee->employee_id;
            $this->data['SelectEmployee']['schedule_type']=$data->SelectEmployee->schedule_type;
        }
    }
}