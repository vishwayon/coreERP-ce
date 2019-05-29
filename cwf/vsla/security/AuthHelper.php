<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\security;

class AuthHelper {
    
    public static function verifyGoogleOAuth($client) {
        $uattr = $client->getUserAttributes();
        $person_id = $uattr['id'];
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id, user_name, full_user_name From sys.user Where auth_person_id=:pperson_id');
        $cmm->addParam('pperson_id', $person_id);
        $dtuinfo = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        
        if(count($dtuinfo->Rows())==1) {
            $authInfo = new \app\cwf\vsla\security\AuthInfo();
            $authInfo->userName = $dtuinfo->Rows()[0]['user_name'];
            $authInfo->userPass = '';
            $authInfo->person_id = (string)$person_id;            
            SessionManager::getInstance($authInfo);
            if(SessionManager::getAuthStatus()) {
                return true;
            }
        }
        return false;
    }
}

