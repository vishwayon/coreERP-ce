<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of FormController
 *
 * @author girish
 */
namespace app\core\fa\controllers;

use app\cwf\vsla\base\WebFormController;


class FormController extends WebFormController {
    //put your code here
    
//    public function getViewPath() {
//        return '../core/fa/views/mast';
//        //parent::getViewPath();
//        
//    }
    
    public function actionCalculatedep($depDateFrom, $depDateTo){
        
        // Build Asset Dep BO
        $inparam= array();
        $branchinparam['ad_id']='';
        // Create instance of Asset Dep BO
        $bopath='../core/fa/assetDep/AssetDep.xml';
        $bo = new \app\cwf\vsla\xmlbo\XboBuilder($bopath);
        $boInst = $bo->buildBO($inparam);
        
        $classinst= new \app\core\fa\assetDep\worker\AssetDepTemp($depDateFrom, $depDateTo, $boInst->asset_dep_ledger);
        
        $adWorker= new \app\core\fa\assetDep\worker\AssetDepWorker(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), 
                \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $adWorker->CalculateDepreciation($classinst); 
        $ad_tran= $adWorker->AssetDepSummary($boInst);  
        
        $result = array();
        $result['asset_dep_ledger']=$classinst->AssetDepLedger();
        $result['ad_tran']=$ad_tran;
        $result['status']='ok';
        return json_encode($result);
    }
    

    public function actionAssetItemForAs($voucher_id, $doc_date, $asset_class_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm_text = "Select a.voucher_tran_id, a.purchase_date, a.asset_item_id, a.asset_class_id, a.asset_code, a.asset_name, a.purchase_amt, coalesce(sum(b.dep_amt), 0) as dep_amt, 
                            a.asset_qty, a.asset_location_id, a.purchase_date, 0 as sale_amt, 0 as sale_amt_fc
                    From fa.asset_item a
                    left Join fa.asset_dep_ledger b on b.asset_class_id=a.asset_class_id and b.asset_item_id=a.asset_item_id
                    where a.branch_id=:pbranch_id
                            and a.company_id=:pcompany_id
                            and a.asset_class_id=:passet_class_id
                            and a.asset_item_id not in (Select a.asset_item_id from fa.as_tran a where a.as_id != :pvoucher_id)
                            and a.use_start_date <=:pto_date
                    group by a.voucher_tran_id, a.purchase_date, a.asset_item_id, a.asset_class_id, a.asset_code, a.asset_name, a.purchase_amt, a.asset_qty, a.asset_location_id, a.purchase_date
                    Order by a.purchase_date, a.voucher_tran_id";
 
        $cmm->setCommandText($cmm_text);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('passet_class_id', $asset_class_id);
        $dtAssetItemBal = \app\cwf\vsla\data\DataConnect::getData($cmm);
        

        $result = array();
        $result['ai_bal'] = $dtAssetItemBal;
        $result['status'] = 'ok';
        return json_encode($result);
    }
}
