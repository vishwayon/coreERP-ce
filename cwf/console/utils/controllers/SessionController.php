<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SessionController
 *
 * @author girish
 */

namespace app\cwf\console\utils\controllers;

class SessionController extends \yii\console\Controller {
    
    
    
    public function actionKillexpired() {
        $outstream =fopen('php://memory','r+');
        try {            
            
            $configReader = new \app\cwf\vsla\utils\ConfigReader();
            $cleanup = new \app\cwf\console\utils\workers\SessionCleanup($configReader, $outstream);
            $cleanup->DoCleanup();
            echo "Session cleanup completed successfully\n";
//            
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
    
    
}
