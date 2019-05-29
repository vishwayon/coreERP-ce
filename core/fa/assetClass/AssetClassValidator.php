<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetClass;
use YaLinqo\Enumerable;

/**
 * Description of AssetClassValidator
 *
 * @author girish
 */
class AssetClassValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetClassEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate asset location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select asset_class from fa.asset_class where asset_class ilike :passet_class and asset_class_id!=:passet_class_id');
        $cmm->addParam('passet_class', $this->bo->asset_class);
        $cmm->addParam('passet_class_id', $this->bo->asset_class_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Asset class already exists. Duplicate asset class not allowed.');
        }
        
        // Validate duplicate location code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select asset_class_code from fa.asset_class where asset_class_code ilike :passet_class_code and asset_class_id!=:passet_class_id');
        $cmm->addParam('passet_class_code', $this->bo->asset_class_code);
        $cmm->addParam('passet_class_id', $this->bo->asset_class_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Class Code already exists. Duplicate class code not allowed.');
        }
        
        if(count($this->bo->asset_class_book->Rows())==0){
            $this->bo->addBRule('Add atleast one Depreciation By Book.');
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select asset_book_id, asset_book_desc from fa.asset_book where is_accounting_asset_book=true');
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $accounting_asset_book=-1;
        if(count($result->Rows())>0){
            $accounting_asset_book=(string)$result->Rows()[0]['asset_book_id'];
            $myarray=  Enumerable::from($this->bo->asset_class_book->Rows());
            $result = $myarray->select('$a==>(string)$a["asset_book_id"]')->contains($accounting_asset_book);
            if($result==false){
                $this->bo->addBRule('Select atleast one Accounting Asset book in Depreciation By Book Info.');
            }       
        }
    }
}
