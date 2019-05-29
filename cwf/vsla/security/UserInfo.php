<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\security;

use app\cwf\vsla\data\SqlCommand;
use app\cwf\vsla\data\DataConnect;

/**
 * Class used to hold the Authenticated UserInfo
 *
 * @author girish
 */
class UserInfo {

    //put your code here
    private $Auth_ID = null;
    private $Session_ID = null;
    private $FullUserName = '';
    private $User_ID = -1;
    private $UserName = '';
    private $SessionVariables = array();
    private $failedMessage = '';
    private $otp_req = FALSE;
    private $otp_token = '';
    private $otp_msg_ref = [];
    private $pwdForceChange = FALSE;
    private $pwdForceChangeToken = '';

    public function __construct() {
        $this->SessionVariables['company_id'] = -1;
        $this->SessionVariables['domain_id'] = -1;
        $this->SessionVariables['company_name'] = '';
        $this->SessionVariables['company_short_name'] = '';
        $this->SessionVariables['companyDB'] = '';
        $this->SessionVariables['user_time_zone'] = 'Asia/Kolkata';

        $this->SessionVariables['branch_id'] = -1;
        $this->SessionVariables['branch_name'] = '';

        $this->SessionVariables['finyear_id'] = -1;
        $this->SessionVariables['finyear'] = '';
        $this->SessionVariables['year_begin'] = '1970-01-01';
        $this->SessionVariables['year_end'] = '1970-01-01';

        $this->SessionVariables['date_format'] = 'dd/mm/yyyy';
        $this->SessionVariables['currency_system'] = 'm';
        $this->SessionVariables['is_owner'] = false;
        $this->SessionVariables['is_admin'] = false;
        $this->SessionVariables['is_mobile'] = false;
    }

    /**
     * Authenticates the user
     * @param AuthInfo $authInfo
     * @return app\cwf\vsla\security\UserInfo
     */
    public static function AuthLogin($authInfo) {
        if (\yii::$app->has('userAuth')) {
            $uinfo = \yii::$app->get('userAuth');
        } else {
            $uinfo = new UserInfo();
        }
        $uinfo->authenticate($authInfo);
        return $uinfo;
    }

