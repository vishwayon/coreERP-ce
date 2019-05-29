<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\businessTurnover;
/**
 * Description of BusinessTurnover
 *
 * @author Priyanka
 */
class BusinessTurnover extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }              
        
        $rptOption->rptParams['preport_period'] = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        //*** select rpt name to be opened as per selected report ***
        
        //Business Turnover By Customer Summary
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='BusinessTurnoverByCustomer';    
        }
        //Business Turnover By Customer Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='BusinessTurnoverByCustomerDetailed';
        }
        //Business Turnover By Salesman Summary
        elseif ($rptOption->rptParams['preport_type'] == 2) 
        {
            $rptOption->rptName='BusinessTurnoverBySalesman';
        }
        //Business Turnover By Salesman Detailed
        elseif ($rptOption->rptParams['preport_type'] == 3) 
        {
            $rptOption->rptName='BusinessTurnoverBySalesmanDetailed';
        }
        //Business Turnover By Segment Summary
        elseif ($rptOption->rptParams['preport_type'] == 4) 
        {
            $rptOption->rptName='BusinessTurnoverBySegment';
        }
        //Business Turnover By Segment Detailed
        elseif ($rptOption->rptParams['preport_type'] == 5) 
        {
            $rptOption->rptName='BusinessTurnoverBySegmentDetailed';
        }
        return $rptOption;
    }
}
