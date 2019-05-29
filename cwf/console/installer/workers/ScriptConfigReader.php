<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\installer\workers;

class ScriptConfigReader extends \app\cwf\vsla\utils\ConfigReader {
    
    /** @var ScriptInfo **/
    public $scriptInfo;
    
    public function __construct(array $rootModules) {
        parent::__construct();
        
        $this->scriptInfo = new ScriptInfo();
        // This is first script file
        $this->loadScriptConfig(\Yii::$app->basePath.'/cwf/console/installer/script-config.xml');
        
        // find and load module scripts
        foreach ($rootModules as $rootModule) {
            $this->findScriptConfig(\Yii::$app->basePath."/".$rootModule."/");
        }
    }
    
    private function findScriptConfig($basePath) {
        if(is_link($basePath)) {
            $basePath = readlink($basePath);
        }
        if(is_readable($basePath)) {
            $dirList = scandir($basePath);
            foreach($dirList as $fname) {
                if($fname !== "." && $fname !==".." && $fname !=="vsla" && $fname !== "release-doc") {
                    $filename = $basePath.$fname;
                    if(is_dir($filename) && is_readable($filename)) {
                        $this->findScriptConfig($filename."/");
                    }
                    else if(is_link($filename)) {
                        $this->findScriptConfig(readlink($filename));
                    }
                    else if(is_file($filename) && is_readable($filename)) {
                        //fwrite(DbCreator::$outstream, "Each File: ".$filename."\n");
                        if(strpos($filename, "-script-config.xml")) {
                            $this->loadScriptConfig($filename);                    
                        }
                    }
                }
            }
        }
    }
    
    private function loadScriptConfig($scriptConfig) {
        $cwFramework = simplexml_load_file($scriptConfig);
        $xScriptConfig = $cwFramework->scriptInfo;
        
        // Load MainDB Script Info
        if(isset($xScriptConfig->mainDB)) {
            $xMainDBScripts = $xScriptConfig->mainDB;
            foreach ($xMainDBScripts->children() as $xScript) {
                array_push($this->scriptInfo->mainDB, (string)$xScript);
            }
        }
        
        // Load CompanyDB Script Info
        if(isset($xScriptConfig->companyDB)) {
            $xCompanyDBScripts = $xScriptConfig->companyDB;
            foreach ($xCompanyDBScripts->children() as $xScript) {
                array_push($this->scriptInfo->companyDB, (string)$xScript);
            }
        }
        
        // Load AuditDB Script Info
        if(isset($xScriptConfig->auditDB)) {
            $xAudDBScripts = $xScriptConfig->auditDB;
            foreach ($xAudDBScripts->children() as $xScript) {
                array_push($this->scriptInfo->auditDB, (string)$xScript);
            }
        }
    }
    
}


class ScriptInfo {
    public $mainDB = array();
    public $companyDB = array();
    public $auditDB = array();
}