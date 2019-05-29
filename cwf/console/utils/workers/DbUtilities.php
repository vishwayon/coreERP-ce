<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DbUtilities
 *
 * @author girish
 */
namespace app\cwf\console\utils\workers;

class DbUtilities {
    /** @var \app\cwf\vsla\utils\ConfigReader */
    private $cwfConfig;
    private $outstream;
    
    public function __construct(\app\cwf\vsla\utils\ConfigReader $cwfConfig, $outstream) {
        $this->cwfConfig = $cwfConfig;
        $this->outstream = $outstream;
    }
    
    public function backupAll() {
        $dbCon = new \app\cwf\console\installer\workers\DbCon($this->cwfConfig);
        $cn = $dbCon->getPgCn();
        $sql = "Select a.datname, b.usename From pg_database a Inner Join pg_user b On a.datdba=b.usesysid Where b.usename='".$this->cwfConfig->dbInfo->suName."';";
        $query = $cn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll();
        $query = null;
        $cn = null;
        
        // resolve backup paths
        $outFiles = [];
        $tmpBkupPath = '';
        if($this->cwfConfig->dbBackup->compress == "singleFile") {
            $tmpBkupPath = $this->cwfConfig->dbBackup->path."tmp/";
            if(file_exists($tmpBkupPath)) {
                $this->removeDir($tmpBkupPath);
            }
            mkdir($tmpBkupPath);
        } else {
            // leave files as they were
            $tmpBkupPath = '';
        }
        
        // Take physical backups
        foreach($result as $db){
            $outFile = $db['datname']."_";
            $outFile .= date("Ymd_Hi", time());
            $outFile .= ".backup";
            
            if($this->cwfConfig->dbBackup->compress == "singleFile") {
                $this->backup($db['datname'], $tmpBkupPath.$outFile);
            } else {
                $bkupPath = $this->cwfConfig->dbBackup->path.$db['datname']."/";
                if(!file_exists($bkupPath)) {
                    // would try to create the temp path
                    mkdir($bkupPath, 0755);
                }
                $this->backup($db['datname'], $bkupPath.$outFile);
            }
            $fileInfo = new FileInfo();
            $fileInfo->dbName = $db['datname'];
            $fileInfo->outFile = $outFile;
            array_push($outFiles, $fileInfo);
        }
        
        // compress output (if required)
        if ($this->cwfConfig->dbBackup->compress=="singleFile") {
            $tarFileName = $this->cwfConfig->dbBackup->path."data-".$this->cwfConfig->dbInfo->suName."-backup_".date("Ymd_Hi", time()).".tar";
            $tar = new \PharData($tarFileName);
            foreach($outFiles as $outFile) {
                $tar->addFile($tmpBkupPath.$outFile->outFile, $outFile->outFile);
            }
            $tar->compress(\Phar::GZ);
            
            // remove the temp tar file
            unlink($tarFileName);
            // remove the temp directory
            $this->removeDir($tmpBkupPath);
        } else if ($this->cwfConfig->dbBackup->compress=="multiFile") {
            foreach($outFiles as $outFile) {
                $tarFileName = $this->cwfConfig->dbBackup->path.$outFile->dbName."/".str_replace(".backup", "", $outFile->outFile).".tar";
                $bkupFileName = $this->cwfConfig->dbBackup->path.$outFile->dbName."/".$outFile->outFile;
                $tar = new \PharData($tarFileName);
                $tar->addFile($bkupFileName, $outFile->outFile);
                $tar->compress(\Phar::GZ);
                unlink($tarFileName);
                unlink($bkupFileName);
            }
        }
    }
    
    private function removeDir($dirPath) {
        // removes files from a directory
        $tmpFiles = scandir($dirPath);
        foreach($tmpFiles as $tmpFile) {
            if($tmpFile !== "." && $tmpFile !=="..") {
                unlink($dirPath.$tmpFile);
            }
        }
        rmdir($dirPath);
    }
    
    public function backup($dbName, $outFile) {        
        // The following lines of code set the pgPassword for login
        putenv('PG_USER='.$this->cwfConfig->pgInfo->pgUser); 
        putenv('PGPASSWORD='.$this->cwfConfig->pgInfo->pgPass);
        
        $bkCmm = "/usr/bin/pg_dump -f ".$outFile." -F custom -h ".$this->cwfConfig->dbInfo->dbServer." -U ".$this->cwfConfig->pgInfo->pgUser." -w ".$dbName;
        exec($bkCmm, $output, $iRet);
        
        foreach($output as $ss) { 
            fwrite($this->outstream, $ss."\n");
        }
    }
}

class FileInfo {
    public $dbName='';
    public $outFile='';
}
