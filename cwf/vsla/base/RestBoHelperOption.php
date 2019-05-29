<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\base;

/**
 * Description of RestBoHelperOption
 *
 * @author girish
 */
class RestBoHelperOption {
    public $bo_id = '';
    public $modulePath = '';
    public $moduleNamespace = '';
    public $inParam = [];
    public $formName = '';
    public $action = '';
    public $saveOnWarn = false;
    public $postData = null;
}
