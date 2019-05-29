<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep\worker;

interface IAssetDepWorker
{
    public function DepDateFrom();
    
    public function DepDateTo();
    
    public function AssetDepLedger();
}