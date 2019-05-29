<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\pm;

/**
 * Base class used for all parser results
 *
 * @author girish
 */
abstract class ParserResult {
    //put your code here
    
}

class UserTask extends ParserResult {
    public $taskid;
    public $docAction;
    public $roles;
}