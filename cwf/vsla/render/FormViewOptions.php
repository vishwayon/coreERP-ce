<?php

namespace app\cwf\vsla\render;
use app\cwf\vsla\security\AccessLevels;

class FormViewOptions {
    public $xmlViewPath = '';
    public $callingModulePath = '';
    public $params = array(); 
    /**Set the Original Action Route for  */
    public $actionViewRoute = '';
    
    // Set the Access Level for the user
    /** @var AccessLevels */
    public $accessLevel = AccessLevels::NOACCESS;
}
