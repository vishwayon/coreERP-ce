<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\purchaseVsSales;

/**
 * Description of SalesAnalysisValidator
 *
 * @author Kaustubh
 */
class PurchaseVsSales extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        //*** Validations ***
        if ($rptOption->rptParams['ptype'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Report.');
        }
        
        if ($rptOption->rptParams['pmaterial_type_id'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Stock Type.');
        }
        if ($rptOption->rptParams['pmaterial_id'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Stock Item.');
        }
        if ($rptOption->rptParams['pmaterial_id'] == -2) {
            $rptOption->rptParams['pmaterial_id'] = 0;
        }

        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }


        //*** Selection of Report Name ***
        //*** Report = By Customer ***
        if ($rptOption->rptParams['ptype'] == 0) {
            $rptOption->rptName = 'PurchaseVsSalesByStockType';
        } else if ($rptOption->rptParams['ptype'] == 1) {
            $rptOption->rptName = 'PurchaseVsSalesByStockItem';
        }

        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        return $rptOption;
    }

}
