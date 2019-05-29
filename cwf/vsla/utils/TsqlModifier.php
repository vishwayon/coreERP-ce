<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of TsqlModifier
 *
 * @author girish
 */
class TsqlModifier {
    
    public static function modifyFunctions($vars, \PDO $cn = null) {
                
        $offset = self::getoffset($vars['user_time_zone']);
        $sql = 'DROP FUNCTION sys.to_time_zone(timestamp without time zone);';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        
        $sql = 'Create function sys.to_time_zone(vtime_stamp timestamp, Out vwith_time_zone timestamp)
                Returns timestamp
                As
                $BODY$
                Begin
                        -- Any change in logic of this function should also be updated in vsla/utils/TsqlModifier
                        vwith_time_zone := vtime_stamp + time \''.$offset.'\';
                End;
                $BODY$
                 Language plpgsql IMMUTABLE;';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        
        $sql = 'DROP FUNCTION sys.time_display(timestamp without time zone)';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);        
        
        $sql_date_format = self::getSqlDateFormat($vars['date_format']);
        $sql = 'Create function sys.time_display(vtime_stamp timestamp, Out vformatted_time Varchar(50))
                Returns Varchar(50)
                As
                $BODY$
                Begin
                        -- Any change in logic of this function should also be updated in vsla/utils/TsqlModifier
                        vformatted_time := to_char(sys.to_time_zone(vtime_stamp), \''.$sql_date_format.' HH24:MI:SS\');
                End;
                $BODY$
                 Language plpgsql IMMUTABLE;';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);        
    }
    
    private static function getoffset($time_zone) {
        $dtz = new \DateTimeZone($time_zone);
        $time = new \DateTime("now", $dtz);
        return $time->format('P');
    }
    
    private static function getSqlDateFormat($date_format) {
        // postgres requires all items to be in upper case for formats
        return strtoupper($date_format);
    }   
}
