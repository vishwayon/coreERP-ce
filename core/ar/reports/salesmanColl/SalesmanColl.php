<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\salesmanColl;
/**
 * Description of Salesman Collection
 *
 * @author Priyanka
 */
class SalesmanCOll extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);        
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
                
        if($rptOption->rptParams['pcustomer_id']=='' OR $rptOption->rptParams['pcustomer_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Customer.');
        } 
        
        if($rptOption->rptParams['psalesman_id']=='' OR $rptOption->rptParams['psalesman_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Salesman.');
        } 
        
        $rptCaption = "As On ". \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //Salesman Collection Summary
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='SalesmanCollSummary';  
        }
        //Salesman Invoice Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='SalesmanCollDetailed';
        }
        //Salesman Receipt Detailed
        elseif ($rptOption->rptParams['preport_type'] == 2) 
        {
            $rptOption->rptName='SalesmanCollRcptDetailed';
        }
        
        return $rptOption;
    }
}
