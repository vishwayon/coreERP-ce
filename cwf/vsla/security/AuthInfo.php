<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\security;

/**
 * Description of SessionInfo
 *
 * @author girish
 */
class AuthInfo {
    public $auth_id = '';
    public $session_id = '';
    public $userName = '';
    public $userPass = '';
    public $person_id = '';
    public $token = '';
    public $is_mobile = false;
}
