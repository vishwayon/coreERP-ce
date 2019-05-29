<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\leaveType;

/**
 * Description of LeaveTypeEventHandler
 *
 * @author Valli
 */

class LeaveTypeEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->leave_type_id==-1)
        {
            $this->bo->carry_forward_limit= 0;
            $this->bo->en_entitlement_type= 1;
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }    
}
