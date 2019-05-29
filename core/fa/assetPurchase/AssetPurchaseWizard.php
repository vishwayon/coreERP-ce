<?php

namespace app\core\fa\assetPurchase;

class AssetPurchaseWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectSupplierAccount':
                $this->setSelectSupplierAccount($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectSupplierAccount($data){
        if($data->SelectSupplierAccount->account_id==-1){
            array_push($this->brokenrules, 'Please select Supplier Account to proceed.');
        }
        
        $this->data['SelectSupplierAccount']=array();
        if($data->SelectSupplierAccount->account_id !=-1){            
            $this->data['SelectSupplierAccount']['account_id']=$data->SelectSupplierAccount->account_id;
        }
    }
}