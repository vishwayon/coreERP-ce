<?php

namespace app\core\st\reports\stockTransferList;
/**
 * Stock Transfer List
 * @author Valli
 */

class StockTransferList extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        //*** Selection of Report Name ***
      
        if ($rptOption->rptParams['preport_type']==1) { 
            $rptOption->rptName='StockTransferListSummary';   
        }
        
        if ($rptOption->rptParams['preport_type']==2) { 
            $rptOption->rptName='StockTransferList'; 
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
                       
        return $rptOption;
    }
}
