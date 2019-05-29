<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep\worker;

class AssetDepWorker{
    /** @var AssetDepBase[] **/
    public $depCalculators=null;
    public $dtAssetClassBook=null;
    private $dtAssetDepLedger=null;
    public $company_id=-1;
    public $branch_id=-1;
    public $finyear = '';    
    
    function __construct($company_id, $branch_id, $finyear) {
        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->finyear = $finyear;
    }
    
    public function CalculateDepreciation(IAssetDepWorker $dep_doc, $asset_item_id=0, $as_id = ''){
                
        // Initialise 
        $this->InitialiseWorker();
        
        // Fetch the asset items that have value for each book
        
        $assetbooks= array();
        foreach($this->dtAssetClassBook->Rows() as $row){
            if(!in_array($row['asset_book_id'], $assetbooks)){
                array_push($assetbooks, $row['asset_book_id']);
            }
        }        
        
        $drsAssetClass = array();
        
        foreach($assetbooks as $asset_book_id){
            // For each unique book this would be executed
            foreach($this->dtAssetClassBook->Rows() as $row){
                if($row['asset_book_id'] == $asset_book_id){
                    array_push($drsAssetClass, $row);
                }
            }            
            
            $dtAssetItem=$this->FetchAssetItems($asset_book_id, $asset_item_id, $dep_doc, $as_id);
            
            foreach($this->depCalculators as $dep_item){
                $dep_item->Initialise($asset_book_id, $dep_doc->DepDateFrom(), $dep_doc->DepDateTo());
            }            
            
            // For each Asset Item, execute the calculations
            foreach($dtAssetItem->Rows() as $drAssetItem){
                $dep_amt=0;
                
                // Calculate dep. Only one method would return the value for one book
                foreach($this->depCalculators as $dep_item){
                    $dep_amt= $dep_amt+ $dep_item->GetDepAmt($drAssetItem);
                }
                
                // Calculate dep. + Acc Dep. may be more than the Purchase Amt. Therefore, reduce dep. by the excess
                if(($dep_amt + $drAssetItem['dep_amt']) > $drAssetItem['purchase_amt']){
                    $dep_amt = $dep_amt - (($dep_amt + $drAssetItem['dep_amt']) - $drAssetItem['purchase_amt']);
                }
                
                // Add the result into the BO                
                $newRow = $dep_doc->AssetDepLedger()->NewRow();
                $newRow['asset_book_id'] = $asset_book_id;  
                $newRow['asset_book'] = $drAssetItem['asset_book'];    
                $newRow['asset_class_id'] = $drAssetItem['asset_class_id']; 
                $newRow['asset_class'] = $drAssetItem['asset_class'];    
                $newRow['asset_item_id'] = $drAssetItem['asset_item_id'];
                $newRow['asset_name'] = $drAssetItem['asset_name'];
                $newRow['dep_amt'] = $dep_amt;     
                $newRow['branch_id'] = $this->branch_id;
                $newRow['company_id'] = $this->company_id;
                $newRow['finyear'] = $this->finyear;     
                $newRow['dep_date_from']=$dep_doc->DepDateFrom();
                $newRow['dep_date_to']=$dep_doc->DepDateTo();
                $dep_doc->AssetDepLedger()->AddRow($newRow);
            }
        }
        
        $this->dtAssetDepLedger=$dep_doc->AssetDepLedger();
    }
    
    public function AssetDepSummary(){
        $dep_amt=NULL;
        $dep_account_id=-1;
        $acc_dep_account_id=-1;
        $dtDepSummary= new \app\cwf\vsla\data\DataTable();
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int2');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $dtDepSummary->addColumn('sl_no', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $dtDepSummary->addColumn('asset_book_id', $phpType, $default, 100, $scale, $isUnique);
        $dtDepSummary->addColumn('asset_class_id', $phpType, $default, 100, $scale, $isUnique);
        $dtDepSummary->addColumn('dep_account_id', $phpType, $default, 100, $scale, $isUnique);
        $dtDepSummary->addColumn('acc_dep_account_id', $phpType, $default, 100, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $dtDepSummary->addColumn('dep_amt', $phpType, $default, 0, $scale, $isUnique);               
        
        $assetClasses = array();
        
        // Store unique Asset Class
        foreach($this->dtAssetDepLedger->Rows() as $row){
            if(!in_array($row['asset_class_id'], $assetClasses)){
                array_push($assetClasses, $row['asset_class_id']);
            }
        }
        
        // Store unique Asset Book
        $assetBooks= array();
        foreach($this->dtAssetDepLedger->Rows() as $row){
            if(!in_array($row['asset_book_id'], $assetBooks)){
                array_push($assetBooks, $row['asset_book_id']);
            }
        }
        
        // Compute Summary of Dep amount for each Asset Class in each Asset Book
        foreach($assetClasses as $asset_class_id){
            foreach($assetBooks as $asset_book_id){
                $dep_amt=NULL;
                $asset_book='';
                $asset_class='';
                foreach($this->dtAssetDepLedger->Rows() as $row){
                    if($row['asset_class_id'] == $asset_class_id && $row['asset_book_id'] == $asset_book_id){
                        $dep_amt = $dep_amt + $row['dep_amt'];
                        $asset_book=$row['asset_book'];                        
                        $asset_class=$row['asset_class'];
                    }
                }
                if($dep_amt != NULL){
                    // Fetch Dep account and accumulated dep acc for the class
                    $drDepAcc = array();
                    foreach($this->dtAssetClassBook->Rows() as $drAssetClassBook){
                        if($drAssetClassBook['asset_class_id'] == $asset_class_id){
                            array_push($drDepAcc, $drAssetClassBook);
                        }
                    }

                    $dep_account_id= $drDepAcc[0]['dep_account_id'];
                    $acc_dep_account_id = $drDepAcc[0]['acc_dep_account_id'];

                    $newRow = $dtDepSummary->NewRow();
                    $newRow['asset_book_id'] = $asset_book_id; 
                    $newRow['asset_book'] = $asset_book;
                    $newRow['asset_class_id'] = $asset_class_id;
                    $newRow['asset_class'] = $asset_class;
                    $newRow['dep_account_id'] = $dep_account_id;   
                    $newRow['dep_account'] = $drDepAcc[0]['dep_account'];    
                    $newRow['acc_dep_account_id'] = $acc_dep_account_id;
                    $newRow['acc_dep_account'] = $drDepAcc[0]['acc_dep_account'];   
                    $newRow['dep_amt'] =round( $dep_amt, \app\cwf\vsla\Math::$amtScale);
                    $dtDepSummary->AddRow($newRow);
                }
            }
        }
        
        return $dtDepSummary;
    }
    
    private function InitialiseWorker(){
        // Initialise Decorators
        $this->depCalculators= array();
        array_push($this->depCalculators, new AssetDepSLM($this));
        array_push($this->depCalculators, new AssetDepWDV($this));
        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from fa.sp_dep_asset_class_book()');
        $this->dtAssetClassBook= \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function FetchAssetItems($asset_book_id, $asset_item_id, IAssetDepWorker $dep_doc, $as_id){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from fa.sp_dep_asset_items(:pbranch_id, :passet_book_id, :passet_class_id, :passet_item_id, :pfrom_date, :pto_date, :pas_id)');
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('passet_book_id', $asset_book_id);
        $cmm->addParam('passet_class_id', 0);
        $cmm->addParam('passet_item_id', $asset_item_id);
        $cmm->addParam('pfrom_date', $dep_doc->DepDateFrom());
        $cmm->addParam('pto_date', $dep_doc->DepDateTo());
        $cmm->addParam('pas_id', $as_id);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
            
}