    protected function authenticate(AuthInfo $authInfo) {
        if ($authInfo->auth_id != '' && $authInfo->session_id != '') {
            $this->retreiveFromDB($authInfo); // complete session. Retreive from db
        } else if ($authInfo->auth_id != '' && $authInfo->session_id == '') {
            // Auth exits, new session requested.
            $cmm = new SqlCommand();
            $cmm->setCommandText("Select a.user_id, a.full_user_name, a.user_name, a.is_owner, a.is_admin
                    From sys.user a Inner Join sys.user_session b On a.user_id=b.user_id Where b.auth_id=:pauth_id Limit 1;");
            $cmm->addParam("pauth_id", $authInfo->auth_id);
            $dtResult = DataConnect::getData($cmm, DataConnect::MAIN_DB);
            if (count($dtResult->Rows()) == 1) {
                $rw = $dtResult->Rows()[0];
                $this->Auth_ID = $authInfo->auth_id;
                $this->Session_ID = uniqid(); // Creates a new session to start with
                $this->UserName = $rw['user_name'];
                $this->User_ID = $rw['user_id'];
                $this->FullUserName = $rw['full_user_name'];
                $this->SessionVariables['is_admin'] = $rw['is_admin'];
                $this->SessionVariables['is_owner'] = $rw['is_owner'];
                $this->persistToDB();
            }
        } else if ($authInfo->userName !== '' && ($authInfo->userPass !== '' || $authInfo->person_id !== '' || $authInfo->token !== '')) {
            // First time authentication
            $par = \app\cwf\vsla\security\PreAuth::allowLogin($authInfo->userName);
            if (!$par->allow) {
                $ip = \yii::$app->request->getUserIP();
                $this->setFailedMessage("User not allowed access from the client IP[$ip]. Login failed");
                return;
            }

            // Pre-Auth successful. Start authentication process
            $cmm = new SqlCommand();
            $cmm->setCommandText("Select user_id, user_name, user_pass, full_user_name, is_owner, is_admin, 
                        email, Coalesce((user_attr->>'pwd_force_change')::bool, false) pwd_force_change,
                        Coalesce((user_attr->>'otp_req')::bool, false) otp_req,
                        Coalesce((user_attr->>'en_otp_req_type')::int, 101) en_otp_req_type
                    From sys.user 
                    Where user_name=:puser_name And is_active");
            $cmm->addParam("puser_name", $authInfo->userName);
            $dtResult = DataConnect::getData($cmm, DataConnect::MAIN_DB);
            if (count($dtResult->Rows()) == 1) {
                $validUser = false;
                $drUser = $dtResult->Rows()[0];
                // First try with password
                if ($authInfo->userPass !== '') {
                    $validUser = $this->authWithPass($authInfo, $drUser['user_pass']);
                } elseif ($authInfo->person_id !== '') {
                    // pre authenticated user from OAuth
                    $validUser = true;
                } elseif ($authInfo->token !== '') {
                    // pre authenticated user from external client
                    $validUser = true;
                }
                if (!$validUser) {
                    $this->setFailedMessage('Username or password is incorrect. Login failed.');
                    \app\cwf\vsla\security\PreAuth::logFailedLogin($authInfo->userName, $this->failedMessage);
                }

                // look for ip restrictions (deprecated with merger of security\PreAuth)
                /* if($validUser && !(bool)$dr['is_admin'] && !(bool)$dr['is_owner']) {
                  if(\yii::$app->has('restrictIP')) {
                  $rip = \Yii::$app->get('restrictIP');
                  } else {
                  $rip = new RestrictIP();
                  }
                  if(!$rip->validateRequest($dr['user_id'])) {
                  $validUser = false;
                  $this->setFailedMessage('User not allowed access from the current IP. Login failed.');
                  }
                  }
                  // If non Token auth, look if mac id is required
                  if($authInfo->person_id == '' && $authInfo->token == '') {
                  $cmmMac = new SqlCommand();
                  $cmmMac->setCommandText('Select is_mac_addr from sys.user Where user_name=:puser_name');
                  $cmmMac->addParam("puser_name", $authInfo->userName);
                  $dtMac = DataConnect::getData($cmmMac, DataConnect::MAIN_DB);
                  if(count($dtMac->Rows())==1) {
                  if(boolval($dtMac->Rows()[0]['is_mac_addr'])) {
                  $validUser = false;
                  $this->setFailedMessage('User login restricted by MAC address. Login failed. Use CoreERP Auth App to login.');
                  }
                  }
                  } */
                // Finally, if the user is valid, then create a session
                if ($validUser) {
                    $this->Auth_ID = uniqid(); // Create a default auth id to start with
                    $this->Session_ID = uniqid(); // Create a default session to start with
                    $this->UserName = $drUser['user_name'];
                    $this->User_ID = $drUser['user_id'];
                    $this->FullUserName = $drUser['full_user_name'];
                    $this->SessionVariables['is_admin'] = $drUser['is_admin'];
                    $this->SessionVariables['is_owner'] = $drUser['is_owner'];
                    $this->SessionVariables['is_mobile'] = $authInfo->is_mobile;
                    $this->persistToDB();

                    // Send OTP, if required
                    if ($drUser['otp_req']) {
                        if ($drUser['en_otp_req_type'] == 102) {
                            // OTP required as user is Not Within Restricted IP
                            if (!$par->restrictIP) {
                                $this->otp_req = TRUE;
                                $this->otp_token = \app\cwf\vsla\security\PreAuth::sendOTP($this->User_ID, $this->Auth_ID, $drUser['email']);
                                $this->otp_msg_ref = [
                                    'email' => $drUser['email']
                                ];
                            }
                        } else {
                            // Always request OTP
                            $this->otp_req = TRUE;
                            $this->otp_token = \app\cwf\vsla\security\PreAuth::sendOTP($this->User_ID, $this->Auth_ID, $drUser['email']);
                            $this->otp_msg_ref = [
                                'email' => $drUser['email']
                            ];
                        }
                    }

                    // Force password reset if required
                    if ($drUser['pwd_force_change']) {
                        $this->pwdForceChange = TRUE;
                        $pwr = new \app\cwf\fwShell\models\PassReset();
                        $reqResult = $pwr->requestReset($drUser['user_name'], '');
                        if ($reqResult['status'] == 'OK') {
                            $this->pwdForceChangeToken = $reqResult['token'];
                        } else {
                            throw new Exception($reqResult['msg']);
                        }
                    }
                }
            } else {
                $this->setFailedMessage('Invalid user or user not authenticated');
            }
        }
    }

    protected function authWithPass(AuthInfo $authInfo, $pswdHash) {
        return \yii::$app->getSecurity()->validatePassword($authInfo->userPass, $pswdHash);
    }

    /**
     * Returns the unique session id
     * @return stringGUID
     */
    public function getSession_ID() {
        return $this->Session_ID;
    }

    /**
     * Returns the unique auth id
     * @return stringGUID
     */
    public function getAuth_ID() {
        return $this->Auth_ID;
    }

    /**
     * Returns the Full User Name
     * @return string
     */
    public function getFullUserName() {
        return $this->FullUserName;
    }

    /**
     * Returns the authentication status
     * @return bool
     */
    public function getAuthStatus() {
        return $this->Auth_ID != null;
    }

    /**
     * Returns the Session status. States whether Company, Branch and FinYear are selected
     * @return bool
     */
    public function getSessionCreated() {
        if ($this->SessionVariables['company_id'] != -1 && $this->SessionVariables['branch_id'] != -1 && $this->SessionVariables['finyear_id'] != -1) {
            return true;
        }
        return false;
    }

    /**
     * Returns the UserName
     * @return string
     */
    public function getUserName() {
        return $this->UserName;
    }

    /**
     * Returns the User ID
     * @return Int
     */
    public function getUser_ID() {
        return $this->User_ID;
    }

    /**
     * Returns the Company ID
     * @return int
     */
    public function getCompany_ID() {
        return $this->getSessionVariable('company_id');
    }

    /**
     * Returns if user is Admin
     * @return boolean
     */
    public function isAdmin() {
        return $this->getAuthStatus() && (bool) $this->SessionVariables['is_admin'];
    }

    /**
     * Returns if user is Owner
     * @return boolean
     */
    public function isOwner() {
        return (bool) $this->SessionVariables['is_owner'];
    }

    /**
     * Returns a session variable value based on key
     * Will generate index error if key is not found
     * @return mixed
     */
    public function getSessionVariable($key) {
        return $this->SessionVariables[$key];
    }

    /**
     * Returns if session contains the variable
     * @return mixed
     */
    public function hasSessionVariable($key) {
        return array_key_exists($key, $this->SessionVariables);
    }

    /**
     * Sets a session variable value based on key
     * The key should pre-exist in the session
     * Throws exception if key is not found
     * @return mixed
     */
    public function setSessionVariable($key, $value) {
        if ($this->hasSessionVariable($key)) {
            $this->SessionVariables[$key] = $value;
        } else {
            throw new \Exception($key . ' not found in session collection.');
        }
    }

    /**
     * Adds a session variable value based on key
     * If key is found, it is updated
     * @return mixed
     */
    public function addSessionVariable($key, $value) {
        $this->SessionVariables[$key] = $value;
    }

    /**
     * Set the login failed message
     * @var string
     */
    public function setFailedMessage($msg) {
        $this->failedMessage = $msg;
    }

    /**
     * Returns login failed message
     * @return string
     */
    public function getFailedMessage() {
        return $this->failedMessage;
    }

    /**
     * Returns True if OTP is required for user-Auth
     */
    public function getOtpReq(): bool {
        return $this->otp_req;
    }

    /**
     * Returns the OtpToken that can be sent to the client
     * @return string 
     */
    public function getOtpToken(): string {
        return $this->otp_token;
    }

    /**
     * Returns otp msg ref for display to user
     * @return array
     */
    public function getOtpMsgRef(): array {
        return $this->otp_msg_ref;
    }

    /**
     * Returns whether the user is required to change password
     * @return bool
     */
    public function getPwdForceChange(): bool {
        return $this->pwdForceChange;
    }

    /**
     * Returns the password change token that can be sent to the client
     * @return string
     */
    public function getPwdForceChangeToken(): string {
        return $this->pwdForceChangeToken;
    }

    protected function persistToDB() {
        // First persist to Database
        $cmm = new SqlCommand();
        $cmmText = "Insert Into sys.user_session(user_session_id, auth_id, user_id, login_time, last_refresh_time, session_variables)"
                . " values (:puser_session_id, :pauth_id, :puser_id, current_timestamp(0), current_timestamp(0), :psession_variables); ";
        $cmm->setCommandText($cmmText);
        $cmm->addParam("puser_session_id", $this->Session_ID);
        $cmm->addParam("pauth_id", $this->Auth_ID);
        $cmm->addParam("puser_id", $this->User_ID);
        $cmm->addParam("psession_variables", json_encode($this->SessionVariables));
        DataConnect::exeCmm($cmm, null, DataConnect::MAIN_DB);

        // Next persist to APCu cache
        $ssinfo = [];
        $ssinfo['user_name'] = $this->UserName;
        $ssinfo['user_id'] = $this->User_ID;
        $ssinfo['full_user_name'] = $this->FullUserName;
        $ssinfo['session_variables'] = $this->SessionVariables;
        $csinfo = json_encode($ssinfo);
        if (\yii::$app->has('apcCache')) {
            \yii::$app->apcCache->add($this->Session_ID, $csinfo);
        }
    }

    public function persistSessionVariables() {
        // First update database
        $cmm = new SqlCommand();
        $cmmText = "update sys.user_session "
                . " Set session_variables=:psession_variables"
                . " Where user_session_id=:puser_session_id; ";
        $cmm->setCommandText($cmmText);
        $cmm->addParam("puser_session_id", $this->Session_ID);
        $cmm->addParam("psession_variables", json_encode($this->SessionVariables));
        DataConnect::exeCmm($cmm, null, DataConnect::MAIN_DB);

        // Next update APCu Cache
        if (\yii::$app->has('apcCache')) {
            $csinfo = \yii::$app->apcCache->get($this->Session_ID);
            $ssinfo = json_decode($csinfo, true);
            $ssinfo['session_variables'] = $this->SessionVariables;
            $csinfo = json_encode($ssinfo);
            \yii::$app->apcCache->set($this->Session_ID, $csinfo);
        }
    }

    /**
     * @param AuthInfo $authInfo
     */
    protected function retreiveFromDB($authInfo) {
        // first check APCu cache
        $csinfo = false;
        if (\yii::$app->has('apcCache')) {
            $csinfo = \yii::$app->apcCache->get($authInfo->session_id);
        }
        if ($csinfo != false) {
            $ssinfo = json_decode($csinfo, true);
            $this->Session_ID = $authInfo->session_id;
            $this->Auth_ID = $authInfo->auth_id;
            $this->UserName = $ssinfo['user_name'];
            $this->User_ID = $ssinfo['user_id'];
            $this->FullUserName = $ssinfo['full_user_name'];
            $this->SessionVariables = $ssinfo['session_variables'];
        } else {
            // fetch information from database
            $cmm = new SqlCommand();
            $cmmText = "Select * From sys.sp_user_session_get(:puser_session_id);";
            $cmm->setCommandText($cmmText);
            $cmm->addParam("puser_session_id", $authInfo->session_id);
            $dtResult = DataConnect::getData($cmm, DataConnect::MAIN_DB);
            if (count($dtResult->Rows()) == 1) {
                $rw = $dtResult->Rows()[0];
                $this->Session_ID = $authInfo->session_id;
                $this->Auth_ID = $authInfo->auth_id;
                $this->UserName = $rw['user_name'];
                $this->User_ID = $rw['user_id'];
                $this->FullUserName = $rw['full_user_name'];
                $this->SessionVariables = json_decode($rw['session_variables'], true);

                // Next persist to APCu cache
                if (\yii::$app->has('apcCache')) {
                    $ssinfo = [];
                    $ssinfo['user_name'] = $this->UserName;
                    $ssinfo['user_id'] = $this->User_ID;
                    $ssinfo['full_user_name'] = $this->FullUserName;
                    $ssinfo['session_variables'] = $this->SessionVariables;
                    $csinfo = json_encode($ssinfo);
                    \yii::$app->apcCache->add($this->Session_ID, $csinfo);
                }
            }
        }
    }

    public function logout() {
        // logout from database
        $cmm = new SqlCommand();
        $cmm->setCommandText('Select * from sys.sp_user_logout_set(:puser_session_id)');
        $cmm->addParam("puser_session_id", $this->Session_ID);
        DataConnect::exeCmm($cmm, null, DataConnect::MAIN_DB);
    }

}
