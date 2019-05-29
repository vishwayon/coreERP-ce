<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\subHeadLedger;
/**
 * Description of SubHeadLedger
 *
 * @author Priyanka
 */
class SubHeadLedger extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }        
        
        $rptOption->rptParams['pshow_opbl'] = true;
        if($rptOption->rptParams['paccount_id'] == '' || $rptOption->rptParams['paccount_id'] == '-1'){
            array_push($rptOption->brokenRules, 'Please Select Account.');
        }        
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if($rptOption->rptParams['psub_head_id'] == '' || $rptOption->rptParams['psub_head_id'] == '-1'){
            array_push($rptOption->brokenRules, 'Please Select Sub Head.');
        }
        
        $rptPeriod = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptPeriod;
        
        //*** select rpt name to be opened as per selected report ***
        
        //Statement 
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='SubHeadLedger';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Ledger';
        }
        //Statement Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='SubHeadLedgerDetailed';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Ledger - Detailed';
        }
        elseif($rptOption->rptParams['preport_type'] == 2)
        {
            $rptOption->rptName='SubHeadLedgerByAcc';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Ledger - By Account';
        }
        //Statement Detailed
        elseif ($rptOption->rptParams['preport_type'] == 3) 
        {
            $rptOption->rptName='SubHeadLedgerByAccDetailed';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Ledger - By Account Detailed';
        }
        elseif ($rptOption->rptParams['preport_type'] == 4) 
        {
            $rptOption->rptName='SubHeadTxnSummary';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Transaction Summary';
            if($rptOption->rptParams['paccount_id'] == -99) {
                $rptOption->rptParams['paccount_id'] = 0;
            }
        }
        elseif ($rptOption->rptParams['preport_type'] == 5) 
        {
            $rptOption->rptName='SubHeadLedgerTxn';
            $rptOption->rptParams['preport_caption'] = 'Sub Head Ledger - Transactions Only';
        }
        return $rptOption;
    }
}
