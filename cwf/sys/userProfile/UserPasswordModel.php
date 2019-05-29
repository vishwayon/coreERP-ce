<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userProfile;
use app\cwf\vsla\security\PasswordPolicy;

class UserPasswordModel extends \yii\base\Model {
    public $full_user_name = '';
    public $password = '';
    public $new_password = '';
    public $confirm_password = '';
    
    private $policy;
    
    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['password', 'new_password', 'confirm_password'], 'required'],
            ['password', 'validatePassword']
        ];
    }
    
    public function validatePassword($attribute, $params) {
        // Ensure that existing pass is correct
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id, user_pass From sys.user where user_id=:puser_id');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $valid = \Yii::$app->getSecurity()->validatePassword($this->password, $dt->Rows()[0]['user_pass']);
        if(!$valid) {
            $this->addError('password', 'User password incorrect. Cannot set a new password');
            return;
        }
        // return if new and confirm do not match
        if($this->new_password != $this->confirm_password) {
            $this->addError('confirm_password', 'New password does not match confirm password.');
            return;
        }        
        
        // ensure that all rules are obeyed
        $rules['min_length'] = 8;
        $rules['max_length'] = 64;
        $this->policy = new PasswordPolicy($rules);
        $this->policy->min_lowercase_chars = 1;
        $this->policy->min_uppercase_chars = 1;
        $this->policy->min_numeric_chars = 1;  
        
        $valid = $this->policy->validate($this->new_password);
        if(!$valid) {
            $this->addError('new_password', 'New password must be atleast 8 characters and contain atleast 1 lowercase, 1 uppercase and 1 numberic character');
        }
    }
    
    public function changePassword() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Update sys.user Set user_pass=:puser_pass Where user_id=:puser_id';
        $cmm->setCommandText($sql);
        $cmm->addParam('puser_pass', \Yii::$app->getSecurity()->generatePasswordHash($this->new_password));
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }
}