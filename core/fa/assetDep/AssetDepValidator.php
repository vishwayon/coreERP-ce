<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep;

/**
 * Description of AssetDepValidator
 *
 * @author priyanka
 */
class AssetDepValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetDepEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        for ($rowIndex=0;$rowIndex< count ($this->bo->ad_tran->Rows());$rowIndex++) {
            $this->bo->ad_tran->Rows()[$rowIndex]['sl_no']=$rowIndex+1;
        } 
        
        foreach($this->bo->asset_dep_ledger->Rows() as &$refrow){            
            $refrow['doc_date']=$this->bo->doc_date;
            $refrow['dep_date_from']=$this->bo->dep_date_from;
            $refrow['dep_date_to']=$this->bo->dep_date_to;
        }
        
        $currency='';
        $subCurrency='';
        $currency_system='';
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows())>0){
            $currency=$dtbr->Rows()[0]['currency'];
            $subCurrency=$dtbr->Rows()[0]['sub_currency'];
            $currency_system=$dtbr->Rows()[0]['currency_system'];
        }
        
        $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->total_dep_amt);
        $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        
        if($this->bo->ad_id=='' or $this->bo->ad_id=='-1'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT max(dep_date_to) + integer '1' as max_date  FROM fa.ad_control where company_id=:pcompany_id And branch_id=:pbranch_id");
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $cmm->addParam('pbranch_id', $this->bo->branch_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0){
                if ($result->Rows()[0]['max_date'] != ''){
                    if(strtotime($this->bo->dep_date_from) != strtotime($result->Rows()[0]['max_date'])){                   
                        $this->bo->addBRule('Please calculate previous year\'s depreciation till year end.' );
                    }
                }
            }
        }
        
        if(strtotime($this->bo->dep_date_to) < strtotime($this->bo->dep_date_from)){
            $this->bo->addBRule('Dep Date To should be greater than Dep Date From');
        }
        
        if(count($this->bo->asset_dep_ledger->Rows()) == 0){
            $this->bo->addBRule('Please calculate Depreciation before save.');
        }
        
        //If any depreciation calculated is not posted then don't allow to calculate depreciation for perticular branch
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT count(*) as rec_count FROM fa.ad_control where company_id=:pcompany_id And branch_id=:pbranch_id And status<>5 and ad_id <> :pad_id");
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $cmm->addParam('pad_id', $this->bo->ad_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if((int)$result->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Cannot create Asset Depreciation because there is/are Depreciation document which is not posted.');
            }
        }
        
        // If any Asset Sale document is not posted then  don't allow to calculate depreciation for perticular branch
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT count(*) as rec_count FROM fa.as_control where company_id=:pcompany_id And branch_id=:pbranch_id And status<>5");
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $resultas = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($resultas->Rows())>0){
            if($resultas->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Cannot create Asset Depreciation because there is/are Asset Sale document which is/are not posted.');
            }
        }
    }
     
    public function validateBeforeUnpost() {
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as rec_count From fa.asset_dep_ledger Where dep_date_from > :pdep_date_from and branch_id=:pbranch_id");
        $cmm->addParam('pdep_date_from', $this->bo->dep_date_from);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $resultad = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($resultad->Rows())>0){
            if($resultad->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Depreciation for subsequent period already charged. Unpost failed.');
            }
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as rec_count from fa.asset_dep_ledger where asset_item_id in (Select asset_item_id from fa.as_book_tran) 
 										and dep_date_from = :pdep_date_from and dep_date_to=:pdep_date_to
 										and voucher_id=:pvoucher_id");
        $cmm->addParam('pdep_date_from', $this->bo->dep_date_from);
        $cmm->addParam('pdep_date_to', $this->bo->dep_date_to);
        $cmm->addParam('pvoucher_id', $this->bo->ad_id);
        $resultaditem = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($resultaditem->Rows())>0){
            if($resultaditem->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Asset Item(s) already sold. Unpost failed.');
            }
        }
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}
