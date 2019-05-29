<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\bankReco;

/**
 * Description of BankRecoReportValidator
 *
 * @author shrishail
 */
class BankRecoReport extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        if($rptOption->rptParams['pview']==-1){
            array_push($rptOption->brokenRules, 'Please Select view.');
        }
        if($rptOption->rptParams['paccount_id']=='' or $rptOption->rptParams['paccount_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Bank.');
        }
                    
        if(strtotime($rptOption->rptParams['pas_on'])>strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
            array_push($rptOption->brokenRules, 'As on date cannot be greater than Year End.');
        }
              
        if(strtotime($rptOption->rptParams['pfrom_date'])>strtotime($rptOption->rptParams['pas_on'])){
            array_push($rptOption->brokenRules, 'From Date cannot be greater than To Date/As on.');
        }
        
        if ($rptOption->rptParams['pview']!=0){
            $rptOption->rptName='BankRecoStatementReconciled';
            if($rptOption->rptParams['pview'] == 1){                
                $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"])." (Reconciled Items) ";        
            }
            else{
                $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"])." (All Items) ";        
            }
        }
        else{
            $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on"])." (Unreconciled Items) ";
            $rptOption->rptParams['pyear'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        }
         $rptOption->rptParams['preport_period'] = $rptCaption;
                
        if($rptOption->rptParams['paccount_id']!='' and $rptOption->rptParams['paccount_id']!=-1){
            $rptOption->rptParams['pbank_name']=  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ac/lookups/Account.xml', 'account_head', 'account_id', $rptOption->rptParams['paccount_id']);
        }
                
        return $rptOption;
    }
}
