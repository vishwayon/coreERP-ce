<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of VHostController
 *
 * @author girish
 */

namespace app\cwf\console\utils\controllers;

class VhostController extends \yii\console\Controller {
    
    
    
    public function actionCreatevhost($sourcePath) {
        $outstream =fopen('php://memory','r+');
        try {            
            
            $configReader = new \app\cwf\vsla\utils\ConfigReader();
            $worker = new \app\cwf\console\utils\workers\VhostWorker($configReader, $outstream, $sourcePath);
            $worker->run();
            echo "vHosts created successfully\n";
//            
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
    
    
}
