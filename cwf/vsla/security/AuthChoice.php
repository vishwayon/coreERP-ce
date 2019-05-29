<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\vsla\security;

use yii\helpers\Url;
use yii\helpers\Html;

class AuthChoice extends \yii\authclient\widgets\AuthChoice {
    
    private $authClients = array();
    
    private function getOAuthInfo() {
        $auth_id = SessionManager::getInstance()->getUserInfo()->getAuth_ID();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select auth_client, auth_account, email, user_name, 'aaaaa' as pass_key
                From sys.user
                Where user_id=(Select user_id From sys.user_session where auth_id=:pauth_id Limit 1)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pauth_id', $auth_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        return $dt;
    }
    
    protected function renderMainContent() {
        $this->extendClients();
        echo Html::beginTag('ul', ['class' => 'auth-clients clear', 'style' => 'list-style: none;']);
        foreach ($this->authClients as $key => $value) {
            echo Html::beginTag('li', ['class' => 'auth-client', 'style' => 'height: 60px;']);
            echo Html::beginTag('div', ['class' => 'col-md-12']);
            echo Html::beginTag('div', ['class' => 'col-md-1', 'style' => 'padding-top: 20px;']);
            echo Html::checkbox('for_'.$key, $value['is_selected'], ['disabled' => 'disabled']);
            echo Html::endTag('div');
            
            echo Html::beginTag('div', ['class' => 'col-md-3']);
            $this->clientLink($value['externalService']);
            echo Html::endTag('div');
            
            if($value['is_selected']) {
                echo Html::beginTag('div', ['class' => 'col-md-8']);
                echo '<i> using account </i><h4> '.Html::encode($value['auth_account']).'</h4>';
                echo Html::endTag('div');
            }
            echo Html::endTag('div');
            echo Html::endTag('li');
        }
        echo Html::endTag('ul');
    }
    
    private function extendClients() {
        $userAuth = $this->getOAuthInfo()->Rows(); 
        foreach($this->getClients() as $externalService) {
            foreach($userAuth as $uaItem) {
                if($externalService->getName() == $uaItem['auth_client']) {
                    $this->authClients[$externalService->getName()] = [
                            'externalService' => $externalService,
                            'is_selected' => true,
                            'auth_account' => $uaItem['auth_account']
                        ];                
                } else {
                    $this->authClients[$externalService->getName()] = [
                            'externalService' => $externalService,
                            'is_selected' => false,
                            'auth_account' => ''
                        ];   
                }
            }
            
        }
    }
    
}
