<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\payTerm;
use app\cwf\vsla\data\DataConnect;

/**
 * Description of PayTermEventHandler
 *
 * @author Kaustubh
 */
class PayTermEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
        
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);        
        
        if($this->bo->pay_term_id == "" or $this->bo->pay_term_id == -1)
        {
            $this->bo->pay_days=0;
        } 
        
    }
}
