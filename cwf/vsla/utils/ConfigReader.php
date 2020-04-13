<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

/**
 * Description of ConfigReader
 *
 * @author girish
 */
class ConfigReader {

    /** @var DbInfo * */
    public $dbInfo;

    /** @var PgInfo * */
    public $pgInfo;

    /** @var DbBackup */
    public $dbBackup;
    public $mailer = [];

    public function __construct() {
        // Extract config from params
        $cwfConfig = \yii::$app->params['cwf_config'];

        // Load dbInfo
        $this->dbInfo = new DbInfo();
        $cDbInfo = $cwfConfig['dbInfo'];
        $this->dbInfo->dbServer = $cDbInfo['dbServer'];
        $this->dbInfo->suName = $cDbInfo['suName'];
        $this->dbInfo->suPass = $cDbInfo['suPass'];
        $this->dbInfo->dbMain = $cDbInfo['dbMain'];
        if(array_key_exists('port', $cDbInfo)) {
            $this->dbInfo->port = $cDbInfo['port'];
        }

        // Load pgInfo
        $this->pgInfo = new PgInfo();
        $cpgInfo = $cwfConfig['pgInfo'];
        $this->pgInfo->pgUser = $cpgInfo['pgUser'];
        $this->pgInfo->pgPass = $cpgInfo['pgPass'];

        // Load DbBackupInfo
        $this->dbBackup = new DbBackup();
        if (isset($cwfConfig['dbBackup'])) {
            $cdbBackup = $cwfConfig['dbBackup'];
            $this->dbBackup->path = $cdbBackup['path'];
            $this->dbBackup->compress = $cdbBackup['compress'];
        }

        // Load Mailer
        if (isset($cwfConfig['mailer'])) {
            $this->mailer = $cwfConfig['mailer'];
        }
    }

}

class DbInfo {

    public $dbServer = '';
    public $port = '5432';
    public $suName = '';
    public $suPass = '';
    public $dbMain = '';

}

class PgInfo {

    public $pgUser = '';
    public $pgPass = '';

}

class DbBackup {

    public $path = '';
    public $compress = "none";

}
