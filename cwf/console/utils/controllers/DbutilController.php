<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Database
 *
 * @author girish
 */


namespace app\cwf\console\utils\controllers;

class DbutilController extends \yii\console\Controller {
    //put your code here
    public function actionBackup() {
        $outstream =fopen('php://memory','r+');
        try {            
            
            $configReader = new \app\cwf\vsla\utils\ConfigReader();
            $dbBkup = new \app\cwf\console\utils\workers\DbUtilities($configReader, $outstream);
            $dbBkup->backupAll();
            echo "Backup completed successfully\n";
//            
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
    
    public function actionAgefile($dirPath) {
        $outstream =fopen('php://memory','r+');
        try {            
                        
            $fileUtil = new \app\cwf\console\utils\workers\FileAgedWorker($dirPath);
            $fileUtil->cleanUp();
            echo "cleanup completed successfully. ".$fileUtil->getRemoveCount()." files removed\n" ;
            
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
    
    public function actionCopyLatest($sourceDir, $targetDir) {
        $outstream =fopen('php://memory','r+');
        try {            
                        
            $fileUtil = new \app\cwf\console\utils\workers\FileAgedWorker($sourceDir);
            $fileUtil->copyLatest($targetDir);
            echo "copy completed successfully.\n" ;
          
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
}
