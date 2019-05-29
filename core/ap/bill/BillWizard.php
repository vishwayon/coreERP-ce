<?php

namespace app\core\ap\bill;

class BillWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectSupplier':
                $this->setSelectSupplier($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectSupplier($data){
        if($data->SelectSupplier->supplier_id==-1){
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }
        
        $this->data['SelectSupplier']=array();
        if($data->SelectSupplier->supplier_id !=-1){            
            $this->data['SelectSupplier']['supplier_id']=$data->SelectSupplier->supplier_id;
            $this->data['SelectSupplier']['doc_date']=$data->SelectSupplier->doc_date;
        }
    }
}