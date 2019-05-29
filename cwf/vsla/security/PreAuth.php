<?php

namespace app\cwf\vsla\security;

/**
 * This class contains pre-Auth methods that are used 
 * before authenticating a user login
 *
 * @author girish
 */
class PreAuth {

    /**
     * Call this method before starting the logon authentication 
     * process. It first checks the user ip against restrictIP data
     * and on failure, checks against allow_addr in user_attr
     * @param string $username  Logon user
     * @return bool             Returns true if user ip is in list of allowed ip(s). Else false
     */
    public static function allowLogin(string $username): \stdClass {
        $par = new \stdClass();
        if (static::allowFromRestrictIP()) {
            $par->allow = TRUE;
            $par->restrictIP = TRUE;
            return $par;
        }
        self::logFailedLogin($username, 'Pre-auth restrict ip does not contain user/client ip');
        if (static::allowFromUserAddr($username)) {
            $par->allow = TRUE;
            $par->restrictIP = FALSE;
            $par->userAddr = TRUE;
            return $par;
        }
        self::logFailedLogin($username, 'Pre-auth user-addr does not contain user/client ip');
        $par->allow = FALSE;
        return $par;
    }

    protected static function allowFromRestrictIP(): bool {
        $ip = \yii::$app->request->getUserIP();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as result 
                From sys.restrict_ip
                Where (ip::inet >>= :pip::inet)
                    And (domain ilike :http_host Or domain = '*')");
        $cmm->addParam('pip', $ip);
        $cmm->addParam('http_host', $_SERVER['HTTP_HOST']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            if (intval($dt->Rows()[0]['result']) >= 1) {
                return TRUE;
            }
        }
        return FALSE;
    }

    protected static function allowFromUserAddr(string $userName): bool {
        $ip = \yii::$app->request->getUserIP();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With logon_addr
            As
            (	Select (addr->>'sl_no')::int sl_no, (addr->>'ip')::inet ip
                    From sys.user a, jsonb_array_elements(user_attr->'logon_addr') addr
                    Where user_name = :puser_name
            )
            Select count(*) ip_cnt
            From logon_addr
            Where (ip >>= :pip::inet)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pip', $ip);
        $cmm->addParam('puser_name', $userName);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            if (intval($dt->Rows()[0]['ip_cnt']) >= 1) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * This method sends OTP via e-mail and returns a token that 
     * can be safely sent to the client. This token is required to be resubmitted
     * along with the user OTP for validation
     * @param string $user_id   The User_id
     * @param string $auth_id   The Auth_id
     * @param string $mail_to   User's email address
     * @return string           Returns a token that can be sent to the client. 
     */
    public static function sendOTP(int $user_id, string $auth_id, string $mail_to) : string {
        //generate random otp
        $otp = self::generateOTP();
        $token = md5($user_id.":".$auth_id."".time());
        //store otp in database
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into sys.user_otp(token, user_id, auth_id, otp) Values (:ptoken::uuid, :puser_id, :pauth_id, :potp)");
        $cmm->addParam("puser_id", $user_id);
        $cmm->addParam("pauth_id", $auth_id);
        $cmm->addParam("potp", $otp);
        $cmm->addParam("ptoken", $token);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        //Generate mail to user
        $mail_body = "Your request for login generated the following OTP at coreerp.in<br><br><b>$otp</b><br><br>
                    This OTP is valid for the next <i>10 minutes</i>.";
        $dtm = new \DateTime();
        $dtz = new \DateTimeZone("Asia/Kolkata");
        $dtm->setTimezone($dtz);
        \app\cwf\vsla\utils\MailHelper::SendMail($mail_to, null, $mail_body, "coreERP Auth OTP " . $dtm->format('d-m-Y H:i T'), '', '', '');
        
        return $token;
    }

    /**
     * Validates the given OTP against the previously generated OTP
     * Provided it is not already used and was generated within the last 10 minutes
     * @param string $token     The token returned in sendOTP
     * @param string $otp       The user entered OTP
     * @return bool             Returns True if matched, else false
     */
    public static function validOTP(string $token, string $otp): bool {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Update sys.user_otp
                Set used = true, used_on = current_timestamp(0)
                Where token = :ptoken::uuid
                    And otp = :potp
                    And created_on >= current_timestamp(0) - Interval '10 Minute'
                    And Not used
                Returning user_id, auth_id, otp";
        $cmm->setCommandText($sql);
        $cmm->addParam("ptoken", $token);
        $cmm->addParam("potp", $otp);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            $dr = $dt->Rows()[0];
            if ($dr['otp'] == $otp) {
                $authInfo = new \app\cwf\vsla\security\AuthInfo();
                $authInfo->auth_id = $dr['auth_id'];
                SessionManager::getInstance($authInfo);
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Logs Failed Logins in table sys.user_failed_login. This log can be used for auditing purposess
     * 
     * @param string $username  Logon username
     * @param string $fail_rsn  Failure Reson to be logged
     */
    public static function logFailedLogin(string $username, string $fail_rsn) {
        $ip = \yii::$app->request->getUserIP();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into sys.user_failed_login(user_name, user_ip, fail_rsn, info)
                Values(:puser_name, :puser_ip, :pfail_rsn, :pinfo)");
        $cmm->addParam('puser_name', $username);
        $cmm->addParam('puser_ip', $ip);
        $cmm->addParam('pfail_rsn', $fail_rsn);
        $pp = [];
        $pp['SERVER'] = $_SERVER;
        $pp['GET'] = $_GET;
        $pp['POST'] = $_POST;
        \yii::trace($pp);
        if (array_key_exists('Login', $pp['POST']) && array_key_exists('password', $pp['POST']['Login'])) {
            $pp['POST']['Login']['password'] = 'xxxxxx';
        }
        $cmm->addParam('pinfo', json_encode($pp));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }

    private static function generateOTP($n = 4): string {
        // Take a generator string which consist of 
        // all numeric digits 
        $generator = "1357902468";

        // Iterate for n-times and pick a single character 
        // from generator and append it to $result 
        // Login for generating a random character from generator 
        //     ---generate a random number 
        //     ---take modulus of same with length of generator (say i) 
        //     ---append the character at place (i) from generator to result 

        $result = "";

        for ($i = 1; $i <= $n; $i++) {
            $result .= substr($generator, (rand() % (strlen($generator))), 1);
        }

        // Return result 
        return $result;
    }

}
