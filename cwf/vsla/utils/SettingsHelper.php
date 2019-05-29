<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SettingsHelper
 *
 * @author priyanka
 */
namespace app\cwf\vsla\utils;

class SettingsHelper {  
    private static $dtCollection=null;
    
    public static function GetKeyValue($key){
        self::GetSettingsCollection();
        if(array_key_exists($key, self::$dtCollection)){
            return self::$dtCollection[$key];
        }
        else{
                throw new \Exception('Key ' . $key . ' not found in sys.settings');
        }
    }
    
    public static function HasKey($key) {
        self::GetSettingsCollection();
        return array_key_exists($key, self::$dtCollection);
    }
    
    private static function GetSettingsCollection(){
        if(self::$dtCollection==NULL){                  
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select key, value from sys.settings');
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm); 
            self::$dtCollection=$dt->asArray('key', 'value');
        }
    }
}
