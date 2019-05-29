<?php

namespace app\core\st\prn;

class PRNWizard
    extends \app\cwf\vsla\xmlbo\WizardBase {
    
    public function setData($step, $data, $oldStepData) {
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectVatType':
                $this->setSelectVatType($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectVatType($data){
        if($data->SelectVatType->vat_type_id==-1){
            array_push($this->brokenrules, 'Please select Sale(s) VAT/GST Type to proceed.');
        }
        
        $this->data['SelectVatType']=array();
        if($data->SelectVatType->vat_type_id !=-1){            
            $this->data['SelectVatType']['vat_type_id']=$data->SelectVatType->vat_type_id;
        }
    }
}
