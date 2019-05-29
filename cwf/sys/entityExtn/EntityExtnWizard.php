<?php

namespace app\cwf\sys\entityextn;

class EntityExtnWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectEntity':
                $this->setEntity($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setEntity($data){
        if($data->SelectEntity->entity_type==-1){
            array_push($this->brokenrules, 'Please select Entity Type to proceed.');
        }
        if($data->SelectEntity->bo_id==-1){
            array_push($this->brokenrules, 'Please select Entity to proceed.');
        }
        
        $this->data['SelectEntity']=array();
        if($data->SelectEntity->entity_type!=-1 && $data->SelectEntity->bo_id !=-1){            
            $this->data['SelectEntity']['entity_type']=$data->SelectEntity->entity_type;
            $this->data['SelectEntity']['bo_id']=$data->SelectEntity->bo_id;
        }
    }
}