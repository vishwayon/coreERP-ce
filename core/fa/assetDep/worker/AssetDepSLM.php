<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep\worker;

class AssetDepSLM extends AssetDepBase{
    public function __construct($parent_worker) {
        parent::__construct($parent_worker, AssetDepBase::STRAIGHT_LINE_METHOD);
    }
    
    public function GetDepAmt($asset_item_row) {
        parent::GetDepAmt($asset_item_row);
        $dep_amt=0;
        $dep_rate =  parent::GetDepRate($asset_item_row['asset_class_id']);
        $dep_days = parent::GetDepDays($asset_item_row);
        
        // SLM always uses the purchase amount for dep.
        $base_amt=$asset_item_row['purchase_amt'];
        $gross_dep_amt= $base_amt * $dep_rate / 100;
        $dep_amt= round(($gross_dep_amt/ parent::$days_in_year) * $dep_days, \app\cwf\vsla\Math::$amtScale);
        
        return $dep_amt;
    }
}