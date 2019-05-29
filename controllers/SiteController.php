<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\cwf\fwShell\models\PassReset;

require_once getcwd() . '/../cwf/vsla/security/password-policy.php';

class SiteController extends Controller {

    public function init() {
        parent::init();
    }

    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions() {
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
                'successCallback' => [$this, 'googleOauthCallback'],
            ]
        ];
    }

    public function googleOauthCallback($client) {
        // user login or signup comes here
        if (\app\cwf\vsla\security\AuthHelper::verifyGoogleOAuth($client)) {
            $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
            \yii::$app->session->open();
            \yii::$app->session['authid'] = $uinfo->getAuth_ID();
        } else {
            if (\app\cwf\vsla\security\SessionManager::hasInstance()) {
                $msg = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFailedMessage();
            } else {
                $msg = 'Google login succeeded but associated user not found! </br><span style="color: black; font-size: normal;">Please login with Username and Password.</br>'
                        . 'You can associate your Google login by clicking on</br><strong>User Profile</strong></span>';
            }
            \yii::$app->session->setFlash('OauthMsg', $msg);
            $this->action->setSuccessUrl(\yii\helpers\Url::toRoute(['/site/login']));
        }
    }

    public function actionIndex() {
        return $this->actionLogin();
    }

    public function actionLogin() {
        // Check if user has already logged in then use existing auth info          
        // This is already set in app_start event
        if (\app\cwf\vsla\security\SessionManager::getAuthStatus()) {
            return $this->redirect(\yii\helpers\Url::toRoute('/cwf/fwShell/main/index'));
        }

        $model = new \app\cwf\fwShell\models\Login();

        if ($model->load(Yii::$app->request->post())) {
            // User is logging in for first time, try to authenticate
            if ($model->login()) {
                // Gets the authentication session id and adds it to the cookie
                $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
                // Request OTP if required
                if ($uinfo->getOtpReq()) {
                    $token = $uinfo->getOtpToken();
                    return $this->render("@app/views/site/otp-auth", ['token' => $token, 'msg' => '', 'email' => self::maskEmail($uinfo->getOtpMsgRef()['email'])]);
                }
                // Force Change Password if required
                if ($uinfo->getPwdForceChange()) {
                    return $this->render("@app/views/site/pwd-force-change", ['token' => $uinfo->getPwdForceChangeToken(), 'msg' => '', 'redirect' => false]);
                }
                // Create User Session
                \yii::$app->session->open();
                \yii::$app->session['authid'] = $uinfo->getAuth_ID();
                return $this->redirect(\yii\helpers\Url::toRoute(['/cwf/fwShell/main/index', 'core-sessionid' => $uinfo->getSession_ID()]));
            } else {
                \yii::$app->response->clear();
                return $this->render('login', ['model' => $model]);
            }
        } else {
            // provide the login screen to the user (with any oauth messages)
            $oauthMsg = \yii::$app->session->getFlash('OauthMsg');
            if (isset($oauthMsg)) {
                $model->msg = $oauthMsg;
                \yii::$app->session->removeAllFlashes();
            }
            \Yii::$app->session->destroy(); // This would destroy any cached session
            return $this->render('login', ['model' => $model]);
        }
    }

    public function actionOtp() {
        $token = \yii::$app->request->post('token');
        $otp = \yii::$app->request->post('otp');
        $email = \yii::$app->request->post('email');
        if (\app\cwf\vsla\security\PreAuth::validOTP($token, $otp)) {
            $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
            // Create User Session
            \yii::$app->session->open();
            \yii::$app->session['authid'] = $uinfo->getAuth_ID();
            return $this->redirect(\yii\helpers\Url::toRoute(['/cwf/fwShell/main/index', 'core-sessionid' => $uinfo->getSession_ID()]));
        } else {
            return $this->render("@app/views/site/otp-auth", ['token' => $token, 'msg' => 'Incorrect or expired OTP', 'email' => $email]);
        }
    }

    public function actionPwdForceChange() {
        $pwr = new PassReset();
        $token = \yii::$app->request->post('token');
        $pwr->passkey = \yii::$app->request->post('pwd');
        $pwr->passkey2 = \yii::$app->request->post('pwd_repeat');
        // Validate existing password with new and break if same
        if (PassReset::compareNewPassWithOld($token, $pwr->passkey)) {
            return $this->render("@app/views/site/pwd-force-change", ['token' => $token, 'msg' => 'New password cannot be same as old password. Provide new password', 'redirect' => false]);
        }
        // Validate new password
        $pwr->validate();
        if (count($pwr->error) > 0) {
            return $this->render("@app/views/site/pwd-force-change", ['token' => $token, 'msg' => $pwr->error[0], 'redirect' => false]);
        } else {
            PassReset::validateResetRequest($token);
            $pwdHash = \Yii::$app->getSecurity()->generatePasswordHash($pwr->passkey);
            $pwrResult = PassReset::resetPass($token, $pwdHash);
            if ($pwrResult == 'OK') {
                PassReset::pwdForceChangeToggle($token);
                return $this->render("@app/views/site/pwd-force-change", ['token' => '', 'msg' => 'Password changed successfully', 'redirect' => true]);
            } else {
                return $this->render("@app/views/site/pwd-force-change", ['token' => $token, 'msg' => 'Password change failed. Please resubmit change request', 'redirect' => false]);
            }
        }
    }

    public function actionLogout() {
        if (\app\cwf\vsla\security\SessionManager::getAuthStatus()) {
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->logout();
            \Yii::$app->cache->flush();
            \Yii::$app->session->destroy();
        } else {
            \Yii::$app->cache->flush();
            \Yii::$app->session->destroy();
        }
        return $this->redirect(\yii\helpers\Url::home(true));
    }

    public function actionContact() {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');
            return $this->refresh();
        } else {
            return $this->render('contact', ['model' => $model]);
        }
    }

    public function actionAbout() {
        return $this->render('@app/cwf/fwShell/views/About');
    }

    public function actionPpolicy() {
        return $this->render('privacypolicy');
    }

    public function actionForgotpassword() {
        $this->layout = '/main.php';
        return $this->render('pwreset');
    }

    public function actionReqresetpass() {
        $username = Yii::$app->request->getBodyParam('username');
        $webadd = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' .
                $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . '?r=site/valreqreset&id=';
        $result = PassReset::requestReset($username, $webadd);
        if ($result['status'] == 'OK') {
            return $this->render('error', ['message' => 'The password reset link has been sent.']);
        } else {
            return $this->render('error', ['message' => 'User not found for given username.']);
        }
    }

    public function actionValreqreset($id) {
        $model = new PassReset();
        $model->id = $id;
        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                $resetid = $model->id;
                $passkey = $model->passkey;
                $passkey2 = $model->passkey2;
                if ($passkey == '') {
                    //$model->addError('passkey', 'Password can not be blank.');
                    array_push($model->error, 'Password can not be blank.');
                }
                if ($passkey != $passkey2) {
                    //$model->addError('passkey', 'Passwords do not match.');
                    array_push($model->error, 'Password can not be blank.');
                }
                $res = $this->pwdstrength($passkey);
                if (count($res) != 0) {
                    foreach ($res as $err) {
                        //$model->addError('passkey', $err);
                        array_push($model->error, $err);
                    }
                }

                if (count($model->errors) == 0) {
                    $result = PassReset::resetPass($resetid, \Yii::$app->getSecurity()->generatePasswordHash($passkey));
                    if ($result == 'OK') {
                        return $this->render('error', ['message' => 'Password reset successfully. Now login using new password.']);
                    } else {
                        //$model->addError('passkey', 'Server error.');
                        array_push($model->error, 'Server error.');
                        return $this->render('newpass', ['model' => $model]);
                    }
                } else {
                    return $this->render('newpass', ['model' => $model]);
                }
            } else {
                return $this->render('newpass', ['model' => $model]);
            }
        } else {
            $result = \app\cwf\fwShell\models\PassReset::validateResetRequest($id);
            if ($result == 'OK') {
                return $this->render('newpass', ['model' => $model]);
            } else {
                return $this->render('error', ['message' => 'The link has expired. Please request a new link.']);
            }
        }
    }

    public function actionResetpass() {
        $resetid = Yii::$app->request->getBodyParam('resetid');
        $passkey = Yii::$app->request->getBodyParam('passkey');
        $passkey2 = Yii::$app->request->getBodyParam('passkey2');
        if ($passkey == '') {
            return $this->render('error', ['message' => 'Password can not be blank.']);
        }
        if ($passkey != $passkey2) {
            return $this->render('error', ['message' => 'Password do not match.']);
        }
        $res = $this->pwdstrength($passkey);
        if (count($res) != 0) {
            return $this->render('error', ['message' => $res]);
        }
        $result = PassReset::resetPass($resetid, $passkey, $passkey2);
        if ($result == 'OK') {
            return $this->render('error', ['message' => 'Password reset successfully. Now login using new password.']);
        } else {
            return $this->render('error', ['message' => 'Server error. Password not reset. Please request reset link.']);
        }
    }

    private function pwdstrength($password) {
        $rules['min_length'] = 8;
        $rules['max_length'] = 64;
        $policy = new \PasswordPolicy($rules);
        $policy->min_lowercase_chars = 1;
        $policy->min_uppercase_chars = 1;
        $policy->min_numeric_chars = 1;
        $valid = $policy->validate($password);
        $result = array();
        if (!$valid) {
            $result = $policy->get_errors();
        }
        return $result;
    }

    public function actionTimezone() {
        $dtz = new \DateTimeZone('Europe/Berlin');
        $time = new \DateTime("now", $dtz);
        return $time->format('P');
    }

    public function actionAuthToken() {
        $qp = \yii::$app->request->getQueryParams();
        if (array_key_exists('username', $qp)) {
            $username = $qp['username'];
        } else {
            return json_encode(['status' => 'FAIL', 'reason' => 'Username missing']);
        }
        if (array_key_exists('userpass', $qp)) {
            $userpass = $qp['userpass'];
        } else {
            return json_encode(['status' => 'FAIL', 'reason' => 'Password missing']);
        }
        if (array_key_exists('macaddr', $qp)) {
            $macaddr = str_replace(":", "", $qp['macaddr']);
        } else {
            return json_encode(['status' => 'FAIL', 'reason' => 'MAC Address missing']);
        }
        if ($username != '' && $userpass != '' && $macaddr != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select user_id, user_name, user_pass, mac_addr From sys.user Where user_name = :pusername And mac_addr @> ARRAY[:pmacaddr]::Varchar[]");
            $cmm->addParam('pusername', $username);
            $cmm->addParam('pmacaddr', $macaddr);
            $dtResult = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            if (count($dtResult->Rows()) == 1) {
                if (\yii::$app->getSecurity()->validatePassword($userpass, $dtResult->Rows()[0]['user_pass'])) {
                    $token = uniqid();
                    $cmmAuth = new \app\cwf\vsla\data\SqlCommand();
                    $cmmAuth->setCommandText('Select * From sys.sp_user_token_create(:puser_id, :ptoken)');
                    $cmmAuth->addParam('puser_id', $dtResult->Rows()[0]['user_id']);
                    $cmmAuth->addParam('ptoken', $token);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmmAuth, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                    return json_encode(['status' => 'OK', 'route' => '?r=site/app-token&token=' . $token]);
                } else {
                    return json_encode(['status' => 'FAIL', 'reason' => 'Incorrect username/password']);
                }
            } else {
                return json_encode(['status' => 'FAIL', 'reason' => 'Incorrect username or invalid MAC']);
            }
        }
        return json_encode(['status' => 'FAIL', 'reason' => 'Incorrect username/password/MAC']);
    }

    public function actionAppToken($token) {
        // Check if user has already logged in then use existing auth info          
        // This is already set in app_start event
        if (\app\cwf\vsla\security\SessionManager::getAuthStatus()) {
            return $this->redirect(\yii\helpers\Url::toRoute('/cwf/fwShell/main/index'));
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select pusername from sys.sp_user_token_valid(:ptoken)');
        $cmm->addParam('ptoken', $token);
        $dtpreauth = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dtpreauth->Rows()) == 1) {
            $authInfo = new \app\cwf\vsla\security\AuthInfo();
            $authInfo->userName = $dtpreauth->Rows()[0]['pusername'];
            $authInfo->token = $token;
            \app\cwf\vsla\security\SessionManager::getInstance($authInfo);
            $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
            if ($userinfo->getAuthStatus()) {
                // Gets the authentication session id and adds it to the cookie
                $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
                \yii::$app->session->open();
                \yii::$app->session['authid'] = $uinfo->getAuth_ID();
                return $this->redirect(\yii\helpers\Url::toRoute(['/cwf/fwShell/main/index', 'core-sessionid' => $uinfo->getSession_ID()]));
            }
        }
        // return simple response only
        return 'Invalid Token. Authentication failed!';
    }

    private static function maskEmail(string $email) {
        $em = explode("@", $email);
        $len = strlen($em[0]);

        if ($len >= 2) {
            return substr($em[0], 0, 2) . str_repeat('*', $len - 2) . "@" . end($em);
        } else {
            return str_repeat('*', $len) . "@" . end($em);
        }
    }

}
