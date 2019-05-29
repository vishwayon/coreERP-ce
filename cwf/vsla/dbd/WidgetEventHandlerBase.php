<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\dbd;

abstract class WidgetEventHandlerBase{

    protected $series_id;
    
    public function initialise()
    {
        
    }
    
    public function beforeFetch($series_id, $params)
    {
        
    }
    
    public function afterFetch($series_id, &$collection)
    {
        
    }
}