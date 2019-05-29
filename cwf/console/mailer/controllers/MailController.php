<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\mailer\controllers;

class MailController extends \yii\console\Controller {
    
   
    public function actionSendmail(){
        echo date('Y-m-d H:i:s').": Starting sendmail\n";
        try{
            
            $configReader= new \app\cwf\vsla\utils\ConfigReader();
            $dbServer= $configReader->dbInfo->dbServer;
            $dbUser= $configReader->dbInfo->suName;
            $dbPass= $configReader->dbInfo->suPass;
            $dbMain= $configReader->dbInfo->dbMain;

            $class= new \app\cwf\console\mailer\workers\MailSenderWorker();
            $class->Start($dbServer, $dbUser, $dbMain, $dbPass);

            echo date('Y-m-d H:i:s').": Send completed\n";
                
        } catch (\Exception $ex) {
            echo date('Y-m-d H:i:s').": Exception: ".$ex->getMessage(). "\n";
        }
    }
}
