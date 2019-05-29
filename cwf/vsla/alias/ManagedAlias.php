<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ManagedAlias
 *
 * @author girish
 */

namespace vsla\alias;

class ManagedAlias {
    //put your code here
    private static $alias = [
        'pcompany_id', '\vsla\security\SessionManager->getUserInfo->getSessionVariable[\'company_id\']' 
    ];
    
    public static function getAliasValue($aliasName) {
        // resolve the alias to the code execution
    }
    
    
    
    
}
