<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\gstAssetPurchase;

/**
 * Description of GstAssetPurchaseEventHandler
 *
 * @author Priyanka
 */
class GstAssetPurchaseEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);   
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
        // Create Tran table for tax detail
        $this->bo->ap_without_po = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('fa_ap_without_po');
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

            // Fetch Adv alloc details
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->ap_id);
        }
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->account_id, $this->bo->doc_date);
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->account_id, $this->bo->doc_date);
        }
    }
}
