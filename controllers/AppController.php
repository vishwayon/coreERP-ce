<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\controllers;

/**
 * Description of AppController
 *
 * @author dev
 */

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;

class AppController extends Controller{
    
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'successCallback'],
            ]
        ];
    }
    
    public function successCallback($client)
    {
        $attributes = $client->getUserAttributes();
    }

    public function actionIndex()
    {
        return $this->actionLogin();
    }
    
    public function beforeAction($action) {
        if($action->id=='Login') {
            $this->enableCsrfValidation == FALSE;
        }
        return true;
    }
    
    public function actionLogin()
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }
        $userinfo = NULL;
        $model = new \app\cwf\fwShell\models\Login();
        $req = Yii::$app->request;
        $msg='';
        if ($req->getIsPost()) {            
            $cookies = Yii::$app->request->getCookies();
            $authInfo = new \app\cwf\vsla\security\AuthInfo();
            $authInfo->auth_id = $cookies->getValue('authid');
            if($authInfo->auth_id != ''){
                $msg.= ' auth_id:'.$authInfo->auth_id;
                if($this->isAuthIdValid($authInfo->auth_id)) {
                    $msg.= ' valid auth_id:'.$authInfo->auth_id;
                    \app\cwf\vsla\security\SessionManager::getInstance($authInfo);
                    if(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getAuthStatus()) {
                        $msg.= ' auth_status:'.\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getAuthStatus();
                        $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
                        $userinfo = $uinfo;
                    }
                }
            } else {            
                $un = $req->post('username');
                $pk = $req->post('password');
                $de = $req->post('device_id');
                $model->username = $un;
                $model->password = $pk;
                if($model->login()){
                    $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
                    $uinfo->setSessionVariable('device_id', $de);
                    $uinfo->persistSessionVariables();
                    \Yii::$app->response->cookies->add(new \yii\web\Cookie(['name' => 'authid', 'value' => $uinfo->getAuth_ID()]));
                    $userinfo = $uinfo;       
                    $msg = ' logged in : '.$userinfo->getAuth_ID();
                }else{
                    \yii::$app->response->clear();
                }
            }
        } 
//        return $msg;
        if( $userinfo != NULL ) {
            $res = ['auth'=>['auth_id'=> $userinfo->getAuth_ID(), 'session_id'=>$userinfo->getSession_ID()]];
            return json_encode($res); //$userinfo->getAuth_ID();
        } else {
            return '0000000000000';
        }        
    }
    
    public function actionHomepage() {
        
    }
    
    private function isAuthIdValid($authid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select auth_id from sys.user_session Where auth_id=:pauth_id;');
        $cmm->addParam('pauth_id', $authid);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($dt->Rows())>=1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
