<?php

namespace app\core\st\purchaseReturn;
use YaLinqo\Enumerable;

class PurchaseReturnWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectSupplier':
                $this->setSelectSupplier($data);
                break;
            case 'SelectStockPurchase':
                $this->setSelectStockPurchase($data);
                break;
            case 'SelectMaterial':
                $this->setSelectMaterial($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectSupplier($data){
        if($data->SelectSupplier->account_id==-1){
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }
        
        $this->data['SelectSupplier']=array();
        if($data->SelectSupplier->account_id !=-1){            
            $this->data['SelectSupplier']['account_id']=$data->SelectSupplier->account_id;
            $this->data['SelectSupplier']['doc_date']=$data->SelectSupplier->doc_date;
            $this->data['SelectSupplier']['from_date']=$data->SelectSupplier->from_date;
            $this->data['SelectSupplier']['to_date']=$data->SelectSupplier->to_date;
        }
    }
    
    private function setSelectStockPurchase($data){
        $this->data['SelectStockPurchase']=array();
        
        $lst=Enumerable::from($data->SelectStockPurchase)->where('$a==>$a->selected==true')->toList();
        if(count($lst)==0){
            array_push($this->brokenrules, 'Please select atleast one Stock Purchase to proceed.');
        }
        if(count($lst) > 1){
            array_push($this->brokenrules, 'Only one Stock Purchase is allowed to select to create Purchase Return.');
        }
        
        foreach ($data->SelectStockPurchase as $sp) {
            if((bool)$sp->selected===true){                 
                $this->data['SelectStockPurchase']=array();        
                $this->data['SelectStockPurchase']['stock_id']=$sp->stock_id;
                $this->data['SelectStockPurchase']['sp_date']=$sp->doc_date;
            }
        }
    }
    
    private function setSelectMaterial($data){
        $this->data['SelectMaterial']=array();
        
        $lst=Enumerable::from($data->SelectMaterial)->where('$a==>$a->selected==true')->toList();
        if(count($lst)==0){
            array_push($this->brokenrules, 'Please select atleast one Stock Item to proceed.');
        }
        
        $lst1=Enumerable::from($data->SelectMaterial)->where('$a==>$a->selected==true && $a->return_qty==0')->toList();
        if(count($lst1)>0){
            array_push($this->brokenrules, 'Return Qty cannot be zero.');
        }
        
        $lst2=Enumerable::from($data->SelectMaterial)->where('$a==>$a->selected==true && $a->return_qty > $a->balance_qty')->toList();
        if(count($lst2)>0){
            array_push($this->brokenrules, 'Return Qty cannot be greater than Balance Qty.');
        }
        
        foreach ($data->SelectMaterial as $supp) {
            if((bool)$supp->selected===true){
                array_push($this->data['SelectMaterial'], $supp);
            }
        }
    }
}