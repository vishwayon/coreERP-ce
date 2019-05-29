<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\base;



class SaveResult {
    //put your code here
    public $SaveStatus = 'Failed';
    
    public $BrokenRules = array();
    
    public $BOPropertyBag = null;
    
    public $Params = null;
    
    public $preLookupData = array();
    
    public $docSecurity = array();
    
    public $docComments = null;
}
