<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\adminUser;

/**
 * Description of AdminUserEventHandler
 *
 * @author Priyanka
 */
class AdminUserEventHandler extends \app\cwf\sys\user\UserEventHandler {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->user_id != -1) {
            $this->bo->user_pass = 'aaaaa';
            $this->bo->user_pass_confirm ='aaaaa';
        } else {
            $this->bo->user_pass_confirm = '';
        }
        $this->bo->is_admin = true;
    }
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
    }
}
