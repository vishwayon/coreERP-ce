<?php

namespace app\cwf\console\installer\workers;

/**
 * ScriptParser that can be used to 
 * parse script files and execute sql code chunks
 * separated by ?==?
 *
 * @author girishshenoy
 */
class ScriptParser {
    private $scriptFile;
    private $hasData = false;
    private $filehandle;
    private $script='';
    private $companyInfo;
    private $config;
    
    /**
     * @param string $scriptFile
     * @param array $companyInfo
     * @param \app\cwf\console\installer\workers\ConfigReader $config
     */
    public function __construct($scriptFile, $companyInfo, $config) {
        $this->scriptFile = $scriptFile;
        $this->companyInfo = $companyInfo;
        $this->config = $config;
        $index = 0;
        if(file_exists($scriptFile)) {
            $this->filehandle = fopen($scriptFile, "r");
        } else {
            throw new \Exception("File not Found: ".$scriptFile);
        }
        $this->prepareScript();
    }
    
    private function prepareScript() {
        $script = '';
        if($this->filehandle) {
            while(!feof($this->filehandle)) {
                $line = fgets($this->filehandle);
                if(strpos($line, '?==?')!==0) {
                    if (strpos($line, "--")!==0) {
                        $script = $script.$line;
                    }
                } else {
                    break;
                }
            }
        }
        if(is_null($this->companyInfo)) {
            $this->script =  trim($script);
        } else {
            $this->script = str_replace("{company_id}", $this->companyInfo['company_id'], trim($script));
            $this->script = str_replace("{suName}", $this->config->dbInfo->suName, $this->script);
            $this->script = str_replace("{suPass}", $this->config->dbInfo->suPass, $this->script);
            $this->script = str_replace("{dbMain}", $this->config->dbInfo->dbMain, $this->script);
        }
        if(strlen($this->script)>0) {
            $this->hasData = true;
        } else {
            $this->hasData = false;
        }
    }
        
    public function moveNext() {
        $this->prepareScript();
    }
    
    public function getScriptToExec() {
        return $this->script;
    }
    
    public function hasData() {
        return $this->hasData;
    }
    
    public function __destruct() {
        if(!is_null($this->filehandle)) {
            fclose($this->filehandle);
        }
    }
}
