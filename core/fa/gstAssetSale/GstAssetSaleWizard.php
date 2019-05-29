<?php

namespace app\core\fa\gstAssetSale;

class GstAssetSaleWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectAssetClass':
                $this->setSelectAssetClass($data);
                break;
            case 'SelectAssetItem':
                $this->setSelectAssetItem($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectAssetClass($data){        
        if($data->SelectAssetClass->asset_class_id==-1){
            array_push($this->brokenrules, 'Please select Asset class to proceed.');
        }
        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select count(*) as count from fa.ad_control where branch_id=:pbranch_id and :pdate <=(select max(dep_date_to) as max_dep_date from fa.ad_control where branch_id=:pbranch_id)');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pdate',  $data->SelectAssetClass->doc_date);
        $result= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if($result->Rows()[0]['count']>0){
                array_push($this->brokenrules, 'Cannot make sale because depreciation for this period is already calculated.');
            }
        }
        
        $this->data['SelectAssetClass']=array();
        if($data->SelectAssetClass->asset_class_id !=-1){            
            $this->data['SelectAssetClass']['asset_class_id']=$data->SelectAssetClass->asset_class_id;          
            $this->data['SelectAssetClass']['doc_date']=$data->SelectAssetClass->doc_date;
        }
    }
    
    private function setSelectAssetItem($data){
        $selectedRows = 0;

        $this->data['SelectAssetItem']=array();
        foreach ($data->SelectAssetItem as $supp) {
            if((bool)$supp->selected===true){
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectAssetItem'], $supp);
            }
        }

        if($selectedRows == 0){
            array_push($this->brokenrules, 'Please select at least one Asset item to proceed.');
        }
    }
}