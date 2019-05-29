<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

define("APACHE_VHOST_PATH", "/etc/apache2/sites-available");
define("APACHE_GROUP", "www-data");

/**
 * Description of VirtualHost
 *
 * @author girish
 */
class VirtualHost {
    private $hostname;
    private $sourcePath;
    
    public function __construct($hostname, $sourcePath) {
        $this->hostname = $hostname;
        $this->sourcePath = $sourcePath;
    }
    
    public function run() {
        $this->createVHost();
        $this->createPublishPath();
        $this->en2apacheRestart();
    }
    
    private function createVHost() {
        $filename = APACHE_VHOST_PATH . "/" . strtolower($this->hostname) . ".conf";
        if (file_exists($filename) ) {
            throw new \Exception($this->lang['vhost_exists'], 1);
        }
        
        $vhc = array(); //virtual_host_content
        $vhc[] = "### Created by coreERP: " . date("Y-m-d H:i:s") . "###";
        $vhc[] = "<VirtualHost *:80>";
        $vhc[] = "\tServerName {$this->hostname}";
        $vhc[] = "\tRedirect permanent / https://{$this->hostname}/";
        $vhc[] = "\tServerAdmin hostmaster@{$this->hostname}";
        $vhc[] = "</VirtualHost>";
        
        $vhc[] = "<VirtualHost *:443>";
        $vhc[] = "\tServerName {$this->hostname}";
        $vhc[] = "\tServerAdmin support@coreerp.com";
        $vhc[] = "\tDocumentRoot /var/www/{$this->hostname}";
        $vhc[] = "\tSSLEngine on";
        $vhc[] = "\tSSLCertificateFile /home/girish/vspl_coreerp_in_cert/coreerp.crt";
        $vhc[] = "\tSSLCertificateKeyFile /home/girish/vspl_coreerp_in_cert/coreerp.key";
        $vhc[] = "\tSSLCertificateChainFile /home/girish/vspl_coreerp_in_cert/intermediate.crt";
        
        $vhc[] = "\tErrorLog \${APACHE_LOG_DIR}/{$this->hostname}-error.log";
        $vhc[] = "\tCustomLog \${APACHE_LOG_DIR}/{$this->hostname}-access.log combined";
        
        $vhc[] = "</VirtualHost>";
        
        $f = file_put_contents($filename, implode("\n", $vhc));
        
        unset($vhc);
        
        if ( !$f ) {
            throw new \Exception('vhost_create_error');
        }
    }
    
    private function createPublishPath() {
        $p1 = symlink($this->sourcePath, "/var/www/html/{$this->hostname}");

        if ( !$p1) {
            throw new \Exception('Failed to create link in /var/www');			
        }
    }
    
    private function en2apacheRestart() {
        $filename = strtolower($this->hostname) . ".conf";
        $output = shell_exec('a2ensite '.$filename);
        $output = shell_exec('/etc/init.d/apache2 reload');
    }
}
