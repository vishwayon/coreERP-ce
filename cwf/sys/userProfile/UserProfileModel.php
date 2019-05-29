<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userProfile;

class UserProfileModel extends yii\base\Model {
    public $full_user_name = '';
    public $password = '';
    public $new_password = '';
    public $new_password_confirm = '';
    
    private $policy;
    
    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['password', 'new_password', 'new_password_confirm'], 'required'],
            ['password', 'validatePassword'],
            ['new_password', 'validateNewPassword']
        ];
    }
    
    public function validatePassword($attribute, $params) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id, password From sys.user where user_id=:puser_id');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $valid = \Yii::$app->getSecurity()->validatePassword($this->password, $dt->Rows()[0]['user_pass']);
        if(!$valid) {
            $this->addError('password', 'User password incorrect. Cannot set a new password');
        }
    }
    
    public function validateNewPassword($attribute, $params) {
        if($this->new_password != $this->new_password_confirm) {
            $this->addError('new_password_confirm', 'Confirm password does not match new password.');
            return;
        }        
        
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
}