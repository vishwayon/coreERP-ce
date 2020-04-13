<?php

namespace app\cwf\fwShell\models;

use yii\base\Model;

class UserCaptcha extends Model {
    
    public $username;
    public $verifyCode;
    public $full_user_name = '';
    public $msg = '';
    
    /**
     * @return array the validation rules.
     */
    public function rules() {
        return [
            // username captcha required for user-captcha
            [['username', 'verifyCode'], 'required'],
            // verifyCode needs to be entered correctly
            ['verifyCode', 'captcha']
        ];
    }
    
    /**
     * @return array customized attribute labels
     */
    public function attributeLabels() {
        return [
            'verifyCode' => 'Verification Code',
        ];
    }
    
    /**
     * This method validates the user if the entered captcha is valid
     */
    public function validUser() {
        if (!$this->hasErrors()) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select user_id, full_user_name from sys.user where user_name = :puname And is_active');
            $cmm->addParam('puname', $this->username);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            if (count($dt->Rows()) == 1) {
                $this->full_user_name = $dt->Rows()[0]['full_user_name'];
                return true;
            }
        }
        return false;
    }

}
