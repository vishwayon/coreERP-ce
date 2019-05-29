<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\saj;

/**
 * Description of JournalVoucherEventHandler
 *
 * @author Priyanka
 */
class SajEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase  {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);        
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1; 
            $this->bo->status = 0;
            if (strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $this->bo->doc_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }
}
