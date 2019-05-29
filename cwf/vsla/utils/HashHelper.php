<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of HashHelper
 *
 * @author dev
 */

class HashHelper {
    
    public static function getHash($input) {
        return \Yii::$app->getSecurity()->generatePasswordHash($input);
    }
    
    public static function validateHash($input, $sys) {
        if (\Yii::$app->getSecurity()->validatePassword($input, $sys)) {
            return true;
        } else {
            return false;
        }
    }
    
}
