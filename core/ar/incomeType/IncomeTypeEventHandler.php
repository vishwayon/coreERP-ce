<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\incomeType;

/**
 * Description of IncomeTypeEventHandler
 *
 * @author Ravindra
 */

class IncomeTypeEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
        
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
      
        if($this->bo->income_type_id=="" or $this->bo->income_type_id==-1)
        {
            $this->bo->company_id=\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
        }
    }
}

            
