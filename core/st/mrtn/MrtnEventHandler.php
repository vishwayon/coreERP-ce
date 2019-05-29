<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\mrtn;

/**
 * Description of MrtnEventHandler
 *
 * @author Priyanka
 */
class MrtnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        if($this->bo->stock_id=="" or $this->bo->stock_id=="-1"){
            $this->bo->stock_id="";
            $this->bo->fc_type_id=0;
            $this->bo->exch_rate=1; 
            $this->bo->status=0;
            $this->bo->branch_id= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        }
    }
}
