<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\restrictIP;

/**
 * Description of RestrictIPEventHandler
 *
 * @author Shrishail
 */
class RestrictIPEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
      
        if($this->bo->restrict_ip_id == '' or $this->bo->restrict_ip_id == -1)
        {
           $this->bo->domain = $_SERVER['HTTP_HOST']; 
           $this->bo->ip = \yii::$app->request->getUserIP().'/32' ;
        }  
    }  
}
