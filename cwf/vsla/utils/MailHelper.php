<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of MailHelper
 *
 * @author priyanka
 */
class MailHelper {

    //put your code here
    public static function SendMail($mail_to, $mail_from, $body, $subject, $cc, $bcc, $reply_to) {
        if ($mail_from == '') {
            $mail_from = 'noreply@coreerp.com';
        }
        if ($mail_to == '') {
            return;
        } else {
            if (!self::mailid_valid($mail_to)) {
                throw new Exception("Invalid Mail id. Does not comply with RFC 5322 standard");
            }
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.sp_notification_mail_add(:pmail_to, :pmail_from, :pbody, :psubject, :pcc, :pbcc, :preply_to)');
        $cmm->addParam('pmail_to', $mail_to);
        $cmm->addParam('pmail_from', $mail_from);
        $cmm->addParam('pbody', $body);
        $cmm->addParam('psubject', $subject);
        $cmm->addParam('pcc', $cc);
        $cmm->addParam('pbcc', $bcc);
        $cmm->addParam('preply_to', $reply_to);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }

    public static function SendMailAttachment($mail_to, $mail_from, $body, $subject, $cc, $bcc, $reply_to, $atchmt) {
        if ($mail_from == '') {
            $mail_from = 'noreply@coreerp.com';
        }
        if ($mail_to == '') {
            return;
        } else {
            if (!self::mailid_valid($mail_to)) {
                throw new Exception("Invalid Mail id. Does not comply with RFC 5322 standard");
            }
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.sp_notification_mail_atch_add(:pmail_to, :pmail_from, :pbody, :psubject, :pcc, :pbcc, :preply_to, :pattach_path)');
        $cmm->addParam('pmail_to', $mail_to);
        $cmm->addParam('pmail_from', $mail_from);
        $cmm->addParam('pbody', $body);
        $cmm->addParam('psubject', $subject);
        $cmm->addParam('pcc', $cc);
        $cmm->addParam('pbcc', $bcc);
        $cmm->addParam('preply_to', $reply_to);
        $cmm->addParam('pattach_path', $atchmt);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }

    /**
     * Validates if the mail id complies with RFC 5322 standard
     * @param string $mailid
     */
    public static function mailid_valid($mailid) {
        $mids = explode(",", $mailid);
        foreach ($mids as $mid) {
            if (!preg_match("/^[A-Za-z0-9._-]+@[A-Za-z0-9._-]+\.[A-Za-z]{2,4}$/", $mid)) {
                return false;
            }
        }
        return true;
    }
    
    public static function SendWarningMail($subject, $body) {
        $to = \yii::$app->params['cwf_config']['exceptionMail']['to'];
        $from = \yii::$app->params['cwf_config']['exceptionMail']['from'];

        $dtm = new \DateTime();
        $dtz = new \DateTimeZone("Asia/Kolkata");
        $dtm->setTimezone($dtz);
        MailHelper::SendMail($to, $from, $body, $subject.' : '.$dtm->format('Y-m-d H:i:s T'), '', '', '');
    }

}
