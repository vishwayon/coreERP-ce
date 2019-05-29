<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\stockMoveType;

/*
 * Description of Stock Movement Type
 *
 * @author Valli
 */

class StockMoveType extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if ($rptOption->rptParams['preport_type']==0) {
            $rptOption->rptName='MatWiseStockMoveTypeSummary'; 
        }
        else if ($rptOption->rptParams['preport_type']==1) {
            $rptOption->rptName='MatWiseStockMoveTypeDetail'; 
        }
        else if ($rptOption->rptParams['preport_type']==2) {
            $rptOption->rptName='StockMoveType'; 
        }
        else if ($rptOption->rptParams['preport_type']==3) {
            $rptOption->rptName='StockMoveTypeDetail'; 
        }
       
        if($rptOption->rptParams['psl_id']=='' || $rptOption->rptParams['psl_id']=='-1'  || $rptOption->rptParams['psl_id']==null){
            $rptOption->rptParams['psl_id']= 0;
        }
        
        if($rptOption->rptParams['psl_id']!=-1){ 
            $rptOption->rptParams['psl_name'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/StockLocation.xml', 'stock_location_name', 'stock_location_id', $rptOption->rptParams['psl_id']);
        } 
        
        if($rptOption->rptParams['pstock_movement_type_id']=='' || $rptOption->rptParams['pstock_movement_type_id']==null){
            $rptOption->rptParams['pstock_movement_type_id']= 0;
        }
        
        $rptOption->rptParams['pst_move_type'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/st/lookups/StockMovementTypeWithAll.xml', 'stock_movement_type', 'stock_movement_type_id', $rptOption->rptParams['pstock_movement_type_id']);
        
        $rptCaption = "Between ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])." And ".
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        // Avoid displaying a blank report
        if($rptOption->rptParams['pmaterial_type_id'] == -1) {
            $rptOption->rptParams['pmaterial_type_id'] = 0;
        }
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        return $rptOption;
    }
}
