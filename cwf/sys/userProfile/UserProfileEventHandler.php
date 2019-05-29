<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userProfile;

/**
 * Description of UserProfileEventHandler
 *
 * @author Priyanka
 */
class UserProfileEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
    }    
    
    public function onFetch($criteriaparam, $tablename)
    {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id, user_name, user_pass from sys.user where user_id=:puser_id');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());   
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($dt->Rows())>0){
            $this->bo->user_name=$dt->Rows()[0]['user_name'];
            $this->bo->user_id=\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
            $this->bo->old_user_pass=$dt->Rows()[0]['user_pass'];
        }
    }    
    
    public function onSave($cn, $tablename)
    {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('update sys.user set user_pass=:puser_pass where user_id=:puser_id');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());  
        $cmm->addParam('puser_pass', $this->bo->user_pass);   
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }
}
