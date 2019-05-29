<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\base;

/**
 * Description of DocManager
 *
 * @author girish
 */
class DocManager {
    
    public static $docMap = array();
    
    public static function addMap($docType, $route, $formName, $pk = 'voucher_id') {
        $docInfo = new DocInfo();
        $docInfo->route = $route;
        $docInfo->formName = $formName;
        self::$docMap[$docType] = $docInfo;
    }
    
    public static function getMap($docType) {
        return self::$docMap[$docType];
    }
}

class DocInfo {
    public $route;
    public $formName;
}
