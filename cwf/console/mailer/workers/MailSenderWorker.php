<?php

namespace app\cwf\console\mailer\workers;

use yii\base\ErrorException;

class MailSenderWorker {

    // method declaration
    public function Start($dbServer, $dbUser, $dbMain, $dbPass) {

        try {

            $dbh = new \PDO('pgsql:host=' . $dbServer . ' user=' . $dbUser . ' dbname=' . $dbMain . ' password=' . $dbPass . '');

            echo "Reading data..\n";
            while (true) {

                $sql = "Select * from sys.notification_mail where is_send=0 order by notification_mail_id asc limit 5";

                $query = $dbh->query($sql);
                $rows = $query->fetchAll();
                $query = null;
                if (count($rows) == 0) {
                    break;
                }

                foreach ($rows as $row) {
                    $notification_mail_id = $row['notification_mail_id'];
                    if ($this->validate($row)) {
                        $subject = $row['subject'];
                        $mailTo = explode(',', trim($row['mail_to']));
                        $mailFrom = trim($row['mail_from']);
                        $body = $row['body'];
                        $reply_to = $row['reply_to'];
                        $cc = ($row['cc'] == NULL || $row['cc'] == '') ? [] : explode(',', trim($row['cc']));
                        $bcc = ($row['bcc'] == NULL || $row['bcc'] == '') ? [] : explode(',', trim($row['bcc']));
                        $attach = $row['attachment_path'];

                        echo "Sending Mail...\n";
                        echo "mailFrom: " . $mailFrom . "\n";
                        echo "mailTo: " . implode(',', $mailTo) . "\n";
                        echo "reply_to: " . $reply_to . "\n";
                        echo "cc_to: " . implode(',', $cc) . "\n";
                        echo "\n";
                        try {
                            $email = \yii::$app->mailer->compose()
                                    ->setFrom($mailFrom)
                                    ->setTo($mailTo)
                                    ->setSubject($subject)
                                    ->setHtmlBody($body);

                            if ($reply_to != '') {
                                $email = $email->setReplyTo($reply_to);
                            }
                            if (count($cc) > 0) {
                                $email = $email->setCc($cc);
                            }
                            if (count($bcc) > 0) {
                                $email = $email->setBcc($bcc);
                            }
                            if ($attach != '') {
                                $email->attach($attach);
                            }
                            $email->send();
                            $update = 'Update sys.notification_mail SET is_send=1 WHERE notification_mail_id= ' . $notification_mail_id;
                            $dbh->exec($update);
                            echo 'Sent Notification Mail ID - ' . $notification_mail_id . "\n";
                            // provide proper breaks before sending next mail. Else, server may term it as spam
                            usleep(50000);
                        } catch (\Swift_TransportException $ex) {
                            echo 'Transport exception. Email not sent. notification_mail_id= ' . $notification_mail_id;
                        } catch (\Swift_SwiftException $ex) {
                            $update = 'Update sys.notification_mail SET is_send=501 WHERE notification_mail_id= ' . $notification_mail_id;
                            $dbh->exec($update);
                            echo 'Not Sent Notification Mail ID - ' . $notification_mail_id . ' err:' . $ex->getMessage() . "\n";
                        } catch (\Exception $ex) {
                            $update = 'Update sys.notification_mail SET is_send=99 WHERE notification_mail_id= ' . $row['notification_mail_id'];
                            $dbh->exec($update);
                            echo 'Not Sent Notification Mail ID - ' . $notification_mail_id . ' err:' . $ex->getMessage() . "\n";
                        }
                    } else {
                        $update = 'Update sys.notification_mail SET is_send=99 WHERE notification_mail_id= ' . $row['notification_mail_id'];
                        $dbh->exec($update);
                    }
                }
            }
            $dbh = null;
        } catch (\Exception $e) {//catches exceptions when connecting to database
            $query = null;
            $dbh = null;
            throw $e;
        }
    }

    private function validate($row) {
        $mailTo = explode(',', trim($row['mail_to']));
        $mailFrom = trim($row['mail_from']);
        $reply_to = $row['reply_to'];
        $cc = ($row['cc'] == NULL || $row['cc'] == '') ? [] : explode(',', trim($row['cc']));
        $bcc = ($row['bcc'] == NULL || $row['bcc'] == '') ? [] : explode(',', trim($row['bcc']));
        foreach ($mailTo as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in MailTo :' . $email . "\n";
                return FALSE;
            }
        }
        if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            echo 'invalid email in MailFrom :' . $mailFrom . "\n";
            return FALSE;
        }
        if ($reply_to != '') {
            if (!filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in ReplyTo :' . $reply_to . "\n";
                return FALSE;
            }
        }
        foreach ($cc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in CC :' . $email . "\n";
                return FALSE;
            }
        }
        foreach ($bcc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in BCC :' . $email . "\n";
                return FALSE;
            }
        }
        return TRUE;
    }

}
