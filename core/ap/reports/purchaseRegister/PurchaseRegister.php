<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\reports\purchaseRegister;

/**
 * Description of PayableLedgerValidator
 *
 * @author Kaustubh
 */
class PurchaseRegister extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if ($rptOption->rptParams['preport_type'] == 0) {
            $rptOption->rptName = 'PurchaseRegister';
        } elseif ($rptOption->rptParams['preport_type'] == 1) {
            $rptOption->rptName = 'PurchaseRegisterIP';
        }elseif ($rptOption->rptParams['preport_type'] == 2) {
            $rptOption->rptName = 'PurchaseRegisterRC';
        }elseif ($rptOption->rptParams['preport_type'] == 3) {
            $rptOption->rptName = 'RecwithGSTR2A';
            $rptOption->preport_caption =  'Reconciled with GSTR2A';
        }elseif ($rptOption->rptParams['preport_type'] == 4) {
            $rptOption->rptName = 'UnRecwithGSTR2A';
            $rptOption->preport_caption =  'Unreconciled with GSTR2A';        
        }
        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }

        if ($this->allowConsolidated && ($rptOption->rptParams['pbranch_id'] == '' || $rptOption->rptParams['pbranch_id'] == '-1')) {
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }

        if ($this->allowConsolidated && $rptOption->rptParams['pbranch_id'] == 0) {
            if ($rptOption->rptParams['pfilter_gst_state']) {
                $newBrId = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 500000;
                $newBrId = $newBrId + intval(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
                $rptOption->rptParams['pbranch_id'] = $newBrId;
            }
        }
//        if ($rptOption->rptParams['psupplier_id'] == '0' And $rptOption->rptParams['pgroup_path'] == 'All' && $rptOption->rptParams['psummary'] == false) {
//            array_push($rptOption->brokenRules, 'Please Select specific Supplier Or specific Account Group.');
//        }
        if ($rptOption->rptParams['psupplier_id'] == '' OR $rptOption->rptParams['psupplier_id'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Supplier.');
        }

        if ($rptOption->rptParams['pgst_state_id'] == '' OR $rptOption->rptParams['pgst_state_id'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select GST State.');
        }

        if ($rptOption->rptParams['psummary'] && $rptOption->rptParams['pgroup_path'] != 'All') {
            array_push($rptOption->brokenRules, 'Please Select Account Group All to view summary.');
        }
        
        if ($rptOption->rptParams['psummary']){            
            if ($rptOption->rptParams['preport_type'] == 3) {
                $rptOption->rptName = 'PRSummaryRecWithGSTR2A';

            }elseif ($rptOption->rptParams['preport_type'] == 4){
              $rptOption->rptName = 'PRSummaryUnRecWithGSTR2A';

            }else{
                $rptOption->rptName = 'PurchaseRegisterSummary';
        }}
        
        if ($rptOption->rptParams['pgroup_path'] == 'All') {
            $rptOption->rptParams['preport_caption'] = "Purchase Register";
        } else if ($rptOption->rptParams['pgroup_path'] == 'A005') {
            $rptOption->rptParams['preport_caption'] = "Purchase Register (COGC)";
        } else if ($rptOption->rptParams['pgroup_path'] == 'A006') {
            $rptOption->rptParams['preport_caption'] = "Purchase Register (Expenses)";
        } else if ($rptOption->rptParams['pgroup_path'] == 'A001') {
            $rptOption->rptParams['preport_caption'] = "Purchase Register (Assets)";
        } else if ($rptOption->rptParams['pgroup_path'] == 'NX') {
            $rptOption->rptParams['preport_caption'] = "Purchase Register (Non Expenses)";
        }

        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        return $rptOption;
    }

}
