<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\stockConsumpByType;

/**
 * Description of SalesAnalysisValidator
 *
 * @author Kaustubh
 */
class StockConsumpByType extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_ids']=='' || $rptOption->rptParams['pbranch_ids']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }
        
        //*** Validations ***
        if ($rptOption->rptParams['pReport'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Report.');
        }

        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }


        //*** Selection of Report Name ***
        //*** Report = Summary By Customer ***
        if ($rptOption->rptParams['pReport'] == 0) {
            $rptOption->rptName = 'ConsumpByTypeSummary';
            $rptOption->rptParams['prpt_caption'] = "Stock Consumption Summary";
        }
        //*** Report = By Customer Detailed***
        if ($rptOption->rptParams['pReport'] == 1) {
            $rptOption->rptName = 'ConsumpByTypeDetailed';
            $rptOption->rptParams['prpt_caption'] = "Stock Consumption Detailed";
        }
        //*** Report = Summary By Material ***
        if ($rptOption->rptParams['pReport'] == 2) {
            $rptOption->rptName = 'ConsumpByMatSummary';
            $rptOption->rptParams['prpt_caption'] = "Stock Consumption By Stock Item Summary";
        }
        //*** Report = By Material Detaiiled***
        if ($rptOption->rptParams['pReport'] == 3) {
            $rptOption->rptName = 'ConsumpByMatDetailed';
            $rptOption->rptParams['prpt_caption'] = "Stock Consumption By Stock Item Detailed";
        }

        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        return $rptOption;
    }

}
