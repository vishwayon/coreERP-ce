<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\design;

/**
 * Description of BusinessObject
 *
 * @author girish
 */
class BusinessObject extends CwFrameworkType {
    //put your code here
    const TYPE_MASTER = 'Master';
    const TYPE_DOCUMENT = 'Document';
    const TYPE_REPORT = 'Report';
    
    // Atrtributes
    public $id = '';
    public $type = self::TYPE_MASTER;
    public $extends = '';
    
    //Elements
    //public $connectionType = app\cwf\vsla\data\DataConnect::MAIN_DB;
    
    public function getType() {
        self::BUSINESS_OBJECT;
    }
}

