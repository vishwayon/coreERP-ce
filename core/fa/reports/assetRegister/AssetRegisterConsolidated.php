<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\reports\assetRegister;

/**
 * Description of AssetRegisterConsolidatedValidator
 *
 * @author shrishail
 */
class AssetRegisterConsolidated extends \app\cwf\fwShell\base\ReportBase {
    
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
        
        //validate type
        if($rptOption->rptParams['pis_summarized']=='' or $rptOption->rptParams['pis_summarized']==-1){
            array_push($rptOption->brokenRules, 'Please select type.');
        }
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["ppurchase_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}

