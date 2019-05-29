<?php

namespace app\core\st\salesReturn;
use YaLinqo\Enumerable;

class SalesReturnWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectCustomer':
                $this->setSelectCustomer($data);
                break;
            case 'SelectStockInvoice':
                $this->setSelectStockInvoice($data);
                break;
            case 'SelectMaterial':
                $this->setSelectMaterial($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectCustomer($data){
        if($data->SelectCustomer->account_id==-1){
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        
        $this->data['SelectCustomer']=array();
        if($data->SelectCustomer->account_id !=-1){            
            $this->data['SelectCustomer']['account_id']=$data->SelectCustomer->account_id;
            $this->data['SelectCustomer']['doc_date']=$data->SelectCustomer->doc_date;
            $this->data['SelectCustomer']['from_date']=$data->SelectCustomer->from_date;
            $this->data['SelectCustomer']['to_date']=$data->SelectCustomer->to_date;
        }
    }
    
    private function setSelectStockInvoice($data){
        $this->data['SelectStockInvoice']=array();
        
        $lst=Enumerable::from($data->SelectStockInvoice)->where('$a==>$a->selected==true')->toList();
        if(count($lst)==0){
            array_push($this->brokenrules, 'Please select atleast one Stock Invoice to proceed.');
        }
        if(count($lst) > 1){
            array_push($this->brokenrules, 'Only one Stock Invoice is allowed to select to create Sales Return.');
        }
        
        foreach ($data->SelectStockInvoice as $sr) {
            if((bool)$sr->selected===true){                 
                $this->data['SelectStockInvoice']=array();        
                $this->data['SelectStockInvoice']['stock_id']=$sr->stock_id;
                $this->data['SelectStockInvoice']['si_date']=$sr->doc_date;
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
        
        foreach ($data->SelectMaterial as $m) {
            if((bool)$m->selected===true){
                array_push($this->data['SelectMaterial'], $m);
            }
        }
    }
}