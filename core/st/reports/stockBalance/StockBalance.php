<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\stockBalance;

/**
 * Description of Stock Balance
 *
 * @author Priyanka
 */
class StockBalance extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }

        if ($rptOption->rptParams['preport_type'] == 0) {
            $rptOption->rptName = 'StockClosingBalWAC';
        } elseif ($rptOption->rptParams['preport_type'] == 1) {
            $rptOption->rptName = 'StockMovementValWAC';
        } elseif ($rptOption->rptParams['preport_type'] == 2) {
            $rptOption->rptName = 'StockMovementValQtyWAC';
        } elseif ($rptOption->rptParams['preport_type'] == 3) {
            $rptOption->rptName = 'StockMovementQty';
        } elseif ($rptOption->rptParams['preport_type'] == 4) {
            $rptOption->rptName = 'StockBalanceColumnar';
        } elseif ($rptOption->rptParams['preport_type'] == 5) {
            $rptOption->rptName = 'StockClosingBalByStockLocWAC';
        }

        if ($rptOption->rptParams['psl_id'] == '' || $rptOption->rptParams['psl_id'] == '-1' || $rptOption->rptParams['psl_id'] == null) {
            $rptOption->rptParams['psl_id'] = 0;
        }

        if ($rptOption->rptParams['psl_id'] != -1) {
            $rptOption->rptParams['psl_name'] = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/StockLocationAllforRpt.xml', 'stock_location_name', 'stock_location_id', $rptOption->rptParams['psl_id']);
        }
        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        // Avoid displaying a blank report
        if ($rptOption->rptParams['pmaterial_type_id'] == -1) {
            $rptOption->rptParams['pmaterial_type_id'] = 0;
        }

        $rptOption->rptParams['preport_period'] = $rptCaption;
        return $rptOption;
    }

}
