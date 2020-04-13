<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\utils\workers;

/**
 * Description of SessionCleanup
 *
 * @author girish
 */
class SessionCleanup {

    /** @var \app\cwf\vsla\utils\ConfigReader */
    private $cwfConfig;
    private $outstream;
    public $timeInterval = 'PT20M'; // 20 Minutes

    public function __construct(\app\cwf\vsla\utils\ConfigReader $cwfConfig, $outstream) {
        $this->cwfConfig = $cwfConfig;
        $this->outstream = $outstream;
    }

    public function DoCleanup() {
        // Expire invalid sessions
        $expiredSessions = $this->expireSessions();
        $this->removeCache($expiredSessions);
        // Always call user logout cleanup
        $logoutSessns = $this->userLogout();
        $this->removeCache($logoutSessns);
    }

    private function expireSessions() {
        $dbCon = new \app\cwf\console\installer\workers\DbCon($this->cwfConfig);
        $cn = $dbCon->getCnMainDB();
        $sql = "Select * From sys.sp_invalid_session_get(:ptimeInterval);";
        $query = $cn->prepare($sql);
        $query->execute(["ptimeInterval" => $this->timeInterval]);
        $result = $query->fetchAll();
        return $result;
    }

    private function userLogout() {
        $dbCon = new \app\cwf\console\installer\workers\DbCon($this->cwfConfig);
        $cn = $dbCon->getCnMainDB();
        $sql = "Select * From sys.sp_user_logout_get();";
        $query = $cn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll();
        return $result;
    }

    private function removeCache($expiredSessions) {
        $rcPath = \yii::$app->basePath . '/web/reportcache/'; // The webpath is not available in console. Hence, use basePath
        $scPath = \yii::getAlias('@runtime/cache/sid');
        foreach ($expiredSessions as $expSess) {
            // clear reportCache
            $folderPath = $rcPath . (string) $expSess['user_session_id'];
            \yii\helpers\FileHelper::removeDirectory($folderPath);
            // clear sessionCache
            $folderPath = $scPath . (string) $expSess['user_session_id'];
            \yii\helpers\FileHelper::removeDirectory($folderPath);
        }
    }

}
