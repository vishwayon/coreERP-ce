<?php

namespace app\cwf\fwShell\models;

use yii\base\Model;
use \app\cwf\vsla\security\SessionManager;

class Login extends Model {
    
    public $username;
    public $password;
    public $userinfo;
    public $rememberMe;
    public $msg = '';
    private $_user = false;
    public $is_mobile = false;
    public $full_user_name;

    /**
     * @return array the validation rules.
     */
    public function rules() {
        return [
            // username and password are both required for login
            [['username', 'password'], 'required'],
        ];
    }

    /**odel->
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params) {
        if (!$this->hasErrors()) {
//            $user = $this->getUser();
//
//            if (!$user || !$user->validatePassword($this->password)) {
//                $this->addError($attribute, 'Incorrect username or password.');
//            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return boolean whether the user is logged in successfully
     */
    public function login() {
        if ($this->validate()) {
            $authInfo = new \app\cwf\vsla\security\AuthInfo();
            $authInfo->userName = $this->username;
            $authInfo->userPass = $this->password;
            $authInfo->is_mobile = false;
            SessionManager::getInstance($authInfo);
            $this->userinfo = SessionManager::getInstance()->getUserInfo();
            $this->msg = $this->userinfo->getFailedMessage();
            return $this->userinfo->getAuthStatus();
        } else {
            return false;
        }
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser() {
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }

}
