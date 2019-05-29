<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\dashboard;

/**
 * Description of PendingInvoicesEventHandler
 *
 * @author Priyanka
 */
class PendingInvoicesEventHandler extends \app\cwf\vsla\dbd\WidgetEventHandlerBase {
    
    public function beforeFetch($series_id, $params) {
        parent::beforeFetch($series_id, $params);
        
        $to_date = new \DateTime();            
        //$to_date->setDate(date("'Y-m-d 00:00:00'", strtotime('+ 3 days', strtotime($params['pto_date']->ParamValue))));
        
        $yearEnd = strtotime('+ 3 days', strtotime($params['pto_date']->ParamValue));
        $returnValue = date("Y-m-d", $yearEnd);
            
        if($series_id == 'PendingInvoices'){
            $params['pto_date']->ParamValue = $returnValue;
        }
    }
}
