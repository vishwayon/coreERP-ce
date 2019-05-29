<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\refLedger;
/**
 * Description of StmtOfAccountsValidator
 *
 * @author Kaustubh
 */
class RefLedger extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }        
        
        if($rptOption->rptParams['paccount_id'] == '' || $rptOption->rptParams['paccount_id'] == '-1'){
            array_push($rptOption->brokenRules, 'Please Select Account.');
        }
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //*** select rpt name to be opened as per selected report ***
        
        //Statement 
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='RefLedgerStmt';
        }
        //Statement Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='RefLedgerStmtDetailed';
        }
        return $rptOption;
    }
}
