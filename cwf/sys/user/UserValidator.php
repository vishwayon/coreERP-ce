<?php

namespace app\cwf\sys\user;

use app\cwf\vsla\security\PasswordPolicy;

class UserValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public $policy;

    public function validateUserEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);


        $rules['min_length'] = 8;
        $rules['max_length'] = 64;
        $this->policy = new PasswordPolicy($rules);
        $this->policy->min_lowercase_chars = 1;
        $this->policy->min_uppercase_chars = 1;
        $this->policy->min_numeric_chars = 1;

        // conduct business rule validations
        $this->validateBusinessRules();
        $this->validatepasskey();
    }

    public function validatepasskey() {
        if ($this->bo->user_id == -1 || ($this->bo->user_id != -1 && $this->bo->user_pass != 'aaaaa')) {
            $valid = $this->policy->validate($this->bo->user_pass);
            if (!$valid) {
                foreach ($this->policy->get_errors() as $k => $error) {
                    $this->bo->addBRule($error);
                }
            }
        }
    }

    protected function validateBusinessRules() {
        if ($this->bo->is_mac_addr) {
            if (count($this->bo->mac_addr->items()) == 0) {
                $this->bo->addBRule('Atleast one MAC address is required if login requires MAC validation');
            }
        }
        $this->bo->user_name = trim($this->bo->user_name);
        if (preg_match("/\s/", $this->bo->user_name)) {
            $this->bo->addBRule('User name should not have space.');
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.user where user_name ilike :puser_name and user_id!=:puser_id');
        $cmm->addParam('puser_name', $this->bo->user_name);
        $cmm->addParam('puser_id', $this->bo->user_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($res->Rows()) > 0) {
            $this->bo->addBRule('User name already exists. Duplicates not allowed.');
        }

        if ($this->bo->email != "") {
            if ($this->validateEmail($this->bo->email)) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from sys.user where email ilike :pemail and user_id!=:puser_id And is_owner = false And is_admin = false');
                $cmm->addParam('pemail', $this->bo->email);
                $cmm->addParam('puser_id', $this->bo->user_id);
                $res = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                if (count($res->Rows()) > 0) {
                    $this->bo->addBRule('Email already exists. Duplicates not allowed.');
                }
            } else {
                $this->bo->addBRule('Invalid email id.');
            }
        }

        foreach ($this->bo->user_branch_role->Rows() as $row) {
            $cnt = 0;
            foreach ($this->bo->user_branch_role->Rows() as $row1) {
                if ($row['role_id'] == $row1['role_id'] && $row['branch_id'] == $row1['branch_id']) {
                    $cnt += 1;
                }
            }
            if ($cnt > 1) {
                $this->bo->addBRule('Role and Branch combination should be unique.');
                break;
            }
        }

        if ($this->bo->user_pass != $this->bo->user_pass_confirm) {

            $this->bo->addBRule('Password and Confirm password should match.');
        }

        // Validate ip address
        $ipv4Pattern = '/^(?=\d+\.\d+\.\d+\.\d+($|\/))(([1-9]?\d|1\d\d|2[0-4]\d|25[0-5])\.?){4}(\/([0-9]|[1-2][0-9]|3[0-2]))?$/';
        foreach ($this->bo->user_attr->Value()->logon_addr as $ip) {
            if (preg_match($ipv4Pattern, $ip->ip) == 0) {
                $this->bo->addBRule("Incorrect IPv4 format [$ip->ip]");
            }
        }

        // Validate req OTP type
        if ($this->bo->user_attr->Value()->otp_req && $this->bo->user_attr->Value()->en_otp_req_type == -1) {
            $this->bo->addBRule("Req. OTP Type is required.");
        }
    }

    public function validateUserAccessLevelEditForm() {
        
    }

}
