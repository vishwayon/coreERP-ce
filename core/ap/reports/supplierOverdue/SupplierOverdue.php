<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\reports\supplierOverdue;
/**
 * Description of SupplierOverdue
 *
 * @author Priyanka
 */
class SupplierOverdue extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['psupplier_id']=='' OR $rptOption->rptParams['psupplier_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select Supplier.');
        }
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        
        //*** select rpt name to be opened as per selected report ***
                //Supplier Overdue
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='SupplierOverdue';  
        }
        //Supplier Overdue Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='SupplierOverdueDetailed';
        }
        return $rptOption;
    }
}
