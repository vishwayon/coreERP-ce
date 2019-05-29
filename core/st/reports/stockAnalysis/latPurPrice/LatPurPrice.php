<?php

namespace app\core\st\reports\stockAnalysis\latPurPrice;
/**
 * Latest Purchase Price
 * @author Valli
 */

class LatPurPrice extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        $rptCaption = "As On ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pas_on_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //*** select rpt name to be opened as per selected report ***
        if($rptOption->rptParams['pgroup_by'] == 1)
        {
            $rptOption->rptName='LatPurPrice_StockType';  
        }
        //Supplier Overdue Detailed
        elseif ($rptOption->rptParams['pgroup_by'] == 2) 
        {
            $rptOption->rptName='LatPurPrice_StockItem';
        }
        elseif ($rptOption->rptParams['pgroup_by'] == 3) 
        {
            $rptOption->rptName='LatPurPrice_Supplier';
        }               
        return $rptOption;
    }
}
