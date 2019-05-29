<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of UserUtils
 *
 * @author dev
 */

namespace app\cwf\console\installer\workers;

class UserUtils {

    /** @var \app\cwf\console\installer\workers\ConfigReader */
    private static $config;

    /** @var \app\cwf\console\installer\workers\DbCon * */
    private static $dbCon;
    public static $outstream;

    public static function changesu($outstream) {
        self::$outstream = $outstream;
        fwrite($outstream, 'Reading Config ...' . "\n");
        self::$config = new ScriptConfigReader([]);
        self::$dbCon = new DbCon(self::$config);
        fwrite($outstream, 'config Read... veryfing if db exists... ' . "\n");
        if (self::existsMainDB()) {
            fwrite($outstream, 'db exists... veryfing if user exists... ' . "\n");
            if (self::existsSuperUser()) {
                fwrite($outstream, 'user exists... changing passkey... ' . "\n");
                self::changeSUPass();
                fwrite($outstream, 'passkey changed... ' . "\n");
            }
        }
    }

    private static function changeSUPass() {
        $cn = self::$dbCon->getCnMainDB();
        $pwdhash = \Yii::$app->getSecurity()->generatePasswordHash(self::$config->dbInfo->suPass);
        $query = $cn->prepare("Update sys.user Set user_pass = '" . $pwdhash
                . "' where user_name = '" . self::$config->dbInfo->suName . "'");
        $query->execute();
        $query = NULL;
        $cn = NULL;
    }

    private static function existsMainDB() {
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT datname FROM pg_database WHERE datistemplate = false and datname='" . self::$config->dbInfo->dbMain . "';");
        $query->execute();
        $result = $query->fetchAll();
        $query = NULL;
        $cn = NULL;
        if (count($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    private static function existsSuperUser() {
        $cn = self::$dbCon->getPgCn();
        $query = $cn->prepare("SELECT usename FROM pg_user WHERE usename = '" . self::$config->dbInfo->suName . "';");
        $query->execute();
        $result = $query->fetchAll();
        $query = NULL;
        $cn = NULL;
        if (count($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function openIP($outstream, string $ip) {
        self::$outstream = $outstream;
        fwrite($outstream, 'Reading Config ...' . "\n");
        self::$config = new ScriptConfigReader([]);
        self::$dbCon = new DbCon(self::$config);
        fwrite($outstream, 'config Read... veryfing if db exists... ' . "\n");
        if (self::existsMainDB()) {
            fwrite($outstream, 'db exists... making entry for super-user-addr ip ... ' . "\n");
            $cn = self::$dbCon->getCnMainDB();
            $query = $cn->prepare("Update sys.user Set user_attr = jsonb_set(user_attr, '{logon_addr}', '[{\"ip\": \"" . $ip . "\"}]'::jsonb, true)
                                where user_name = '" . self::$config->dbInfo->suName . "'");
            $query->execute();
            $query = NULL;
            $cn = NULL;
            fwrite($outstream, 'ip entered successfully... ' . "\n");
        }
    }
}
