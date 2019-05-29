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
    public $timeInterval = '20 minutes';
    
    public function __construct(\app\cwf\vsla\utils\ConfigReader $cwfConfig, $outstream) {
        $this->cwfConfig = $cwfConfig;
        $this->outstream = $outstream;
    }
    
    public function DoCleanup() {
        // Expire invalid sessions
        $expiredSessions = $this->expireSessions();
        $this->removeReportCache($expiredSessions);
        $this->removeFileCache($expiredSessions);
        // Always call user logout cleanup
        $this->removeReportCache($this->userLogout());
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
    
    private function removeReportCache($expiredSessions) {
        $rcPath = \yii::$app->basePath."/web/reportcache";
        if(is_link($rcPath)){
            $rcPath = readlink($rcPath); 
        }
        $dirs = scandir($rcPath);
        foreach($dirs as $dir) {
            if($dir != "." && $dir != "..") {
                if($this->isMatch($expiredSessions, $dir)) {
                    $this->removeAll($rcPath.$dir."/");
                }
            }
        }
    }
    
    private function removeFileCache($expiredSessions) {
        foreach($expiredSessions as $expSess) {
            $fcPath = \yii::getAlias('@runtime/cache/sid'.  ((string)$expSess['user_session_id']).DIRECTORY_SEPARATOR);
            $files = scandir($fcPath);
            foreach($files as $file) {
                if($file != "." && $file != "..") {
                    if (is_file($fcPath.$file)) {
                        chmod($fcPath.$file, 0777);
                        unlink($fcPath.$file);
                    }
                }
            }
            rmdir($fcPath);
        }        
    }
    
    private function isMatch($expiredSessions, $dirName) {
        foreach($expiredSessions as $expSess) {
            if ($expSess['user_session_id']==$dirName) {
                return TRUE;
            }
        }
        return FALSE;
    }
    
    private function removeAll($dirPath) {
        // This function removes only physical files and paths
        // All linked paths are ignored
        $files = scandir($dirPath);
        foreach($files as $file) {
            if($file != "." && $file != "..") {
                if (is_file($dirPath.$file)) {
                    chmod($dirPath.$file, 0777);
                    unlink($dirPath.$file);
                } elseif (is_dir($dirPath.$file)) {
                    $this->removeAll($dirPath.$file."/");
                }
            }
        }
        rmdir($dirPath);
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
}
