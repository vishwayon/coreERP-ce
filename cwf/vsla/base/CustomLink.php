<?php

namespace app\cwf\vsla\ui;

class CustomLink 
    extends yii\web\UrlManager{

    public function createUrl($params) {
        $tempurl= parent::createUrl($params);
        $resurl='javascript:coreWebApp.rendercontents(\''.$tempurl.'\')';
        return $resurl;
    }
}