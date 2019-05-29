<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\installer\workers;

class DbCon {
    /*
     * @var ConfigReader
     */
    private $config;
    
    public function __construct($cwfConfig) {
        $this->config = $cwfConfig;
    }
    
    public function getPgCn() {
        $cn = new \PDO('pgsql:host='.$this->config->dbInfo->dbServer.';port='.$this->config->dbInfo->port.';dbname=postgres'
                    , $this->config->pgInfo->pgUser
                    , $this->config->pgInfo->pgPass);
        $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $cn;
    }
    
    public function getCnMainDB() {
        $cn = new \PDO('pgsql:host='.$this->config->dbInfo->dbServer.';port='.$this->config->dbInfo->port.';dbname='.$this->config->dbInfo->dbMain
                    , $this->config->dbInfo->suName
                    , $this->config->dbInfo->suPass);
        $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $cn;
    }
    
    public function getCnCompanyDB($dbName) {
        $cn = new \PDO('pgsql:host='.$this->config->dbInfo->dbServer.';port='.$this->config->dbInfo->port.';dbname='.$dbName
                    , $this->config->dbInfo->suName
                    , $this->config->dbInfo->suPass);
        $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $cn;
    }
    
    public function getCnAuditDB($dbName) {
        $cn = new \PDO('pgsql:host='.$this->config->dbInfo->dbServer.';port='.$this->config->dbInfo->port.';dbname='.$dbName
                    , $this->config->dbInfo->suName
                    , $this->config->dbInfo->suPass);
        $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $cn;
    }
    
    
    
}


