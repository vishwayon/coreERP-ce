<?php

namespace app\core\tds\tdsPayment;

class TDSPaymentWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectPersonType':
                $this->setSelectPersonType($data);
                break;
            case 'SelectBill':
                $this->setSelectBill($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    
    private function setSelectPersonType($data){
        if($data->SelectPersonType->person_type_id==-1){
            array_push($this->brokenrules, 'Please select Person Type to proceed.');
        }
        
        $this->data['SelectPersonType']=array();
        if($data->SelectPersonType->person_type_id !=-1){            
            $this->data['SelectPersonType']['person_type_id']=$data->SelectPersonType->person_type_id;
        }
    }
    
    private function setSelectBill($data){
        $selectedRows = 0;
        $this->data['SelectBill']=array();
        
        foreach ($data->SelectBill as $bill) {
            if((bool)$bill->selected===true){
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectBill'], $bill);
            }
        }
        if($selectedRows == 0){
            array_push($this->brokenrules, 'Please select atleast one Bill to proceed.');
        }
    }
}