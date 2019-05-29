<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\tdsRecoStmt;

/**
 * Description of BankRecoReportValidator
 *
 * @author shrishail
 */
class TDSRecoStmt extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        if($rptOption->rptParams['pview']==-1){
            array_push($rptOption->brokenRules, 'Please Select view.');
        }
        if($rptOption->rptParams['pcustomer_id']=='' or $rptOption->rptParams['pcustomer_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Customer.');
        }
                    
        if(strtotime($rptOption->rptParams['pas_on'])>strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
            array_push($rptOption->brokenRules, 'As on date cannot be greater than Year End.');
        }
        
        if ($rptOption->rptParams['pview']!=0){
            if($rptOption->rptParams['pview'] == 1){                
                $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on"])." (Reconciled Items) ";        
            }
            else{
                $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on"])." (All Items) ";        
            }
        }
        else{
            $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on"])." (Unreconciled Items) ";
            $rptOption->rptParams['pyear'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        }
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}
