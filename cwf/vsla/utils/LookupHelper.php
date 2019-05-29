<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace app\cwf\vsla\utils;


class LookupHelper {
    
    private static $lookups=[];
    
    public static function GetLookupText($namedlookup, $displaymember, $valuemember, $id){
        if(array_key_exists($namedlookup, self::$lookups)) {
            $lookupItem = self::$lookups[$namedlookup];
        } else {        
            $lookupItem = new LookupItem();
            $lookupItem->namedLookup = $namedlookup;
            $lookupItem->displayMember = $displaymember;
            $lookupItem->valueMember = $valuemember;
            $lookupItem->lookupInstance=new \app\cwf\vsla\xmlbo\LookupInfo($namedlookup, $displaymember,$valuemember);
            self::$lookups[$namedlookup] = $lookupItem;
        }
        return $lookupItem->lookupInstance->initData($id);
    }
}
class LookupItem {
    public $namedLookup='';
    public $displayMember='';
    public $valueMember='';
    public $lookupInstance;
}