<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\reports\tdsPayments;
/**
 * Pending Bills for TDS Payments 
 *
 * @author Valli
 */
class TDSPayments extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        $rptOption->rptParams['preport_period'] = " Upto " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]); 
        
        if ($rptOption->rptParams['pcategory'] == 1){
            $rptOption->rptName = 'TDSPaymentsPending';
        }
        return $rptOption;
    }
}
