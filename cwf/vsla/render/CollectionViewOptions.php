<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\render;
use app\cwf\vsla\security\AccessLevels;
/**
 * Description of CollectionViewOptions
 *
 * @author girish
 */
class CollectionViewOptions {
    public $xmlViewPath = '';
    public $callingModulePath = '';
    public $filters = ''; 
    /**Set the Original Action Route for  */
    public $actionViewRoute = '';
    /** Set the Action Route to get Collection Data Only 
     *  This is usually the table that is displayed after refresh click */
    public $actionGetDataRoute = '';
    
    // Set the Access Level for the user
    /** @var AccessLevels */
    public $accessLevel = AccessLevels::NOACCESS; 
}
