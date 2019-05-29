<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ACModule
 *
 * @author girish
 */

namespace app\core\ac;
use yii\base\Module;

/**
 * Module for loading Accounts
 */

class AcModule extends Module {
    //put your code here
    
    public function init() {
        parent::init();
        
        $this->initDocMap();
        
        $this->controllerMap = [
            'custMon' => 'app\core\ac\dashboard\custMon\CustMonController',
        ];
        
    }
    
    private function initDocMap() {
        // Create document map for accounting module
        \app\cwf\vsla\base\DocManager::addMap('BPV', '/core/ac/form','bankPayment/BankPaymentEditForm');
        \app\cwf\vsla\base\DocManager::addMap('BRV', '/core/ac/form','bankReceipt/BankReceiptEditForm');
        \app\cwf\vsla\base\DocManager::addMap('CPV', '/core/ac/form','cashPayment/CashPaymentEditForm');
        \app\cwf\vsla\base\DocManager::addMap('CRV', '/core/ac/form','cashReceipt/CashReceiptEditForm');
        \app\cwf\vsla\base\DocManager::addMap('JV', '/core/ac/form','journalVoucher/JournalVoucherEditForm');
        \app\cwf\vsla\base\DocManager::addMap('CV', '/core/ac/form','contraVoucher/ContraVoucherEditForm');
    }
}

/*
 * End of file
 *  */