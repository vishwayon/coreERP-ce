<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetPurchase;

/**
 * Description of AssetPurchaseEventHandler
 *
 * @author Priyanka
 */
class AssetPurchaseEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);   
        // Create Tran table for tax detail
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CreateTaxDetailTemp($this->bo);
        $this->bo->tax_schedule_id=-1; 
        $this->bo->applicable_to_supplier = true;
        $this->bo->applicable_to_customer = false;
        
        if($this->bo->ap_id=="" or $this->bo->ap_id=="-1")
        {
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->ap_id="";
            $this->bo->fc_type_id=0;
            $this->bo->exch_rate=1; 
            //$this->bo->cheque_number="0"; 
            $this->bo->status=0;
            if(strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->doc_date= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
        }
        else{
            \app\core\tx\taxSchedule\worker\TaxScheduleHelper::GetTaxDetailsOnEdit($this->bo);
        }
        
        $this->bo->ap_tran->getColumn("asset_qty")->default= 0;
        $this->bo->setTranColDefault('ap_tran', 'asset_qty', 0);
    }
}
