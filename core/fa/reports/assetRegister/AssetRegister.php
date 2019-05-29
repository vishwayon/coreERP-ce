<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\reports\assetRegister;

/**
 * Description of AssetRegisterValidator
 *
 * @author shrishail
 */
class AssetRegister extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        //Validate asset class 

        if($rptOption->rptParams['passet_class_id']=='' or $rptOption->rptParams['passet_class_id']==-1){
            array_push($rptOption->brokenRules, 'Please select asset class.');
        }
        
        //validate type
        if($rptOption->rptParams['pis_summarized']=='' or $rptOption->rptParams['pis_summarized']==-1){
            array_push($rptOption->brokenRules, 'Please select type.');
        }
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}

