<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\customerDueBySalesman;
/**
 * Description of CustomerDueBySalesman
 *
 * @author Priyanka
 */
class CustomerDueBySalesman extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }        
//        
//        if($rptOption->rptParams['paccount_id'] == '' || $rptOption->rptParams['paccount_id'] == '-1'){
//            if($rptOption->rptParams['preport_type'] == 2 || $rptOption->rptParams['preport_type'] == 3){
//                $rptOption->rptParams['paccount_id'] = 0;
//            }
//            else{
//                array_push($rptOption->brokenRules, 'Please Select Customer.');
//            } 
//        }
//        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //*** select rpt name to be opened as per selected report ***
        
        //Salesman Outstanding Summary
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='SalesmanOutstandingSummary';  
        }
        //Salesman Outstanding Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='SalesmanOutstandingDetailed';
        }
        //Ageing Analysis Summary
        elseif ($rptOption->rptParams['preport_type'] == 2) 
        {
            $rptOption->rptName='AgeingAnalysis';
        }
        //Ageing Analysis Detailed
        elseif ($rptOption->rptParams['preport_type'] == 3) 
        {
            if ($rptOption->rptParams['psub_tot']) {
                $rptOption->rptName = 'AgeingAnalysisDetailedSubTot';
            } Else {
                $rptOption->rptName = 'AgeingAnalysisDetailed';
            }
        }
        return $rptOption;
    }
}
