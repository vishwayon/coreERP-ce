<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetBook;

/**
 * Description of AssetBookEventHandler
 *
 * @author Priyanka
 */
class AssetBookEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);   
    }
    
    public function afterSave($cn) {
        parent::afterSave($cn);
        
        //Only one asset book can be mark as Accounting Asset Book.
        if($this->bo->is_accounting_asset_book == true ){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('update fa.asset_book set is_accounting_asset_book = false where is_accounting_asset_book= true and asset_book_id!=:passet_book_id');
        $cmm->addParam('passet_book_id', $this->bo->asset_book_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }      
}
