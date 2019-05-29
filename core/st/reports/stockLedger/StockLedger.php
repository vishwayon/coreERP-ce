<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\stockLedger;
/**
 * Description of Stock Ledger
 *
 * @author Priyanka
 */
class StockLedger extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }   
        if($rptOption->rptParams['pmaterial_id']=='' || $rptOption->rptParams['pmaterial_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select Material to view.');
        }   
        
        if($rptOption->rptParams['pmaterial_id']!=-1){ 
            $rptOption->rptParams['pmaterial_name'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/Material.xml', 'material_name', 'material_id', $rptOption->rptParams['pmaterial_id']);
        
        }   
        
        if($rptOption->rptParams['pstock_location_id']=='' || $rptOption->rptParams['pstock_location_id']=='-1'  || $rptOption->rptParams['pstock_location_id']==null){
            $rptOption->rptParams['pstock_location_id']= 0;
        }
        
        if($rptOption->rptParams['pstock_location_id']!=-1){ 
            $rptOption->rptParams['pstock_location_name'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/StockLocation.xml', 'stock_location_name', 'stock_location_id', $rptOption->rptParams['pstock_location_id']);
        
        } 
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        if ($rptOption->rptParams['pts_info']) {
            $rptOption->rptName = 'StockLedgerWithTs';
        } else if ($rptOption->rptParams['pby_sl_grp']) {
            $rptOption->rptName = 'StockLedgerSlGrp';
        } else {
            $rptOption->rptName = 'StockLedger';
        }
        return $rptOption;
    }
}
