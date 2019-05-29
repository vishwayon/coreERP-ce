<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\controllers;

/**
 * Description of TwigReportController
 *
 * @author priyanka
 */
class TwigReportController extends \app\cwf\fwShell\controllers\TwigReportController{
    //put your code here
    function init() {
        parent::init();
        $twigOptions = &\yii::$app->view->renderers['twig'];
        // Register yii classes that you plan to use in twig
        $twigOptions['globals'] = [
                        'formatHelper' => '\app\cwf\vsla\utils\FormatHelper'
                    ];
    }
}
