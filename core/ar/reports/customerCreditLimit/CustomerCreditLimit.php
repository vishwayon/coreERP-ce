<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\customerCreditLimit;
/**
 * Description of CustomerCreditLimit
 *
 * @author Priyanka
 */
class CustomerCreditLimit extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['pcustomer_id']=='' OR $rptOption->rptParams['pcustomer_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Customer.');
        }
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //*** select rpt name to be opened as per selected report ***
                //Customer Credit Limit
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='CustomerCreditLimit';  
        }
        //Customer Credit Limit Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='CustomerCreditLimitDetailed';
        }
        //Customer Credit Limit By Branch
        elseif ($rptOption->rptParams['preport_type'] == 2) 
        {
            $rptOption->rptName='CustomerCreditLimitByBranch';
        }
        return $rptOption;
    }
}
