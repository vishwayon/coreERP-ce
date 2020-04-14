<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of dbConnect
 *
 * @author girish
 */
namespace app\cwf\vsla\data {
           
    class DataConnect {  
        
        const MAIN_DB = 0;
        const COMPANY_DB = 1; 
        
        /** @var \app\cwf\vsla\utils\ConfigReader */
        private static $dbConfig;
        private static $companyDB=null;
        /**
         * 
         * @param app\vsla\data\SqlCommand $cmm
         * @return app\vsla\data\DataTable
         */
        public static function getData(SqlCommand $cmm, $dbType=self::COMPANY_DB, \PDO $cn = null) {
            $selfCn = false;
            if($cn == null) {
                $selfCn = true;
                $cn = DataConnect::getCn($dbType);
            }
            $query = $cn->prepare($cmm->getCommandText());
            $query->execute($cmm->getParamsForBind());
            $dt = new DataTable();
            DataAdapter::Fill($dt, $query);
            $query = null;
            if($selfCn) {
                $cn = null;
            }
            return $dt;
        }
        
        /**
         * Gets data in SplFixedArray. This is a memory efficient array. 
         * Use this method if you do not plan to remove/add rows in the result
         * 
         * @param \app\cwf\vsla\data\SqlCommand $cmm    The command object
         * @param const $dbType                         The DB to connect (Main/Company/Audit)
         * @param \PDO $cn                              The Connection Object (if already open)
         * @param resource $fhandle                     The writable file handle
         * @return \stdClass                            Returns the a stdClass with cols and rows (Index only rows)
         */
        public static function getDataSplArray(SqlCommand $cmm, $dbType=self::COMPANY_DB, \PDO $cn = null) {
            $selfCn = false;
            if($cn == null) {
                $selfCn = true;
                $cn = DataConnect::getCn($dbType);
            }
            $query = $cn->prepare($cmm->getCommandText());
            $query->execute($cmm->getParamsForBind());
            $resultData = new \SplFixedArray($query->rowCount());
            $i = 0;
            while ($row = $query->fetch(\PDO::FETCH_NUM)) {
                $resultData[$i] = \SplFixedArray::fromArray($row);
                $i++;
            }
            $resultCols = new \SplFixedArray($query->columnCount());
            for($ci=0; $ci<$query->columnCount(); $ci++) {
                $colMeta = $query->getColumnMeta($ci);
                $resultCols[$ci] = $colMeta['name'];
            }
            $query = null;
            $result = new \stdClass();
            $result->cols = $resultCols;
            $result->rows = $resultData;
            if($selfCn) {
                $cn = null;
            }
            return $result;
        }
        
        /**
         * Directly writes the results into a csv file. 
         * @param \app\cwf\vsla\data\SqlCommand $cmm    The command object
         * @param const $dbType                         The DB to connect (Main/Company/Audit)
         * @param \PDO $cn                              The Connection Object (if already open)
         * @param resource $fhandle                     The writable file handle
         * @return int                                  Returns the number of rows affected by the query
         */
        public static function getDataInCsvFile(SqlCommand $cmm, $dbType=self::COMPANY_DB, \PDO $cn = null, $fhandle = null) {
            $selfCn = false;
            if($cn == null) {
                $selfCn = true;
                $cn = DataConnect::getCn($dbType);
            }
            $query = $cn->prepare($cmm->getCommandText());
            $query->execute($cmm->getParamsForBind());
            $rowCount = $query->rowCount();
            // put columns as header row in file
            $cols = [];
            for($ci=0; $ci<$query->columnCount(); $ci++) {
                $colMeta = $query->getColumnMeta($ci);
                $cols[] = $colMeta['name'];
            }
            fputcsv($fhandle, $cols, ',', '"');
            // put row data into file
            while ($row = $query->fetch(\PDO::FETCH_NUM)) {
                fputcsv($fhandle, $row, ',', '"');
            }
            $query = null;
            if($selfCn) {
                $cn = null;
            }
            return $rowCount;
        }
        
