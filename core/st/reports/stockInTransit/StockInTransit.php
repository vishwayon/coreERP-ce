<?php

namespace app\core\st\reports\stockInTransit;
/**
 * Stock In Transit report
 * @author Valli
 */

class StockInTransit extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        //*** Selection of Report Name ***
        
        if ($rptOption->rptParams['preport_type']==1) { 
            $rptOption->rptName='StockInTransitSummary';   
        }
        
        if ($rptOption->rptParams['preport_type']==2) { 
            $rptOption->rptName='StockInTransitDetail'; 
        }
        
        $rptCaption =  \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["ptransit_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
                       
        return $rptOption;
    }
}
