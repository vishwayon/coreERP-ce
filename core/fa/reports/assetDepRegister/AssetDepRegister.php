<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\reports\assetDepRegister;

/**
 * Description of AssetDepRegisterValidator
 *
 * @author shrishail
 */
class AssetDepRegister extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        //Validate asset book
//        if($rptOption->rptParams['passet_book_id']=='' or $rptOption->rptParams['passet_book_id']==-1){
//                array_push($rptOption->brokenRules, 'Please select asset book.');
//            } 
        $rptOption->rptParams['passet_book_id']=0;
        
        //Validate asset class 
        if($rptOption->rptParams['passet_class_id']=='' or $rptOption->rptParams['passet_class_id']==-1){
                array_push($rptOption->brokenRules, 'Please select asset class.');
            } 
        
        if($this->allowConsolidated && ($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1')){
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        } 
        
        //validate type
        if($rptOption->rptParams['pis_summarized']=='' or $rptOption->rptParams['pis_summarized']==-1){
            array_push($rptOption->brokenRules, 'Please select type.');
        }
        return $rptOption;
    }
}

