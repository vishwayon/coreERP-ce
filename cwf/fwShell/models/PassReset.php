<?php

namespace app\cwf\fwShell\models;

use app\cwf\vsla\data\DataConnect;
use \app\cwf\vsla\data\SqlCommand;
use yii\base\Model;
use app\cwf\vsla\security\PasswordPolicy;

class PassReset extends Model {

    public $id;
    public $passkey;
    public $passkey2;
    public $error = array();
    public $policy;

    public function rules() {
        return [
            [['passkey', 'passkey2'], 'required', 'message' => 'Password can not be blank.'],
            ['passkey2', 'compare', 'compareAttribute' => 'passkey', 'message' => 'Passwords do not match'],
            ['passkey', 'validatepasskey']
        ];
    }

    public function __construct() {
        $rules['min_length'] = 8;
        $rules['max_length'] = 64;
        $this->policy = new PasswordPolicy($rules);
        $this->policy->min_lowercase_chars = 1;
        $this->policy->min_uppercase_chars = 1;
        $this->policy->min_numeric_chars = 1;
    }

    public function validatepasskey() {
        $valid = $this->policy->validate($this->passkey);
        if (!$valid) {
            foreach ($this->policy->get_errors() as $k => $error) {
                $this->addError('passkey', $error);
                array_push($this->error, $error);
            }
        }
    }

    public static function requestReset($username, $web) {
        $cmd = new SqlCommand();
        $cmd->setCommandText('select * from sys.sp_user_pass_reset(:puser_name,:pweb)');
        $cmd->addParam('puser_name', $username);
        $cmd->addParam('pweb', $web);
        $dtuser = DataConnect::getData($cmd, DataConnect::MAIN_DB);
        if (count($dtuser->Rows()) > 0) {
            if ($dtuser->Rows()[0]['reset_id'] != -1) {
                return ['status' => 'OK', 'token' => $dtuser->Rows()[0]['reset_uuid']];
            }
        }
        return ['status' => 'ERROR', 'msg' => 'Failed to generate Password Reset Request. Contact software support'];
    }

    public static function validateResetRequest($uuid) {
        $cmd = new SqlCommand();
        $cmd->setCommandText('select * from sys.sp_update_reset_status(:preset_uuid)');
        $cmd->addParam('preset_uuid', $uuid);
        $dtuser = DataConnect::getData($cmd, DataConnect::MAIN_DB);
        if (count($dtuser->Rows()) == 1) {
            if ($dtuser->Rows()[0]['sp_update_reset_status'] == 0) {
                return 'OK';
            } else {
                return 'Error';
            }
        } else {
            return 'Error';
        }
    }

    public static function revalidateResetRequest($uuid) {
        $cmd = new SqlCommand();
        $cmd->setCommandText('select * from sys.sp_rollback_reset_status(:preset_uuid)');
        $cmd->addParam('preset_uuid', $uuid);
        $dtuser = DataConnect::getData($cmd, DataConnect::MAIN_DB);
        if (count($dtuser->Rows()) == 1) {
            if ($dtuser->Rows()[0]['sp_rollback_reset_status'] == 0) {
                return 'OK';
            } else {
                return 'Error';
            }
        } else {
            return 'Error';
        }
    }

    public static function resetPass($uuid, $pass) {
        $cmd = new SqlCommand();
        $cmd->setCommandText('select * from sys.sp_update_password(:preset_uuid, :pnew_password)');
        $cmd->addParam('preset_uuid', $uuid);
        $cmd->addParam('pnew_password', $pass);
        $dtuser = DataConnect::getData($cmd, DataConnect::MAIN_DB);
        if (count($dtuser->Rows()) > 0) {
            if ($dtuser->Rows()[0]['sp_update_password'] == 2) {
                return 'OK';
            } else {
                return 'Error';
            }
        } else {
            return 'Error';
        }
    }

    /**
     * Returns the if the new password matches the user's current password
     * @param type $uuid The request token
     * @return bool         TRUE when new and old password are the same
     */
    public static function compareNewPassWithOld(string $uuid, string $newPass): bool {
        $cmm = new SqlCommand();
        $cmm->setCommandText("Select user_pass From sys.user
                    Where user_id In (Select b.user_id from sys.user_pass_reset b Where b.reset_uuid = :preset_uuid)");
        $cmm->addParam("preset_uuid", $uuid);
        $dt = DataConnect::getData($cmm, DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            return \yii::$app->getSecurity()->validatePassword($newPass, $dt->Rows()[0]['user_pass']);
        }
        return FALSE;
    }
    
    /**
     * Returns true if the new password matches the user's current password
     * @return bool TRUE when new and old password are the same
     */
    public static function compareNewPassWithOldCurrentUser(string $newPass): bool {
        $cmm = new SqlCommand();
        $cmm->setCommandText("Select user_pass From sys.user
                    Where user_id = :puser_id");
        $cmm->addParam("puser_id", \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = DataConnect::getData($cmm, DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            return \yii::$app->getSecurity()->validatePassword($newPass, $dt->Rows()[0]['user_pass']);
        }
        return FALSE;
    }

    /**
     * Toggles the user's Force Change Pwd status to false.
     * Call this method only after successful change of user's password
     * @param string $uuid The token generated for password change
     */
    public static function pwdForceChangeToggle(string $uuid) {
        $cmm = new SqlCommand();
        $cmm->setCommandText("Update sys.user
                    Set user_attr = jsonb_set(user_attr, '{pwd_force_change}', 'false'::jsonb)
                    Where user_id In (Select b.user_id from sys.user_pass_reset b Where b.reset_uuid = :preset_uuid)");
        $cmm->addParam("preset_uuid", $uuid);
        $dt = DataConnect::getData($cmm, DataConnect::MAIN_DB);
    }

}
