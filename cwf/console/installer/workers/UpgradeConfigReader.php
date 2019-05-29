<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\installer\workers;

/**
 * Description of UpgradeConfigReader
 *
 * @author girish
 */
class UpgradeConfigReader extends \app\cwf\vsla\utils\ConfigReader {
    
    /** @var ScriptInfo **/
    public $scriptInfo;
    
    public function __construct(array $rootModules) {
        parent::__construct();
        
        $this->scriptInfo = new ScriptInfo();
        // This is first script file
        $this->findScriptConfig('cwf');
                
        // find and load module scripts
        foreach ($rootModules as $rootModule) {
            $this->findScriptConfig($rootModule);
        }
    }
    
    private function findScriptConfig($rootModule) {
        $basePath='';
        if(is_link(\Yii::$app->basePath."/".$rootModule)) {
            $basePath = readlink(\Yii::$app->basePath."/".$rootModule)."release-doc/";
        } else {
            $basePath .= "/release-doc/";
        }
        if(is_readable($basePath)) {
            if(file_exists($basePath.$rootModule."-rel-script-config.xml")) {
                $this->loadScriptConfig($basePath.$rootModule."-rel-script-config.xml");
            } else {
                throw new \Exception("Upgrade failed! missing rel-script-config for module path ".$basePath);
            }
        } else {
            throw new \Exception("Upgrade failed! could not read ".$basePath);
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
