<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\ui;

/**
 * Description of ViewParserFactory
 *
 * @author girish
 */
class ViewParserFactory {

    private static $parsers = [];

    public static function getParser($xmlViewPath, $modulePath, $formParams) {
        if (!array_key_exists($xmlViewPath, self::$parsers)) {
            $viewX = simplexml_load_file($xmlViewPath);
            $vp = new viewparser($viewX->formView, $modulePath, $formParams);
            self::$parsers[$xmlViewPath] = $vp;
            return $vp;
        } else {
            return self::$parsers[$xmlViewPath];
        }
    }

}
