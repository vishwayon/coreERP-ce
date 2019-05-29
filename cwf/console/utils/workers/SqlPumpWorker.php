<?php

namespace app\cwf\console\utils\workers;

/**
 * SqlPumpWorker can be used to run scripts that are 
 * separated by ?==?
 * Create a xml file listing the file names for each file to be executed
 * The run can be scheduled as a service and will execute for each company
 * in main_db.
 * 
 * An error in the script will skip the company and move to the next
 */

class SqlPumpWorker {
    /** @var \app\cwf\vsla\utils\ConfigReader */
    private $cwfConfig;
    private $outstream;
    
    /**
     * Contains an instance of DbConnection
     * @var \app\cwf\console\installer\workers\DbCon 
     */
    private $dbCon;
    
    /**
     * Contains list of companies retrieved from main_db
     * @var app\cwf\vsla\data\DataTable A collection of companies
     */
    private $_compList;
    
    public function __construct(\app\cwf\vsla\utils\ConfigReader $cwfConfig, $outstream, $xfile_path) {
        $this->cwfConfig = $cwfConfig;
        $this->outstream = $outstream;
        $this->xfile_path = $xfile_path;
        $this->dbCon = new \app\cwf\console\installer\workers\DbCon($this->cwfConfig);
    }
    
    public function run() {
        $this->setComps();
        fwrite($this->outstream, "Loading xml: $this->xfile_path\n");
        $sfiles = $this->readFromXml($this->xfile_path);
        fwrite($this->outstream, "Script Files to execute: ".count($sfiles)."\n");
        foreach($this->_compList as $comp) {
            fwrite($this->outstream, "Database: ".$comp['database']."\n");
            $cn = $this->dbCon->getCnCompanyDB($comp['database']);
            try {
                foreach($sfiles as $sfile) {
                    fwrite($this->outstream, "Comp: ".$comp['company_name']." Executing File: $sfile\n");
                    $this->execScript($cn, $comp, $sfile);
                }
            } catch (\Exception $ex) {
                fwrite($this->outstream, "Exception: ".$comp['company_name']." Executing File: $sfile\n".$ex->getMessage()."\n".$ex->getTraceAsString());
            }
            $cn = null; // closes the connection
        }
    }
    
    private function setComps() {
        $cn = $this->dbCon->getCnMainDB();
        $query = $cn->prepare("Select * from sys.company Order by company_id");
        $query->execute();
        $this->_compList = $query->fetchAll();
        $cn = null;
    }
    
    private function readFromXml(string $xml_file_path) {
        $files = [];
        $file_path = substr($xml_file_path, 0, strrpos($xml_file_path, '/'));
        $xsf = \simplexml_load_file($xml_file_path);
        foreach($xsf->files->children() as $xfile) {
            $files[] = $file_path.(string)$xfile;
        }
        return $files;
    }
    
    /**
     * Executes a script file on an open connection
     * @param \PDO $cn
     * @param string $sfile_path
     */
    private function execScript($cn, $comp, string $sfile_path) {
        $sp = new \app\cwf\console\installer\workers\ScriptParser($sfile_path, $comp, $this->cwfConfig);
        while($sp->hasData()) {
            if(strlen($sp->getScriptToExec())>0) {
                $sql = $sp->getScriptToExec();
                fwrite($this->outstream, "Executing --**--\n$sql\n--**--\n");
                $query = $cn->prepare($sql);
                $query->execute();
            }
            $sp->moveNext();
        }
        fwrite($this->outstream, 'File Completed'."\n");
    }
        
}