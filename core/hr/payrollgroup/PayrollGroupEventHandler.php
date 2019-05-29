<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollgroup;

/**
 * Description of PayrollGroupEventHandler
 *
 * @author Valli
 */

class PayrollGroupEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->payroll_group_id == -1){
            $this->bo->en_pay_period = 2;
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }
    
    
}
