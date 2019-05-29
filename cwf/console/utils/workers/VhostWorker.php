<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\console\utils\workers;

/**
 * Description of VHostWorker
 *
 * @author girish
 */
class VhostWorker {
    /** @var \app\cwf\vsla\utils\ConfigReader */
    private $cwfConfig;
    private $outstream;    
    private $sourcePath;
    
    public function __construct(\app\cwf\vsla\utils\ConfigReader $cwfConfig, $outstream, $sourcePath) {
        $this->cwfConfig = $cwfConfig;
        $this->outstream = $outstream;
        $this->sourcePath = $sourcePath;
    }
    
    public function run() {
        $dbCon = new \app\cwf\console\installer\workers\DbCon($this->cwfConfig);
        $cn = $dbCon->getCnMainDB();
        $sql = "Select site_id, site_name From sys.site Where created=false;";
        $query = $cn->prepare($sql);
        $query->execute();
        $result = $query->fetchAll();
        
        foreach($result as $row) {
            $hostname = $row['site_name'];
            $vhost = new \app\cwf\vsla\utils\VirtualHost($hostname, $this->sourcePath);
            try {
                $vhost->run();
                $sql = "Update sys.site set create=true Where site_id=".$row['site_id'];
                $query = $cn->prepare($sql);
                $query->execute();
        
            } catch (\Exception $ex) {
                fwrite($this->outstream, $ex->getMessage()."\n");
            }
        }
    }
}
