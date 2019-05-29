<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\reports\payableLedger;
/**
 * Description of PayableLedgerValidator
 *
 * @author Kaustubh
 */
class PayableLedger extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
         if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }
        
        if($rptOption->rptParams["psub_interval"] == -1) {
            array_push($rptOption->brokenRules, 'Please select balancing period.');
        }
        
        if($rptOption->rptParams['paccount_id']=='' OR $rptOption->rptParams['paccount_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Supplier.');
        } 
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}
