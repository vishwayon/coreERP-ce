<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\tdsWithheld;
/**
 * Description of BusinessTurnover
 *
 * @author Priyanka
 */
class TDSWithheld extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }              
        
        $rptOption->rptParams['preport_period'] = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        //*** select rpt name to be opened as per selected report ***
        
        //Business Turnover By Customer Summary
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='TDSWithheld';    
        }
        //Business Turnover By Customer Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='TDSWithheldDetailed';
        }
        return $rptOption;
    }
}
