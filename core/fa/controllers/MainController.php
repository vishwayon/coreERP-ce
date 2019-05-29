<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SiteController
 *
 * @author girish
 */
namespace app\core\fa\controllers;

use app\cwf\vsla\base\WebController;


class MainController extends \yii\web\Controller {
    
    public function actionIndex($viewName = null, $viewParams = null) {
        return 'Main/Index';
    }
    
    public function actionAssetlocation() {
        return 'Main/AssetLocation';
    }
    
    public function actionTest() {
        return 'TestAction';
    }
}