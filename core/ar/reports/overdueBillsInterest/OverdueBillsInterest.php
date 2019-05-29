<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\overdueBillsInterest;
/**
 * Description of OverdueBillInterest
 *
 * @author Vallimalar
 */
class OverdueBillsInterest extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        
        if($rptOption->rptParams['pcustomer_id']=='' OR $rptOption->rptParams['pcustomer_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Customer.');
        }
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
        
        if($rptOption->rptParams['ppercentage']<0){
            array_push($rptOption->brokenRules, 'Please specify valid percentage.');
        }
        
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='OverdueBillsInterestSummary';  
        }
        //Customer Overdue Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='OverdueBillsInterest';
        }
        
        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;
       
    }
}
