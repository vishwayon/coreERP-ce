<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\installer\workers;

class DbCreator {
    /** @var \app\cwf\console\installer\workers\ConfigReader */
    private static $config;
    /** @var \app\cwf\console\installer\workers\DbCon **/
    private static $dbCon;
    private static $rootModules = [];
    
    public static $outstream;
    
    
    public static function StartInstallation($outstream) {
        self::$outstream = $outstream;
        fwrite($outstream, 'Reading Config ...'."\n");
        self::loadRootModules();
        fwrite($outstream, 'Following Root Modules Found ...'."\n");
        foreach(self::$rootModules as $rootM) {
            fwrite($outstream, $rootM."\n");
        }
        self::$config = new ScriptConfigReader(self::$rootModules);
        self::$dbCon = new DbCon(self::$config);
        fwrite($outstream, 'config Read... veryfing if db already exists ... '."\n");
        
        if(self::existsMainDB()) {
            fwrite($outstream, 'MainDB : '.self::$config->dbInfo->dbMain.' : already exists. Cannot proceed with installation'."\n");
            fwrite($outstream, 'Exiting'."\n");
            return;
        }
        if(self::existsSuperUser()) {
            fwrite($outstream, 'SuperUser : '.self::$config->dbInfo->suName.' : already exists. Cannot proceed with installation'."\n");
            fwrite($outstream, 'Exiting'."\n");
            return;
        }
        
        fwrite($outstream, 'MainDB not found. Proceeding to create database and user ... '."\n");
        self::createMainDB();
        
        fwrite($outstream, 'MainDB and super user created successfully. Proceeding to execute scripts '."\n");
        self::executeScripts(self::$config->scriptInfo->mainDB, 'MainDB', null);                
        fwrite($outstream, 'config Read... proceeding to create audittrail db... '."\n");
        
        fwrite($outstream, 'Generating timezone data '."\n");
        self::insertTimeZoneData();
        fwrite($outstream, 'Timezone data successfully generated '."\n");
        
        self::createAuditTrailDB(self::$config->dbInfo->dbMain, true);
        fwrite($outstream, "audittrail db created successfully\n");
        
        self::executeScripts(self::$config->scriptInfo->auditDB, 'AuditMainDB', null);
        fwrite($outstream, "audittrail objects created successfully\n");
        
        fwrite($outstream, 'DB created.'."\n");
        fwrite($outstream, 'Registering super user'."\n");
        self::registerSuperUser();
        fwrite($outstream, 'superuser registered'."\n");        
    }
    
    public static function StartCompanyCreation($companyInfo, $outstream) {
        self::$outstream = $outstream;
        fwrite($outstream, 'Reading Config ...'."\n");
        self::loadRootModules();
        self::$config = new ScriptConfigReader(self::$rootModules);  
        self::$dbCon = new DbCon(self::$config);
        fwrite($outstream, 'config Read... proceeding to create company database... '."\n");
        
        self::createCompanyDB($companyInfo, true);
        fwrite($outstream, "company database created successfully\n");
        
        self::executeScripts(self::$config->scriptInfo->companyDB, 'CompanyDB', $companyInfo);
        fwrite($outstream, "company databaseobjects created successfully\n");
        
        fwrite($outstream, 'config Read... proceeding to create audit database... '."\n");
        
        self::createAuditTrailDB($companyInfo['database'], true);
        fwrite($outstream, "audit database created successfully\n");
        
        self::executeScripts(self::$config->scriptInfo->auditDB, 'AuditDB', $companyInfo);
        fwrite($outstream, "audit database objects created successfully\n");        
    }
    
