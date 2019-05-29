<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\generalLedgerRestricted;
/**
 * Description of GeneralLedgerValidator
 *
 * @author shrishail
 */
class GeneralLedgerRestricted extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
        
        if($this->allowConsolidated && $rptOption->rptParams['pbranch_id']==0) {
            if($rptOption->rptParams['pfilter_gst_state']) {
                $newBrId = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 500000;
                $newBrId = $newBrId + intval(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
                $rptOption->rptParams['pbranch_id'] = $newBrId;
            }
        }
        
        if($rptOption->rptParams["psub_interval"] == -1) {
            array_push($rptOption->brokenRules, 'Please select balancing period.');
        }
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if($rptOption->rptParams['paccount_id']=='' || $rptOption->rptParams['paccount_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select an Account to view.');
        }
        
        if($rptOption->rptParams['paccount_id']=='-99' && $rptOption->rptParams['pcategory']=='Any'){
            array_push($rptOption->brokenRules, 'Please select a specific Book while generating report for all accounts.');
        }
        
        if ($rptOption->rptParams['pdisplay_fc_amount']==1){
            $rptOption->rptName='GeneralLedgerFC'; 
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        if($rptOption->rptParams['pcategory']=='Bank'){
            $rptOption->rptParams['preport_caption'] = 'Bank Book';
        }
        if($rptOption->rptParams['pcategory']=='Cash'){
            $rptOption->rptParams['preport_caption'] = 'Cash Book';
            $rptOption->rptName='CashLedger'; 
        }
        if($rptOption->rptParams['pcategory']=='GL'){
            $rptOption->rptParams['preport_caption'] = 'General Ledger';
        }
        if($rptOption->rptParams['pcategory']=='Any'){
            $rptOption->rptParams['preport_caption'] = 'Ledger Report';
        }
        if($rptOption->rptParams['pcategory']=='Debtors'){
            $rptOption->rptParams['preport_caption'] = 'Debtors Report';
        }
        if($rptOption->rptParams['pcategory']=='Creditors'){
            $rptOption->rptParams['preport_caption'] = 'Creditors Report';
        }
        
        return $rptOption;
    }
}
