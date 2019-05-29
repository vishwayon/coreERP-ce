<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * ContactForm is the model behind the contact form.
 */
class ContactForm extends Model {

    public $name;
    public $email;
    public $subject;
    public $body;
    public $verifyCode;

    /**
     * @return array the validation rules.
     */
    public function rules() {
        return [
            // name, email, subject and body are required
            [['name', 'email', 'subject', 'body'], 'required'],
            // email has to be a valid email address
            ['email', 'email'],
            // verifyCode needs to be entered correctly
            ['verifyCode', 'captcha'],
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
     * Sends an email to the specified email address using the information collected by this model.
     * @param  string  $email the target email address
     * @return boolean whether the model passes validation
     */
    public function contact($email) {
        if ($this->validate()) {
            $mail_from = $this->email;
            $mail_to = 'info@coreerp.com';
            $body = 'Name: ' . $this->name . '<br/> Email: ' . $this->email . '<br/><br/>' . $this->body;
            $subject = $this->subject;
            $cc = '';
            $bcc = '';
            $reply_to = '';
            \app\cwf\vsla\utils\MailHelper::SendMail($mail_to, $mail_from, $body, $subject, $cc, $bcc, $reply_to);
            return true;
        } else {
            return false;
        }
    }

}
