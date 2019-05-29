<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ArModule
 *
 * @author Priyanka
 */

namespace app\core\ar;
use yii\base\Module;

/**
 * Module for loading Accounts Receivable
 */

class ArModule extends Module {
    //put your code here
    
    public function init() {
        parent::init();
        
        $this->initDocMap();
    }
    
    private function initDocMap() {
        // Create document map for accounting module
        \app\cwf\vsla\base\DocManager::addMap('INV', '/core/ar/form','invoice/InvoiceEditForm', 'invoice_id');
    }
}

/*
 * End of file
 *  */