    private static function existsMainDB() {
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT datname FROM pg_database WHERE datistemplate = false and datname='".self::$config->dbInfo->dbMain."';");
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)>0) {
            return true;
        } else {
            return false;
        }
    }
    
    private static function existsSuperUser() {
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT usename FROM pg_user WHERE usename = '".self::$config->dbInfo->suName."';");
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)>0) {
            return true;
        } else {
            return false;
        }
    }
    
    private static function createMainDB() {
        $cn = self::$dbCon->getPgCn();
        
        // Step 1: Create User
        $query = $cn->prepare("Create Role ".self::$config->dbInfo->suName." With CREATEDB SUPERUSER LOGIN Password '".self::$config->dbInfo->suPass."';");
        $query->execute();
        
        // Step 2: Create Database with user as owner
        $query = $cn->prepare("create database ".self::$config->dbInfo->dbMain." WITH OWNER = ".self::$config->dbInfo->suName
                        ." ENCODING = 'UTF8' CONNECTION LIMIT = -1;");
        $query->execute();
        
    }
    
    private static function registerSuperUser() {
        $cn=self::$dbCon->getCnMainDB();
        
        // Step 1: Create User
        $sql = "Insert into sys.user(user_id, user_name, user_pass, full_user_name, email, is_active, is_admin, is_owner, user_attr)
                Values(0, :psu_name, :psu_pass, 'superuser', '', true, true, false, :puser_attr)";
        // Attrs for login
        $uattr = new \stdClass();
        $uattr->pwd_force_change = FALSE;
        $uattr->logon_addr = [];
        $uattr->logon_addr[] = new class {
            public $ip = '0.0.0.0/0';
        };
        $uattr->otp_req = FALSE;
        $uattr->en_otp_req_type = 101;
        $params = [
            'psu_name' => self::$config->dbInfo->suName,
            'psu_pass' => \Yii::$app->getSecurity()->generatePasswordHash(self::$config->dbInfo->suPass),
            'puser_attr' => json_encode($uattr)
        ];
        $query = $cn->prepare($sql);
        $query->execute($params);
    }
    
    private static function executeScripts(array $scriptFiles, $dbType = 'MainDB', $companyInfo = null, $isUpgrade=false) {
        if($dbType=='MainDB') {
            $cn = self::$dbCon->getCnMainDB();
        } else if ($dbType == 'CompanyDB') {
            $cn = self::$dbCon->getCnCompanyDB($companyInfo['database']);
        } else if ($dbType == 'AuditDB') {
            $cn = self::$dbCon->getCnAuditDB($companyInfo['database'].'_aud');
        }  else if ($dbType == 'AuditMainDB') {
            $cn = self::$dbCon->getCnAuditDB(self::$config->dbInfo->dbMain.'_aud');
        } else {
            throw new \Exception('Unknown dbType. Failed to open connection');
        }
        
        // Open a transaction to ensure that each script file is completed in totality
        $cn->beginTransaction();
        foreach($scriptFiles as $scriptFile) {
            fwrite(self::$outstream, 'Starting file: '.$scriptFile."\n");
            $scriptParser = new ScriptParser(\Yii::$app->basePath.'//'.$scriptFile, $companyInfo, self::$config);
            while($scriptParser->hasData()) {
                try {
                    if(strlen($scriptParser->getScriptToExec())>0) {
                        $query = $cn->prepare($scriptParser->getScriptToExec());
                        $query->execute();
                    }
                    $scriptParser->moveNext();
                } catch (\Exception $ex) {
                    try {
                        $cn->rollBack();
                    } catch(\Exception $tranEx) {
                        // do nothing
                    } 
                    $msg = "Error executing script\n".$scriptParser->getScriptToExec()."\n".$ex->getMessage();
                    $newEx = new \yii\console\Exception($msg, '001', $ex);
                    throw $newEx;
                }
            }
            fwrite(self::$outstream, 'File Completed'."\n");
        }
        // always mark the database version
        try {
             if($dbType=='MainDB') {
                $dbname = "MainDB";
            } else if ($dbType == 'CompanyDB') {
                $dbname = $companyInfo['database'];
            } else if ($dbType == 'AuditDB') {
                $dbname = $companyInfo['database'].'_aud';
            }  else if ($dbType == 'AuditMainDB') {
                $dbname = self::$config->dbInfo->dbMain.'_aud';
            }
            self::markDbUpgraded($dbname, $cn);
        } catch (\Exception $exupgrade) {
            try {
                $cn->rollBack();
            } catch(\Exception $tranExupgrade) {
                // do nothing
            } 
            throw $exupgrade;
        }
        // commit the transaction
        $cn->commit();
    }
    
    private static function createCompanyDB($companyInfo, $forceCreate = false) {
        // Step 1: First we check if the db exists
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT datname FROM pg_database WHERE datistemplate = false and datname='".$companyInfo['database']."';");
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)>0) {
            // Company db exists.
            if($forceCreate) {
                // drop db
                $query = $cn->prepare("drop database ".$companyInfo['database'].";");
                $query->execute();
            } else {
                throw new \Exception("DB with current name already exists. To drop and recreate, set the forceCreate bit to true");
            }
        } 
        // Step 2: Create Database with user as owner
        $query = $cn->prepare("create database ".$companyInfo['database']." WITH OWNER = ".self::$config->dbInfo->suName
                        ." ENCODING = 'UTF8' CONNECTION LIMIT = -1;");
        $query->execute();
    }
    
    private static function createAuditTrailDB($dbName, $forceCreate = false) {
        // Step 1: First we check if the db exists
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT datname FROM pg_database WHERE datistemplate = false and datname='".$dbName."_aud';");
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)>0) {
            // AuditTrail db exists.
            if($forceCreate) {
                // drop db
                $query = $cn->prepare("drop database ".$dbName."_aud;");
                $query->execute();
            } else {
                throw new \Exception("Audit DB with current name already exists. To drop and recreate, set the forceCreate bit to true");
            }
        } 
        
        // Step 2: Create Database with user as owner
        $query = $cn->prepare("create database ".$dbName."_aud WITH OWNER = ".self::$config->dbInfo->suName
                        ." ENCODING = 'UTF8' CONNECTION LIMIT = -1;");
        $query->execute();
    }
    
    public static function UpgradeDB($outstream) {
        self::$outstream = $outstream;
        fwrite($outstream, 'Reading Config ...'."\n");
        self::loadRootModules();
        fwrite($outstream, 'Following Root Modules Found ...'."\n");
        foreach(self::$rootModules as $rootM) {
            fwrite($outstream, $rootM."\n");
        }
        self::$config = new UpgradeConfigReader(self::$rootModules);
        self::$dbCon = new DbCon(self::$config);
        fwrite($outstream, 'config Read... connecting and getting list of company db(s) ... '."\n");
        
        // Upgrade Main DB
        fwrite($outstream, "Verifying Main DB ... "."\n");
        if(self::verifyIfUpgradeRequired('MainDB')) {
            fwrite($outstream, "Upgrading Main DB ... "."\n");
            self::executeScripts(self::$config->scriptInfo->mainDB, 'MainDB', null, TRUE);
            fwrite($outstream, "Main DB successfully upgraded ... "."\n");
        } else {
            fwrite($outstream, "Skipped Main DB (version matched) ... "."\n");
        }
        
        
        // Fetch list of company Databases
        fwrite($outstream, "Upgrading Company DB(s) ... "."\n");
        $cn = self::$dbCon->getCnMainDB();
        $query = $cn->prepare("SELECT company_id, company_short_name, database FROM sys.company;");
        $query->execute();
        $companies = $query->fetchAll();
        $query->closeCursor();
        
        if(count($companies)>0) {
            foreach($companies as $compInfo) {
                if(self::verifyIfUpgradeRequired($compInfo['database'])) {
                    // Upgrade each company
                    fwrite($outstream, "Upgrading Company DB - ".$compInfo['company_short_name']."[".$compInfo['database']."] ... "."\n");
                    self::executeScripts(self::$config->scriptInfo->companyDB, "CompanyDB", $compInfo, TRUE);
                    fwrite($outstream, "Successfully upgraded ".$compInfo['company_short_name']."[".$compInfo['database']."]"."\n");
                } else {
                    fwrite($outstream, "Skipped company upgrade ".$compInfo['company_short_name']."[".$compInfo['database']."] (version matched)"."\n");
                }
                if(self::verifyIfUpgradeRequired($compInfo['database']."_aud")) {
                    // Upgrade each Audit DB
                    fwrite($outstream, "Upgrading Audit DB - ".$compInfo['company_short_name']."[".$compInfo['database']."_aud] ... "."\n");
                    self::executeScripts(self::$config->scriptInfo->auditDB, "AuditDB", $compInfo, TRUE);
                    fwrite($outstream, "Upgrading Audit DB - ".$compInfo['company_short_name']."[".$compInfo['database']."_aud]"."\n");
                } else {
                    fwrite($outstream, "Skipped Audit DB upgrade ".$compInfo['company_short_name']."[".$compInfo['database']."_aud] (version matched)"."\n");
                }
            }
        }
    }
    
    private static function markDbUpgraded($dbname, $cn) {
        $cmm = "Insert Into sys.db_ver(db_name, coreerp_ver, modules, last_updated)\n";
        if($dbname=="MainDB") {
            $cmm .= "values(current_database(), :pcoreerp_ver, :pmodules, current_timestamp(0))";
        } else {
            $cmm .= "values(:pdb_name, :pcoreerp_ver, :pmodules, current_timestamp(0))";
            $params['pdb_name'] = $dbname;
        }
        $modules = '';
        foreach(self::$rootModules as $rootModule) {
            if(strlen($modules)>0){
                $modules .= ",";
            }
            $modules .= $rootModule;
        }
        $params['pcoreerp_ver'] = \yii::$app->params['coreerp-ver'];
        $params['pmodules']=$modules;
        
        $query = $cn->prepare($cmm);
        $query->execute($params);
        
    }
    
    private static function verifyIfUpgradeRequired($dbname) {
        if($dbname=='MainDB') {
            $cn = self::$dbCon->getCnMainDB();
        } else {
            $cn = self::$dbCon->getCnCompanyDB($dbname);
        }
        $cmm = "Select table_name From Information_schema.tables Where table_schema='sys' And table_name='db_ver';";
        $query = $cn->prepare($cmm);
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)==0) {
            throw new \Exception("Missing table: sys.db_ver. Create this table for db upgrades", "004");
        }
            
        $cmm = "Select coreerp_ver From sys.db_ver Where db_ver_id=(Select max(db_ver_id) From sys.db_ver);";
        $query = $cn->prepare($cmm);
        $query->execute();
        $result = $query->fetchAll();
        if(count($result)>0) {
            // verify whether new upgrade is required
            if(version_compare(\yii::$app->params['coreerp-ver'], $result[0]['coreerp_ver']) > 0) {
                // verify that the previous upgrade was proper
                if(\yii::$app->params['coreerp-previous-ver'] != $result[0]['coreerp_ver']) {
                    throw new \Exception("Database[".$dbname."] current version[".$result[0]['coreerp_ver']."] does not match with coreerp-previous-ver[".\yii::$app->params['coreerp-previous-ver']."]. Upgrade not allowed.\n");
                } else {           
                    return TRUE;
                }
            } else {
                return FALSE;
            }
        } else {
            return TRUE;
        }
    }
    
    private static function loadRootModules() {
        // This would be populated from cwfConfig
        $cwfConfig = \yii::$app->params['cwf_config'];
        if(isset($cwfConfig['rootModules'])) {
            // load root module names only. These will be used to search for -script-config.xml
            // cwf is left out as it has a specific script-config.xml
            foreach($cwfConfig['rootModules'] as $mod => $modVal) {
                array_push(self::$rootModules, $mod);
            }
        }
    }
    
    private static function insertTimeZoneData() {
        $cn = self::$dbCon->getCnMainDB();
        $cmm = "Insert Into sys.time_zone(time_zone_id, time_zone) Values(:ptime_zone_id, :ptime_zone)";
        $query = $cn->prepare($cmm);
        $tzlist = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        foreach($tzlist as $tz) {
            $query->execute([
                'ptime_zone_id' => $tz,
                'ptime_zone' => $tz
            ]);
        }
        $query = null;
        $cn = null;
    }
}