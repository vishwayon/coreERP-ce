<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\taxPayable;

/**
 * Description of TaxPayableReportValidator
 *
 * @author shrishail
 */
class TaxPayable extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams["preport_type"] == -1 || $rptOption->rptParams["preport_type"] == "-1"){
            array_push($rptOption->brokenRules, 'Please select Report Type.');
        }
        
        if($rptOption->rptParams["pbranch_id"] == -1 || $rptOption->rptParams["pbranch_id"] == "-1"){
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }
        
        if($rptOption->rptParams["pview_type"] == -1 || $rptOption->rptParams["pview_type"] == "-1"){
            array_push($rptOption->brokenRules, 'Please select View Type.');
        }
        
        if ($rptOption->rptParams['preport_type'] == 0 && $rptOption->rptParams['pview_type'] == 0){
            $rptOption->rptName='TaxPayableByTaxTypeInvoiceType';
        }
        else if ($rptOption->rptParams['preport_type'] == 0 && $rptOption->rptParams['pview_type'] == 1){
            $rptOption->rptName='TaxPayableByTaxTypeInvoiceDetail';
        }
        else if ($rptOption->rptParams['preport_type'] == 1 && $rptOption->rptParams['pview_type'] == 0){
            $rptOption->rptName='TaxPayableByTaxScheduleInvoiceType';
        }
        else if ($rptOption->rptParams['preport_type'] == 1 && $rptOption->rptParams['pview_type'] == 1){
            $rptOption->rptName='TaxPayableByTaxScheduleInvoiceDetail';
        }
        else if ($rptOption->rptParams['preport_type'] == 2 && $rptOption->rptParams['pview_type'] == 0){
            $rptOption->rptName='TaxPayableByTaxScheduleWithDetailInvoiceType';
        }
        else if ($rptOption->rptParams['preport_type'] == 2 && $rptOption->rptParams['pview_type'] == 1){
            $rptOption->rptName='TaxPayableByTaxScheduleWithDetailInvoiceDetail';
        }
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        return $rptOption;
    }
}
