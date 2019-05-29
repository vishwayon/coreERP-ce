<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\dashboard;

/**
 * Description of BankBalanceEventHandler
 *
 * @author Priyanka
 */
class BankBalanceEventHandler extends \app\cwf\vsla\dbd\WidgetEventHandlerBase {
    
    public function beforeFetch($series_id, $params) {
        parent::beforeFetch($series_id, $params);
//        
//        if($series_id == 'acctype1'){
//            $params['paccount_type_id']->ParamValue = 2;
//        }
    }
}
