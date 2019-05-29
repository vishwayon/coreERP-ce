<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of StModule
 *
 * @author Kaustubh
 */

namespace app\core\st;
use yii\base\Module;

/**
 * Module for loading st
 */

class StModule extends Module {
    //put your code here
    
    public function init() {
        parent::init();
        
        $this->controllerMap = [
            'mat-val-mon' => 'app\core\st\matValueMonitor\MatValMonController',
        ];
        
    }
}

/*
 * End of file
 *  */