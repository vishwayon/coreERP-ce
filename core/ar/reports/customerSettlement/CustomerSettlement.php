<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\customerSettlement;

/**
 * Description of SalesAnalysisValidator
 *
 * @author Kaustubh
 */
class CustomerSettlement extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if ($rptOption->rptParams['pbranch_id'] == '' || $rptOption->rptParams['pbranch_id'] == '-1') {
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }
        //*** Validations ***
        if ($rptOption->rptParams['pReport'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Report.');
        }

        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if($rptOption->rptParams['paccount_id'] == -99) {
            $rptOption->rptParams['paccount_id'] = 0;
        }
        
        //*** Selection of Report Name ***
        //*** Report = By Account Summary***
        if ($rptOption->rptParams['pReport'] == 0) {
            $rptOption->rptName = 'CustomerSettlementByAccount';
            $rptOption->rptParams['pshow_detail'] = false;
        } elseif ($rptOption->rptParams['pReport'] == 1) {
            $rptOption->rptName = 'CustomerSettlementByAccount';
            $rptOption->rptParams['pshow_detail'] = true;
        } elseif ($rptOption->rptParams['pReport'] == 2) {
            $rptOption->rptName = 'CustomerSettlementBySegment';
            $rptOption->rptParams['pshow_detail'] = false;
        }elseif ($rptOption->rptParams['pReport'] == 3) {
            $rptOption->rptName = 'CustomerSettlementBySegment';
            $rptOption->rptParams['pshow_detail'] = true;
        }


        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        return $rptOption;
    }

}
