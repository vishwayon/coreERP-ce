<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\controllers;

use \app\cwf\vsla\base\WebController;

/**
 * Description of UserSettingsController
 *
 * @author girish
 */
class UsersettingsController extends WebController {

    public function init() {
        parent::init();
    }

    public function actions() {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'oauth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'googleOauthCallback'],
            ]
        ];
    }

//    
//    public function actionPreferences() {
//        // get the login info based on session id
//        $sessionid = \Yii::$app->request->get('sessionid');
//        $userInfo = \app\cwf\vsla\security\SessionManager::getInstance($sessionid)->getUserInfo();
//        $up = new \app\cwf\sys\models\UserPreferences();
//        if(\Yii::$app->request->getIsPost()) {
//            $params = \Yii::$app->request->getBodyParams();
//            $userInfo->setSessionVariable('company_id', $params['company_id']);
//            $userInfo->setSessionVariable('branch_id', $params['branch_id']);
//            $userInfo->setSessionVariable('finyear_id', $params['finyear_id']);
//            $finyear = \app\cwf\vsla\entity\EntityManager::getfinyear($params['finyear_id']);
//            $userInfo->setSessionVariable('finyear', $finyear);
//            $userInfo->persistSessionVariables();
//            return 'successfuly changed user preferences';
//        } else {
//            $up->company_id = $userInfo->getSessionVariable('company_id');
//            $up->branch_id = $userInfo->getSessionVariable('branch_id');
//            $up->finyear_id = $userInfo->getSessionVariable('finyear_id');
//            
//            $up->prepareforRender();
//            return $this->render('UserPreferencesView', ['model' => $up ]);
//        }
//    }

    public function googleOauthCallback($client) {
        // We can use only the auth id or the user id here, as the request does not contain session_id
        $auth_id = \yii::$app->session->get('authid');
        $user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();

        $uattr = $client->getUserAttributes();
        $person_id = $uattr['id'];
        $emails = $uattr['emails'];
        $auth_account = '';
        foreach ($emails as $email) {
            if ($email['type'] == 'account') {
                $auth_account = $email['value'];
                break;
            }
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select count(*) as row_count 
                From sys.user
                Where user_id!=:puser_id And auth_person_id=:pauth_person_id';
        $cmm->setCommandText($sql);
        $cmm->addParam('puser_id', $user_id);
        $cmm->addParam('pauth_person_id', $person_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            if ($dt->Rows()[0]['row_count'] > 0) {
                \yii::$app->session->setFlash('OauthMsg', 'Failed to associate the google account [ ' . $auth_account . '
                      ] with your login as the google account is already associated with some other login.');
                $this->action->setSuccessUrl(\yii\helpers\Url::toRoute(['/cwf/sys/usersettings/oauthstatus']));
                return;
            }
        }

        // Proceed to associate the user
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Update sys.user 
                    Set auth_client=:pauth_client, 
                    auth_person_id=:pauth_person_id,
                    auth_account=:pauth_account
                Where user_id=:puser_id';

        $cmm->setCommandText($sql);
        $cmm->addParam('pauth_client', 'google');
        $cmm->addParam('pauth_person_id', $person_id);
        $cmm->addParam('pauth_account', $auth_account);
        $cmm->addParam('puser_id', $user_id);

        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        // Would finally redirect to select company.
    }

    public function actionAuthsettings() {
        $userPass = new \app\cwf\sys\userProfile\UserPasswordModel();
        $userPass->full_user_name = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName();

        return $this->renderPartial('@app/cwf/sys/userProfile/UserProfileOauth', ['userPass' => $userPass]);
    }

    public function actionChangePass() {
        $userPass = new \app\cwf\sys\userProfile\UserPasswordModel();
        $result = [];
        if ($userPass->load(\yii::$app->request->post())) {
            if ($userPass->validate()) {
                if (\app\cwf\fwShell\models\PassReset::compareNewPassWithOldCurrentUser($userPass->new_password)) {
                    $result['status'] = 'Failed';
                    $result['errors'][]= 'New password cannot be same as old password. Provide new password.';
                } else {
                    $userPass->changePassword();
                    $result['status'] = 'OK';
                }
            } else {
                $result['status'] = 'Failed';
                foreach ($userPass->errors as $fld => $errmsg) {
                    $result['errors'][] = $errmsg[0];
                }
            }
        } else {
            $result['status'] = 'Failed';
            $result['errors'] = 'Only POST supported';
        }
        return json_encode($result);
    }

    public function actionOauthstatus() {
        $ssid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID();
        $msg = \yii::$app->session->getFlash('OauthMsg', 'Something went wrong! we were unable to associate your account');
        return $this->render('@app/cwf/sys/userProfile/UserProfileOauthStatus', ['msg' => $msg, 'url' => \yii\helpers\Url::toRoute(['/cwf/fwShell'])]);
    }

}
