<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\reports\assetDepRegister;

/**
 * Description of AssetDepRegisterConsolidatedValidator
 *
 * @author shrishail
 */
class AssetDepRegisterConsolidated extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
         //validate branch id
        if($rptOption->rptParams['pbranch_id']=='' or $rptOption->rptParams['pbranch_id']==-1){
            array_push($rptOption->brokenRules, 'Please select branch.');
        }
        
//        //Validate asset book
//        if($rptOption->rptParams['passet_book_id']=='' or $rptOption->rptParams['passet_book_id']==-1){
//                array_push($rptOption->brokenRules, 'Please select asset book.');
//            } 
        $rptOption->rptParams['passet_book_id']=0;
        
        //Validate asset class 
        if($rptOption->rptParams['passet_class_id']=='' or $rptOption->rptParams['passet_class_id']==-1){
                array_push($rptOption->brokenRules, 'Please select asset class.');
            } 
        
        //validate type
        if($rptOption->rptParams['pis_summarized']=='' or $rptOption->rptParams['pis_summarized']==-1){
            array_push($rptOption->brokenRules, 'Please select type.');
        }
        return $rptOption;
    }
}

