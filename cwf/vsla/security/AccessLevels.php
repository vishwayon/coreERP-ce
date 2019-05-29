<?php

namespace app\cwf\vsla\security;

/**
 * Description of AccessLevels
 *
 * @author dev
 */

class AccessLevels{
    const NOACCESS      = 0;
    const READONLY      = 1;
    const DATAENTRY     = 2;
    const AUTHORIZE     = 3;
    const CONSOLIDATED  = 4;
    
    const ALLOW_DELETE  = TRUE;
    const ALLOW_UNPOST = TRUE;
    
    public static function getLevel($accessLevel_int) {
        switch($accessLevel_int) {
            case 1:
                return self::READONLY;
            case 2:
                return self::DATAENTRY;
            case 3:
                return self::AUTHORIZE;
            case 4:
                return self::CONSOLIDATED;
            default :
                return self::NOACCESS;
        }
    }
}
