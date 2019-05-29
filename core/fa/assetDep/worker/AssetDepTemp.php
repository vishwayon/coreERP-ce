<?php

namespace app\core\fa\assetDep\worker;

class AssetDepTemp implements IAssetDepWorker {
//    
    private $depDateFrom=null;
    private $depDateTo = null;
    private $dtAssetDepLedger=null;
    
    
    public function __construct($depdatefrom, $depdateto, $dtassetdepledger) {
        $this->depDateFrom=$depdatefrom;
        $this->depDateTo=$depdateto;
        $this->dtAssetDepLedger= $dtassetdepledger;        
    }
    
    public function DepDateFrom() {
        return $this->depDateFrom;
    }
    
    public function DepDateTo() {
        return $this->depDateTo;        
    }
    
    public function AssetDepLedger() {
        return $this->dtAssetDepLedger;
    }
}
