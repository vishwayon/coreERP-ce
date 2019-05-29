<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetLocation;


/**
 * Description of AssetLocationValidator
 *
 * @author priyanka
 */
class AssetLocationValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetLocationEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {
        // Validate duplicate asset location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select asset_location from fa.asset_location where asset_location ilike :passet_location and branch_id=:pbranch_id and asset_location_id!=:passet_location_id');
        $cmm->addParam('passet_location', $this->bo->asset_location);
        $cmm->addParam('passet_location_id', $this->bo->asset_location_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Asset location already exists. Duplicate asset locations not allowed.');
        }
        
        // Validate duplicate location code
        if($this->bo->asset_location_code != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select asset_location_code from fa.asset_location where asset_location_code ilike :passet_location_code and branch_id=:pbranch_id and asset_location_id!=:passet_location_id');
            $cmm->addParam('passet_location_code', $this->bo->asset_location_code);
            $cmm->addParam('passet_location_id', $this->bo->asset_location_id);
            $cmm->addParam('pbranch_id', $this->bo->branch_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0) {
                $this->bo->addBRule('Location Code already exists. Duplicate location codes not allowed.');
            }
        }
    }
}
