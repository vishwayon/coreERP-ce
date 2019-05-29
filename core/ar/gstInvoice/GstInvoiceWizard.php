<?php

namespace app\core\ar\gstInvoice;

class GstInvoiceWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectIncomeType':
                $this->setSelectIncomeType($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectIncomeType($data){
        if($data->SelectIncomeType->income_type_id==-1){
            array_push($this->brokenrules, 'Please select Income Type to proceed.');
        }
        $this->data['SelectIncomeType']=array();
        if($data->SelectIncomeType->income_type_id !=-1){            
            $this->data['SelectIncomeType']['income_type_id']=$data->SelectIncomeType->income_type_id;
        }
    }
}