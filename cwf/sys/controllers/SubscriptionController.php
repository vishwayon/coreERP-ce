<?php

namespace app\cwf\sys\controllers;

class SubscriptionController extends \yii\web\Controller {

    public function beforeAction($action) {
        if ($action->id == 'exec') {
            $this->enableCsrfValidation = false;
        }
        return true;
    }

    public function actionAdd() {
        $postData = \Yii::$app->request->bodyParams;
        if ($postData != NULL && count($postData) > 0) {
            $postData['user_id'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
            $subscr = \app\cwf\sys\subscription\subscriptionHelper::createSubscription($postData);
            if ($subscr != NULL) {
                return json_encode($subscr->addUpdate());
            }
        }
        return 'Invalid request.';
    }

    public function actionRemove($subscription_id) {
        $subscr = new \app\cwf\sys\subscription\modelSubscription($subscription_id);
        $subscr->remove();
    }

    public function actionExec($subscr_id) {
        $username = 'reportuser';
        $userpass = 'Reportuser123#';
        $authInfo = new \app\cwf\vsla\security\AuthInfo();
        $authInfo->userName = $username;
        $authInfo->userPass = $userpass;
        \app\cwf\vsla\security\SessionManager::getInstance($authInfo);
        $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($userinfo->getAuthStatus()) {
            // Gets the authentication session id and adds it to the cookie
            $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
            \yii::$app->session->open();
            \yii::$app->session['authid'] = $uinfo->getAuth_ID();
            if ($subscr_id != NULL && $subscr_id != -1) {
                $subscr = new \app\cwf\sys\subscription\modelSubscription($subscr_id);
                return $subscr->exec();
            } else {
                return 'invalid subscription info';
            }
        }
        return 'Invalid settings. Subscription execution failed.';
    }

}
