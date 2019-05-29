<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\purchaseRegister;
/**
 * Description of Purchase Register
 *
 * @author Shrishail
 */
class PurchaseRegister extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams["preg_type"] == "purchReg") {
            $rptOption->rptName = "PurchaseRegisterV2";
        } elseif ($rptOption->rptParams["preg_type"] == "retReg") {
            $rptOption->rptName = "PurchaseRegisterReturnV2";
        }
        
        // Select All Vat Types
        if($rptOption->rptParams["pvat_type_id"] == '' || $rptOption->rptParams["pvat_type_id"] == -1) {
            $rptOption->rptParams["pvat_type_id"] = 0;
            $rptOption->rptParams["pvat_type_desc"] = 'All VAT/GST Types';
        } else {
            $rptOption->rptParams["pvat_type_desc"] = \app\cwf\vsla\utils\LookupHelper::GetLookupText(
                    '../core/tx/lookups/VatTypePurchase.xml',
                    'vat_type_desc',
                    'vat_type_id',
                    intval($rptOption->rptParams["pvat_type_id"])
                );
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
