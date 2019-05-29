<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payhead;

/**
 * Description of payheadEventHandler
 *
 * @author Valli
 */

class PayheadEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        $this->bo->calc_type ="Fixed";
        $this->bo->monthly_or_onetime = 1;
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }
    
    
}
