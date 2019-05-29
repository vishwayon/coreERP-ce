<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\vsla\security;


class RoleInfo {
    public $role_id = -1;
    public $role_name = '';
    public $next_role_id = '';
    public $next_role_name = '';
    public $next_stage_id = '';
    public $next_stage_desc = '';
    public $regress_stage_id = '';
    public $regress_stage_desc = 'Regress';
    public $resultStatus = 'Error';
    public $resultMsg = '';
}