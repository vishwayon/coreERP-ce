<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\security;

/**
 * Description of RestrictIP
 *
 * @author girish
 */
class RestrictIP {
           
    public function validateRequest($userid) {  
        return TRUE;
        $ip = \yii::$app->request->getUserIP();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as result From sys.restrict_ip
                              Where (ip::inet >>= :pip::inet Or ip::inet = '0.0.0.0'::inet)");
        $cmm->addParam('pip', $ip);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($dt->Rows())==1) {
            if(intval($dt->Rows()[0]['result'])>=1) {
                return TRUE;
            } 
        }
        return FALSE;
    }
}