        public static function getAuditData(SqlCommand $cmm, $dbType=self::COMPANY_DB) {
            $cn = DataConnect::getCnAuditDB($dbType);
            $query = $cn->prepare($cmm->getCommandText());
            $query->execute($cmm->getParamsForBind());
            $dt = new DataTable();
            DataAdapter::Fill($dt, $query);
            $query = null;
            $cn = null;
            return $dt;
        }

        public static function exeCmm(SqlCommand $cmm, \PDO $cn=null, $dbType=self::COMPANY_DB) {
            $selfCn = false;
            if($cn == null) {
                $selfCn = true;
                $cn = DataConnect::getCn($dbType);
            }
            $query = $cn->prepare($cmm->getCommandText());
            $query->execute($cmm->getParamsForBind());
            $result = $query->fetchAll();
            $cmm->setOutput($result);
            $query = null;
            if($selfCn) {
                $cn = null;
            }
        } 
        
        /** 
         * @return \PDO
         */
        public static function getCn($dbType) {
            self::setdbcon();
            if($dbType==self::MAIN_DB){            
                $cn = new \PDO('pgsql:host='.self::$dbConfig->dbInfo->dbServer.';port='.self::$dbConfig->dbInfo->port.';dbname='.self::$dbConfig->dbInfo->dbMain
                        , self::$dbConfig->dbInfo->suName, self::$dbConfig->dbInfo->suPass);
                $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $cn;
            }
            elseif($dbType==self::COMPANY_DB){
               if(self::$companyDB == null || self::$companyDB==''){
                   self::$companyDB= \app\cwf\vsla\security\SessionManager::getSessionVariable('companyDB');
               }
                $cn = new \PDO('pgsql:host='.self::$dbConfig->dbInfo->dbServer.';port='.self::$dbConfig->dbInfo->port.';dbname='.self::$companyDB
                        , self::$dbConfig->dbInfo->suName, self::$dbConfig->dbInfo->suPass);
                $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $cn;
            }
        }
        
        public static function getCnAuditDB($dbType=self::COMPANY_DB){
            self::setdbcon();
            if($dbType==self::MAIN_DB){            
                $cn = new \PDO('pgsql:host='.self::$dbConfig->dbInfo->dbServer.';port='.self::$dbConfig->dbInfo->port.';dbname='.self::$dbConfig->dbInfo->dbMain.'_aud'
                        , self::$dbConfig->dbInfo->suName, self::$dbConfig->dbInfo->suPass);
                $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $cn;
            }
            elseif($dbType==self::COMPANY_DB){
                if(self::$companyDB == null || self::$companyDB==''){
                    self::$companyDB= \app\cwf\vsla\security\SessionManager::getSessionVariable('companyDB');
                }
                $cn = new \PDO('pgsql:host='.self::$dbConfig->dbInfo->dbServer.';port='.self::$dbConfig->dbInfo->port.';dbname='.self::$companyDB.'_aud'
                        , self::$dbConfig->dbInfo->suName, self::$dbConfig->dbInfo->suPass);
                $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return $cn;
            }
        }
        
        private static function setdbcon(){
            if (self::$dbConfig == null) {
                self::$dbConfig = new \app\cwf\vsla\utils\ConfigReader();
                if(\app\cwf\vsla\security\SessionManager::hasInstance()) {
                    self::$companyDB = \app\cwf\vsla\security\SessionManager::getSessionVariable('companyDB');
                }
            }
        }
        
        public static function getMainDB() {
            self::setdbcon();
            return self::$dbConfig->dbInfo->dbMain;
        }
        
        public static function getCompanyDB() {
            self::setdbcon();
            return self::$companyDB;
        }
        
        public static function clearCompanyDB() {
            self::$companyDB = null;
        }
        
        public static function getCnDB($dbName) {
            $cn = new \PDO('pgsql:host='.self::$dbConfig->dbInfo->dbServer.';port='.self::$dbConfig->dbInfo->port.';dbname='.$dbName
                        , self::$dbConfig->dbInfo->suName, self::$dbConfig->dbInfo->suPass);
            $cn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $cn;
        }
    }
}

