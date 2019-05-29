<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHead;

/**
 * Description of SubHeadEventHandler
 *
 * @author Shrishail
 */
class SubHeadEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function beforeSave($cn) {
       parent::beforeSave($cn);
       
   }
   
   public function afterFetch($criteriaparam) {
       parent::afterFetch($criteriaparam);
       
       
        if(!$this->bo->is_closed){
            $this->bo->closed_date='1970-01-01';
        }
   }
   
   public function afterCommit($generatedKeys) {
       parent::afterCommit($generatedKeys);
       
        if(!$this->bo->is_closed){
            $this->bo->closed_date='1970-01-01';
        }
   }
}
