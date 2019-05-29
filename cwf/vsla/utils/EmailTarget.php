<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;


use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\mail\MailerInterface;

/**
 * EmailTarget sends selected log messages to the specified email addresses.
 *
 * You may configure the email to be sent by setting the [[message]] property, through which
 * you can set the target email addresses, subject, etc.:
 *
 * ```php
 * 'components' => [
 *     'log' => [
 *          'targets' => [
 *              [
 *                  'class' => 'yii\log\EmailTarget',
 *                  'mailer' =>'mailer',
 *                  'levels' => ['error', 'warning'],
 *                  'message' => [
 *                      'from' => ['log@example.com'],
 *                      'to' => ['developer1@example.com', 'developer2@example.com'],
 *                      'subject' => 'Log message',
 *                  ],
 *              ],
 *          ],
 *     ],
 * ],
 * ```
 *
 * In the above `mailer` is ID of the component that sends email and should be already configured.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class EmailTarget extends \yii\log\Target
{
    /**
     * @var array the configuration array for creating a [[\yii\mail\MessageInterface|message]] object.
     * Note that the "to" option must be set, which specifies the destination email address(es).
     */
    public $message = [];
    /**
     * @var MailerInterface|array|string the mailer object or the application component ID of the mailer object.
     * After the EmailTarget object is created, if you want to change this property, you should only assign it
     * with a mailer object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $mailer = 'mailer';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
    }

    /**
     * Sends log messages to specified email addresses.
     */
    public function export()
    {        
        if(isset(\yii::$app->params['cwf_config']['exceptionMail'])) {
            if(\app\cwf\vsla\security\SessionManager::getAuthStatus()) {
                $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
                array_unshift($this->messages, ['Finyear: '.$uinfo->getSessionVariable('finyear'), 4, 'application', time()]);
                array_unshift($this->messages, ['Branch: '.$uinfo->getSessionVariable('branch_id'), 4, 'application', time()]);
                array_unshift($this->messages, ['Company: '.$uinfo->getSessionVariable('companyDB'), 4, 'application', time()]);
                array_unshift($this->messages, ['Username: '.$uinfo->getFullUserName().' ('.$uinfo->getUserName().')', 4, 'application', time()]);
            }

            $messages = array_map([$this, 'formatMessage'], $this->messages);
            $body = implode("<br/>", $messages);
            $body = str_replace("\n", '<br/>', (string)$body);
            $body = str_replace(' ', '&nbsp;', (string)$body);
            $body = '<html><body>'.$body.'</body></html>';
            
            
            $to = \yii::$app->params['cwf_config']['exceptionMail']['to'];
            $from = \yii::$app->params['cwf_config']['exceptionMail']['from'];
            
            $dtm = new \DateTime();
            $dtz = new \DateTimeZone("Asia/Kolkata");
            $dtm->setTimezone($dtz);
            MailHelper::SendMail($to, $from, $body, 'CoreERP Exception '.$dtm->format('Y-m-d H:i:s T'), '', '', '');
        }
    }

    /**
     * Composes a mail message with the given body content.
     * @param string $body the body content
     * @return \yii\mail\MessageInterface $message
     */
    protected function composeMessage($body)
    {
        $message = $this->mailer->compose();
        Yii::configure($message, $this->message);
        $message->setTextBody($body);

        return $message;
    }
    
    
}
