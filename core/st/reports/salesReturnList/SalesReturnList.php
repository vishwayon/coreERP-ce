<?php

namespace app\core\st\reports\salesReturnList;
/**
 * Sales Return List report
 * @author Valli
 */

class SalesReturnList extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        //*** Selection of Report Name ***
        
         if ($rptOption->rptParams['psrr_id']==0){
            $rptOption->rptParams["psrr_desc"] = 'All';}
        else{             
            $rptOption->rptParams["psrr_desc"] = \app\cwf\vsla\utils\LookupHelper::GetLookupText(
                    '../core/st/lookups/SrrWithAll.xml', 
                    'srr_desc', 
                    'srr_id', 
                    intval($rptOption->rptParams["psrr_id"])
                );
        }
        
        if ($rptOption->rptParams['preport_type']==1) { 
            $rptOption->rptName='SalesReturnList';   
        }
        
        if ($rptOption->rptParams['preport_type']==2) { 
            $rptOption->rptName='SalesReturnListWithTax'; 
        }
        
        if ($rptOption->rptParams['preport_type']==3) { 
            $rptOption->rptName='SalesReturnListSummaryByMat'; 
        }
        
        if ($rptOption->rptParams['preport_type']==4) { 
            $rptOption->rptName='SalesReturnListSummaryByCus';   
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
                       
        return $rptOption;
    }
}
