<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\salesAnalysis;
/**
 * Description of SalesAnalysisValidator
 *
 * @author Kaustubh
 */
class SalesAnalysis extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }   
        //*** Validations ***
        if($rptOption->rptParams['pReport']==-1){
            array_push($rptOption->brokenRules, 'Please Select Report.');
        } 
        
        if($rptOption->rptParams['pcurrency_type']==-1){
            array_push($rptOption->brokenRules, 'Please Select Currency Type.');
        } 
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        
        //*** Selection of Report Name ***
        //*** Report = Summary By Customer ***
        if ($rptOption->rptParams['pReport']==0) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='CustomerWiseSummary';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='CustomerWiseSummary';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='CustomerWiseSummary'; 
            }   
        }
        //*** Report = By Customer Detailed***
        else if ($rptOption->rptParams['pReport']==1) {
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='CustomerWiseSalesReportAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='CustomerWiseSalesReportAll'; 
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='CustomerWiseSalesReport';   
            }           
        }
        //*** Report = Summary By Material ***
        else if ($rptOption->rptParams['pReport']==2) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='MaterialWiseSummaryAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='MaterialWiseSummaryAll';  
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='MaterialWiseSummary'; 
            }   
        }
        //*** Report = By Material Detaiiled***
        else if ($rptOption->rptParams['pReport']==3) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='MaterialWiseSalesReportAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='MaterialWiseSalesReportAll';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='MaterialWiseSalesReport';  
            }
        }
        //*** Report = By Material By Customer Summary ***
        else if ($rptOption->rptParams['pReport']==4) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='MaterialWiseCustomerSummaryAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='MaterialWiseCustomerSummaryAll';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='MaterialWiseCustomerSummary'; 
            }   
        }
        //*** Report = By Material By Customer Detailed ***
        else if ($rptOption->rptParams['pReport']==5) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='MaterialWiseCustomerSalesReportAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='MaterialWiseCustomerSalesReportAll';  
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='MaterialWiseCustomerSalesReport'; 
            }   
        }
        //*** Report = By Customer By Material Summary ***
        else if ($rptOption->rptParams['pReport']==6) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='CustomerWiseMaterialSummaryAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='CustomerWiseMaterialSummaryAll';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='CustomerWiseMaterialSummary'; 
            }   
        }
        //*** Report = By Customer By Material Detailed ***
        else if ($rptOption->rptParams['pReport']==7) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='CustomerWiseMaterialSalesReportAll';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='CustomerWiseMaterialSalesReportAll';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='CustomerWiseMaterialSalesReport';  
            }
        }
        //*** Report = By Date By Sales Account By Customer ***
        else if ($rptOption->rptParams['pReport']==8) { 
            if($rptOption->rptParams['pcurrency_type']==0){
                //*** Currency = All  ***
                $rptOption->rptParams['pwhere_condition'] = "";
                $rptOption->rptName='CustomerWiseBySalesAccSalesReport';   
            }
            else if($rptOption->rptParams['pcurrency_type']==2){
                //*** Currency = Foreign  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id != 0 ";
                $rptOption->rptName='CustomerWiseBySalesAccSalesReport';   
            }
            else {
                //*** Currency = Local  ***
                $rptOption->rptParams['pwhere_condition'] = " WHERE fc_type_id = 0 ";
                $rptOption->rptName='CustomerWiseBySalesAccSalesReport';  
            }
        }
                
        //*** Filter condition for Stock Type ***
        if($rptOption->rptParams['pmaterial_type_id']<>''){
            if($rptOption->rptParams['pmaterial_type_id']<>0){
                if($rptOption->rptParams['pwhere_condition'] == ""){
                    $rptOption->rptParams['pwhere_condition'] = " WHERE material_type_id IN ( " . $rptOption->rptParams['pmaterial_type_id'] . " ) ";
                }
                else {
                    $rptOption->rptParams['pwhere_condition'] = $rptOption->rptParams['pwhere_condition'] . " AND material_type_id IN ( " . $rptOption->rptParams['pmaterial_type_id'] . " ) ";
                }
            }
        } 
        
        //*** Filter condition for Stock Item ***
        if($rptOption->rptParams['pmaterial_id']<>''){
            if($rptOption->rptParams['pmaterial_id']<>-2){
                if($rptOption->rptParams['pwhere_condition'] == ""){
                    $rptOption->rptParams['pwhere_condition'] = " WHERE material_id IN ( " . $rptOption->rptParams['pmaterial_id'] . " ) ";
                }
                else {
                    $rptOption->rptParams['pwhere_condition'] = $rptOption->rptParams['pwhere_condition'] . " AND material_id IN ( " . $rptOption->rptParams['pmaterial_id'] . " ) ";
                }
            }
        } 
        
        //*** Filter condition for Customer ***
        if($rptOption->rptParams['paccount_id']<>''){
            if($rptOption->rptParams['paccount_id']<>-1){
                if($rptOption->rptParams['pwhere_condition'] == ""){
                $rptOption->rptParams['pwhere_condition'] = " WHERE account_id IN ( " . $rptOption->rptParams['paccount_id'] . " ) ";
                }
                else {
                    $rptOption->rptParams['pwhere_condition'] = $rptOption->rptParams['pwhere_condition'] . " AND account_id IN ( " . $rptOption->rptParams['paccount_id'] . " ) ";
                }
            }
        } 
        
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}
