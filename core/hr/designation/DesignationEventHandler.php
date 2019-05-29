<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\designation;

/**
 * Description of designationEventHandler
 *
 * @author Valli
 */

class DesignationEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->designation_id == -1){
            $this->bo->rank = 0;
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }
    
    
}
