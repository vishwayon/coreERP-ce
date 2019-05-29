<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of ScriptHelper
 *
 * @author girish
 */
class ScriptHelper {
    //put your code here
    
    
    public static function registerScript($scriptFile) {
        
        $src = \yii::getAlias($scriptFile);
        $basePath = \yii::getAlias('@webroot/assets');
        $filetime =  filemtime($src);
        
        $dir = self::hash(dirname($src));
        $fileName = basename($src);
        $dstDir = $basePath . DIRECTORY_SEPARATOR . $dir;
        $dstFile = $dstDir . DIRECTORY_SEPARATOR . $fileName;

        if (!is_dir($dstDir)) {
            \yii\helpers\FileHelper::createDirectory($dstDir);
        }

        // Create Symbolic link for the file
        if (!is_file($dstFile)) {
            symlink($src, $dstFile);
        }

        return \yii::$app->getUrlManager()->baseUrl . "/assets/$dir/$fileName" . "?v=" . $filetime;
    }
    
    private static function hash($scriptFile) {
        return sprintf('%x', crc32($scriptFile));
    }
}
