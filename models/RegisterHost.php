<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\models;

/**
 * Description of RegisterHost
 *
 * @author girishshenoy
 */
class RegisterHost extends \yii\base\Model {
    public $mac_name;
    public $mac_id;
    public $platform;
    public $user_agent;
    public $pass;
}
