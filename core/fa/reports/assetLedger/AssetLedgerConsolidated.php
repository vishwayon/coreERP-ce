<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\reports\assetLedger;

/**
 * Description of AssetLedgerConsolidatedValidator
 *
 * @author shrishail
 */
class AssetLedgerConsolidated extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        //validate branch id
        if($rptOption->rptParams['pbranch_id']=='' or $rptOption->rptParams['pbranch_id']==-1){
            array_push($rptOption->brokenRules, 'Please select branch.');
        }
        
        //Validate asset class 
        if($rptOption->rptParams['passet_class_id']=='' or $rptOption->rptParams['passet_class_id']==-1){
                array_push($rptOption->brokenRules, 'Please select asset class.');
            } 
        
        return $rptOption;
    }
}

