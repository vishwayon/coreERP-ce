<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\stockAnalysis\stockReorder;

/**
 * Description of Stock Reorder
 *
 * @author valli
 */

class StockReorder extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }   
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        $rptOption->rptParams['pstock_date'] =  \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on_date"]) ;
        
                
        //*** Filter condition for Filter Current Stock ***
        if($rptOption->rptParams['pfilter_by']==0)  //** Less than or Equal to Reorder Level
        {
            $rptOption->rptParams['pwhere_condition'] = " WHERE a.curr_stock < a.reorder_level and a.reorder_level > 0 ";
            $rptOption->rptParams['prpt_subcaption'] = "Current stock quantity less than reorder level quantity";
        } 
         
        if($rptOption->rptParams['pfilter_by']==1)  //** Greater than Reorder Level
        {
            $rptOption->rptParams['pwhere_condition'] = " WHERE a.curr_stock > a.reorder_level and a.reorder_level > 0 ";
            $rptOption->rptParams['prpt_subcaption'] = "Current stock quantity greater than reorder level quantity";   
        } 
        
        if($rptOption->rptParams['pfilter_by']==2)  //** With Reorder Level
        {
            $rptOption->rptParams['pwhere_condition'] = " WHERE a.reorder_level > 0 ";
            $rptOption->rptParams['prpt_subcaption'] = "Stock Items with reorder level quantity";
        } 
        
        if($rptOption->rptParams['pfilter_by']==3)  //** Without Reorder Level
        {
            $rptOption->rptParams['pwhere_condition'] = " WHERE (a.reorder_level <= 0 or a.reorder_level is null)";
            $rptOption->rptParams['prpt_subcaption'] = "Stock Items without reorder level quantity";
        }
        
        if($rptOption->rptParams['pfilter_by']==4)  //** All Stock Items
        {
            $rptOption->rptParams['pwhere_condition'] = " ";
            $rptOption->rptParams['prpt_subcaption'] = "All Stock Items";
        }
        return $rptOption;
    }
}

