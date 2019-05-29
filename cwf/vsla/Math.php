<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla;

/*
 * Math library created specific for vsla
 */
class Math {
    public static $amtScale = 2;
    public static $rateScale = 3; 
    public static $qtyScale = 3;
    public static $fcScale = 6;
    
    /**
     * Add any number of variables to get the result
     * returns rounded number based on amtScale
     * @return float The rounded value
     */
    public static function add() {
       $result = 0;
       foreach(func_get_args() as $arg) {
           $result += $arg;
       }
       return round($result, self::$amtScale);
    }
    
    /**
     * Subtract b from a and get result
     * returns rounded number based on amtScale
     * @param type $a
     * @param type $b
     * @return float The rounded value
     */
    public static function sub($a, $b) {
        $result = 0;
        $result = $a - $b;
        return round($result, self::$amtScale);
    }
    
    /**
     * Multiply any number of variables to get the result
     * returns rounded number based on amtScale
     * @return float The rounded value
     */
    public static function mul() {
        $result = 0;
       foreach(func_get_args() as $arg) {
           $result *= $arg;
       }
       return round($result, self::$amtScale);
    }
    
    
    /**
     * Divide a by b to get the result
     * returns rounded number based on amtScale
     * @param type $a
     * @param type $b
     * @return float The rounded value
     */
    public static function div($a, $b) {
        $result = 0;
        $result = $a / $b;
        return round($result, self::$amtScale);
    }
}

