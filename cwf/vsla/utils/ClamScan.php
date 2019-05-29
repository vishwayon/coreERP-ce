<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

use Socket\Raw\Socket;

/**
 * Class wrapper for ClamScan AV Daemon
 * To Install: composer require "blurgroup/quahog": "0.*"
 * Dependency: clue/socket-raw
 * OS dependencies: sudo apt-get install clamav clamav-daemon
 * To activate: Insert in components fileAVScan
 * include user clamav in daemon/www-data group
 * Change owner of tmpPath to daemon/www-data
 * @author girish
 */
class ClamScan {
    
    public $tmpPath = '';
    
    public function scanFile($filePath) {
        // copy file
        $fname = uniqid('F');
        copy($filePath, $this->tmpPath.$fname);
        
        $factory = new \Socket\Raw\Factory();
        $socket = $factory->createClient('unix:///var/run/clamav/clamd.ctl', 'unix');
        $quahog = new \quahog\Client($socket);
        
        //$result = $quahog->ping();
        
        // scan the file
        $result = $quahog->scanFile($this->tmpPath.$fname);
        
        // remove file copy
        unlink($this->tmpPath.$fname);
                
        return $result;        
    }
}
