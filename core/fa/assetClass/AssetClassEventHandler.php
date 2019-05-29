<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetClass;

/**
 * Description of AssetClassEventHandler
 *
 * @author Priyanka
 */
class AssetClassEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);  
                              
        if(count($this->bo->asset_class_book->Rows())==0){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select asset_book_id, asset_book_desc from fa.asset_book where is_accounting_asset_book=true');
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if(count($result->Rows())>0){
                $newRow = $this->bo->asset_class_book->NewRow();
                $newRow['asset_book_id'] = $result->Rows()[0]['asset_book_id'];  
                $newRow['asset_book_desc'] = $result->Rows()[0]['asset_book_desc'];  
                
                $this->bo->asset_class_book->AddRow($newRow);
            }
        }
        else{
            foreach($this->bo->asset_class_book->Rows() as &$refrow){
                $refrow['asset_book_desc'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/fa/lookups/AssetBook.xml', 'asset_book_desc', 'asset_book_id', $refrow['asset_book_id']);
            }
        }
    }
}