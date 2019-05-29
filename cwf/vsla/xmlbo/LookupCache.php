<?php

namespace app\cwf\vsla\xmlbo;

class LookupCache {
    /** @var \yii\caching\Cache **/
    private static $cache = null;
    private static $mainDB = '';
    private static $comp_id = -1;
    private static $initialised = false;
    
    private static function init() {
        self::$cache = \yii::$app->cache;
        self::$mainDB = \app\cwf\vsla\data\DataConnect::getMainDB();
        self::$comp_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        self::$initialised = true;
    }  
    
    public static function getLookup($namedlookup, $filter) {
        if (!self::$initialised )
            self::init ();
        $key = md5(self::$mainDB.self::$comp_id.$namedlookup.$filter);
        $cacheData = self::$cache->get($key);
        if($cacheData) {
            $masterBO = $cacheData['masterBO'];

            // if the item is dirty, discard and return nothing
            $dirtyKey = md5(self::$mainDB.self::$comp_id.'dirtyItems');
            if(self::$cache->exists($dirtyKey)) {
                $dirtyItems = self::$cache->get($dirtyKey);
                if(array_key_exists($masterBO, $dirtyItems)) {
                    if($dirtyItems[$masterBO]>$cacheData['cacheTime']) {
                        // was marked dirty. Data expired. Hence delete and return null
                        self::$cache->delete($key);
                        $cacheData = null;
                    }                
                }
            }

            if ($cacheData != null) {
                return $cacheData['rawData'];
            } else {
                return null;
            }
        }
    }
    
    public static function setLookup($namedlookup, $filter, $data, $masterBO) {
        if (!self::$initialised )
            self::init ();
        $key = md5(self::$mainDB.self::$comp_id.$namedlookup.$filter);
        $cacheData = ['masterBO'=> $masterBO, 'cacheTime'=> time(), 'rawData' => $data];
        self::$cache->set($key, $cacheData);
    }
     
    public static function markDirty($masterBO) {
        // uncommect to activate the code
        // required when lookup cache is implemented
//        if (!self::$initialised )
//            self::init ();
//        $key = md5(self::$mainDB.self::$comp_id.'dirtyItems');
//        $dirtyItems = self::$cache->get($key);
//        if($dirtyItems) {
//            $dirtyItems[$masterBO] = time();
//        } else {
//            $dirtyItems = Array();
//            $dirtyItems[$masterBO] = time();
//        }
//        self::$cache->set($key, $dirtyItems);
    }
}

