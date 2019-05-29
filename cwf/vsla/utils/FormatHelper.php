<?php

namespace app\cwf\vsla\utils;


class FormatHelper {
    
    private static $nf = null;
    
    /*
     * Returns a string representation of the number with format e.g. 2,678.98
     */
    public static function FormatAmt($value) {
        //return number_format($value, \app\cwf\vsla\Math::$amtScale, "." , ",");
        if(self::$nf == null) {
            if(\app\cwf\vsla\security\SessionManager::getCCYSystem() == 'l') {
                self::$nf = new \NumberFormatter($locale = 'en_IN', \NumberFormatter::DECIMAL);
                self::$nf->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, \app\cwf\vsla\Math::$amtScale);
                self::$nf->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, \app\cwf\vsla\Math::$amtScale);
            } else {
                self::$nf = new \NumberFormatter($locale = 'en_US', \NumberFormatter::DECIMAL);
                self::$nf->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, \app\cwf\vsla\Math::$amtScale);
                self::$nf->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, \app\cwf\vsla\Math::$amtScale);
            }
        }
        return self::$nf->format($value); 
    }
    
    public static function FormatNumber($value) {
        return number_format($value, 0, "." , ",");
    }
    
    public static function FormatRate($value) {
        return number_format($value, \app\cwf\vsla\Math::$rateScale, "." , ",");
    }
    
    public static function FormatQty($value) {
        return number_format($value, \app\cwf\vsla\Math::$qtyScale, "." , ",");
    }
    
    public static function FormatFC($value) {
        return number_format($value, \app\cwf\vsla\Math::$fcScale, "." , ",");
    }
    
    public static function GetNumericValue($formattedValue) {
        if (strlen($formattedValue)> 0) {
            $result = str_replace(',', '', $formattedValue);
            if(strpos($result, '.')>0) {
                return floatval($result);
            } else {
                return intval($result);
            }
        } else {
            return 0;
        }
    }
    
    public static function GetNumberFormat(){
        $result= str_pad('#,##0.', \app\cwf\vsla\Math::$amtScale+6, '0');
        return $result;
    }
    
    public static function GetQtyFormat(){
        $result= str_pad('#,##0.', \app\cwf\vsla\Math::$qtyScale+6, '0');
        return $result;
    }
    
    public static function GetRateFormat(){
        $result= str_pad('#,##0.', \app\cwf\vsla\Math::$rateScale+6, '0');
        return $result;
    }
    
    public static function GetFCRateFormat(){
        $result= str_pad('#,##0.', \app\cwf\vsla\Math::$fcScale+6, '#');
        return $result;
    }
    
    public static function GetAmtFormat(){
        $result= str_pad('#,##0.', \app\cwf\vsla\Math::$amtScale+6, '0');
        return $result;
    }
    
    public static function FormatDateForDisplay($value) {
        $date = \DateTime::createFromFormat('Y-m-d|' , $value);
        if($date == new \DateTime("1970-01-01 00:00:00.000000")) {
            return '';
        } else {
            return date_format($date, self::GetDateFormatForPHP()) ;
        }
    }
    
    /*
     * Returns a date time object formatted to the user_time_zone
     * for the purposes of display in html. Do not use the returned 
     * value for making date calculations as it would be in the specific 
     * time zone and not in UTC
     */
    public static function FormatDateWithTimeForDisplay($value) {
        $date = strpos(' UTC', $value) ? $value : $value.' UTC';
        $dtm = new \DateTime($date);
        $dtm->setTimezone(\app\cwf\vsla\security\SessionManager::getUserTimeZone());
        return $dtm->format(self::GetDateFormatWithTimeForPHP());
    }
    
    //Returns DBDate from formatted JSON date Value
    public static function GetDBDate($formattedValue) {
        $dateFormat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format');
        if($dateFormat == 'dd/mm/yyyy') {
            $parts = explode('/', $formattedValue);
            if(checkdate($parts[1], $parts[0], $parts[2])) {
                $date = new \DateTime();
                $date->setDate($parts[2], $parts[1], $parts[0]);
                return $date->format('Y-m-d');
            }
        }
        if($dateFormat == 'dd-mm-yyyy') {
            $parts = explode('-', $formattedValue);
            if(checkdate($parts[1], $parts[0], $parts[2])) {
                $date = new \DateTime();
                $date->setDate($parts[2], $parts[1], $parts[0]);
                return $date->format('Y-m-d');
            }
        }
        if($dateFormat == 'mm-dd-yyyy') {
            $parts = explode('-', $formattedValue);
            if(checkdate($parts[0], $parts[1], $parts[2])) {
                $date = new \DateTime();
                $date->setDate($parts[2], $parts[0], $parts[1]);
                return $date->format('Y-m-d');
            }
        }
        if($dateFormat == 'mm/dd/yyyy') {
            $parts = explode('/', $formattedValue);
            if(checkdate($parts[0], $parts[1], $parts[2])) {
                $date = new \DateTime();
                $date->setDate($parts[2], $parts[0], $parts[1]);
                return $date->format('Y-m-d');
            }
        }
        return false;
    }
    
    // returns DateTime based on the DBDate value
    public static function GetDateValue($dbDate) {
        return \DateTime::createFromFormat('Y-m-d 00:00:00', $dbDate);
    }
    
    public static function GetDateFormatWithTimeForPHP(){
        $dateFormat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format');
        if($dateFormat == 'dd/mm/yyyy') {
                return 'd/m/Y H:i:s T';
        }
        if($dateFormat == 'dd-mm-yyyy') {
                return 'd-m-Y H:i:s T';
        }
        if($dateFormat == 'mm-dd-yyyy') {
                return 'm-d-Y H:i:s T';
        }
        if($dateFormat == 'mm/dd/yyyy') {
                return 'm/d/Y H:i:s T';
        }
        return '';
    }
    
    public static function GetDateFormatForPHP(){
        $dateFormat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format');
        if($dateFormat == 'dd/mm/yyyy') {
                return 'd/m/Y';
        }
        if($dateFormat == 'dd-mm-yyyy') {
                return 'd-m-Y';
        }
        if($dateFormat == 'mm-dd-yyyy') {
                return 'm-d-Y';
        }
        if($dateFormat == 'mm/dd/yyyy') {
                return 'm/d/Y';
        }
        return '';
    }
    
    public static function GetDateFormatForHtml(){
        $dateFormat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format');
        if($dateFormat == 'dd/mm/yyyy') {
                return 'dd/mm/yyyy';
        }
        if($dateFormat == 'dd-mm-yyyy') {
                return 'dd-mm-yyyy';
        }
        if($dateFormat == 'mm-dd-yyyy') {
                return 'mm-dd-yyyy';
        }
        if($dateFormat == 'mm/dd/yyyy') {
                return 'mm/dd/yyyy';
        }
        return '';
    }
    
    public static function GetDateFormatForReport(){
        $dateFormat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format');
        if($dateFormat == 'dd/mm/yyyy') {
                return 'dd/MM/yyyy';
        }
        if($dateFormat == 'dd-mm-yyyy') {
                return 'dd-MM-yyyy';
        }
        if($dateFormat == 'mm-dd-yyyy') {
                return 'MM-dd-yyyy';
        }
        if($dateFormat == 'mm/dd/yyyy') {
                return 'MM/dd/yyyy';
        }
        return '';
    }
    
    public static function GetValidDate(){
        $yearBegins = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
        $yearEnds = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
        if(strtotime($yearBegins) > time()){
            return $yearBegins;
        } else if(strtotime($yearEnds) <  time()){
            return $yearEnds;
        } else {
            return date("Y-m-d", time());
        }
    }
}