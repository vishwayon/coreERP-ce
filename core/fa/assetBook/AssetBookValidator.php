<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetBook;


/**
 * Description of AssetBookValidator
 *
 * @author girish
 */
class AssetBookValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetBookEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    protected function validateBusinessRules() {
        
        // Validate duplicate asset book
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select asset_book_desc from fa.asset_book where asset_book_desc ilike :passet_book_desc and asset_book_id!=:passet_book_id');
        $cmm->addParam('passet_book_desc', $this->bo->asset_book_desc);
        $cmm->addParam('passet_book_id', $this->bo->asset_book_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Asset Book already exists. Duplicate asset books not allowed.');
        }
        
        // Validate Accounting Asset Book Status
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from fa.asset_book where is_accounting_asset_book = true');
        $resultAccountingAssetBookStatus = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($resultAccountingAssetBookStatus->Rows())>0) {
            if($resultAccountingAssetBookStatus->Rows()[0]['asset_book_id'] == $this->bo->asset_book_id and $resultAccountingAssetBookStatus->Rows()[0]['is_accounting_asset_book'] <> $this->bo->is_accounting_asset_book){
              $this->bo->addBRule('Cannot change Accounting Asset Book.');  
            }
        }
            
        // Validate Accounting Asset Book
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from fa.ad_control');
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            if($this->bo->is_accounting_asset_book == true ){
            $this->bo->addBRule('Cannot change Accounting Asset Book as it is part of Asset Depreciation.');
            }
        }
    }
        
    public function validateBeforeDelete() {
        // conduct default form validations
        $this->validateBeforeDelete();
        if($this->bo->is_accounting_asset_book == true ) {
            $this->bo->addBRule('Cannot delete Accounting Asset Book.');
        }
    }
}
