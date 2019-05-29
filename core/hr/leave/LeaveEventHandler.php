<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\leave;

/**
 * Description of leaveEventHandler
 *
 * @author Valli
 */

class LeaveEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        if($this->bo->leave_id==-1)
        {
            $this->bo->finyear= \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'); 
        }
        
        if(!$this->bo->is_authorised_on){
            $this->bo->authorised_on = '1970-01-01';
        }
        
        if(!$this->bo->is_rejoin_date){
            $this->bo->rejoin_date = '1970-01-01';
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
        
        if(!$this->bo->replacement_required)
        {
            $this->bo->replacing_emp_id=-1;
        }
    }
    
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        
        if(!$this->bo->is_authorised_on){
            $this->bo->authorised_on = '1970-01-01';
        }
        
        if(!$this->bo->is_rejoin_date){
            $this->bo->rejoin_date = '1970-01-01';
        }
    }
}
