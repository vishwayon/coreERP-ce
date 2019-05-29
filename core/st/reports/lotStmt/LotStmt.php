<?php

namespace app\core\st\reports\lotStmt;

class LotStmt extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if ($rptOption->rptParams['preport_type'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }

        if ($rptOption->rptParams['pmaterial_id'] == '' || $rptOption->rptParams['pmaterial_id'] == '-1') {
            array_push($rptOption->brokenRules, 'Please Select Stock Item.');
        }

        if ($rptOption->rptParams['pmaterial_id'] == -2) {
            $rptOption->rptParams['pmaterial_id'] = 0;
        }
//
//        if ($rptOption->rptParams['paccount_id'] == '0' &&
//                ($rptOption->rptParams['preport_type'] == 0 || $rptOption->rptParams['preport_type'] == 1 || $rptOption->rptParams['preport_type'] == 4)) {
//            array_push($rptOption->brokenRules, 'Select a Supplier for SOA. Option - All is available only for Ageing');
//        }


        if ($rptOption->rptParams['pstock_location_id'] == '' || $rptOption->rptParams['pstock_location_id'] == '-1' || $rptOption->rptParams['pstock_location_id'] == null) {
            $rptOption->rptParams['pstock_location_id'] = 0;
        }

        if ($rptOption->rptParams['pstock_location_id'] != -1) {
            $rptOption->rptParams['pstock_location_name'] = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/StockLocation.xml', 'stock_location_name', 'stock_location_id', $rptOption->rptParams['pstock_location_id']);
        }
        $rptOption->rptParams['pmat_name'] = '';
        if ($rptOption->rptParams['pmaterial_id'] != 0) {
            $rptOption->rptParams['pmat_name'] = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/Material.xml', 'material_name', 'material_id', $rptOption->rptParams['pmaterial_id']);
        }
        
        $rptCaption = "As on " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        //*** select rpt name to be opened as per selected report ***
        //Stock Lot Statement
        if ($rptOption->rptParams['preport_type'] == 0) {
            $rptOption->rptName = 'LotStmt';
        }
        //Stock Lot Statement  Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) {
            $rptOption->rptName = 'LotStmtDetailed';
        }
        //Stock Summary
        elseif ($rptOption->rptParams['preport_type'] == 2) {
            $rptOption->rptName = 'LotStockSummary';
        }
        //Stock Lot Statement Txn
        elseif ($rptOption->rptParams['preport_type'] == 3) {
            $rptOption->rptName = 'LotStmtTxn';

            $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"])." And " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        }
        elseif ($rptOption->rptParams['preport_type'] == 4) {
            $rptOption->rptName = 'LotStmtCons';
        }
        elseif ($rptOption->rptParams['preport_type'] == 5) {
            $rptOption->rptName = 'LotStmtConsTS';
        }
        return $rptOption;
    }

}
