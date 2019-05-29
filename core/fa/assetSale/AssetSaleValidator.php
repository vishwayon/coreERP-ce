<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetSale;
use YaLinqo\Enumerable;

/**
 * @author Priyanka
 */
class AssetSaleValidator extends  \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetSaleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    public function validateBusinessRules() {
        
        $this->bo->debit_amt=  round(Enumerable::from($this->bo->as_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
        
        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
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
        
        $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->debit_amt);
        $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);    
        
        $this->bo->net_debit_amt=$this->bo->debit_amt;
        $this->bo->gross_debit_amt=$this->bo->debit_amt;
        
//        foreach($this->bo->as_tran->Rows() as $row){
//            if($row['credit_amt'] == 0){                
//                $this->bo->addBRule('Credit Amount cannot be zero.');
//            }
//        }
        
//        if($this->bo->cheque_number == 0){
//           $this->bo->cheque_date = NULL;     
//        }
        
        // If Depreciation calculated for particular period is not authorised then don't allow to make Asset Sale for this period and branch
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select count(*) as count from fa.ad_control where branch_id=:pbranch_id and :pdate <=(select max(dep_date_to) as max_dep_date from fa.ad_control where branch_id=:pbranch_id)');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pdate', $this->bo->doc_date);
        $result= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if($result->Rows()[0]['count']>0){
                $this->bo->addBRule('Cannot make sale because depreciation for this period is already calculated.');
            }
        }        
        
        if(count($this->bo->as_tran->Rows())==0){
            $this->bo->addBRule('Sale Info should have atleast one record.');
        }  
        
        
        // check account type for selected account.
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->customer_id);
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            $acc_type_id=$dt->Rows()[0]['account_type_id'];
            
            if($this->bo->en_sales_type==0){
                if($acc_type_id !=2){
                    $this->bo->addBRule('Please select Cash account.'); 
                }
            }
            else if($this->bo->en_sales_type==1){
                if($acc_type_id !=1 ){
                    $this->bo->addBRule('Please select Bank account.'); 
                }
            }
            else if($this->bo->en_sales_type==2){
                if($acc_type_id !=7){
                    $this->bo->addBRule('Please select Credit account.'); 
                }
            }
            else if($this->bo->en_sales_type==3){
                if($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 23
                         || $acc_type_id == 24 || $acc_type_id == 21 || $acc_type_id == 22 || $acc_type_id == 18 || $acc_type_id == 38){
                    $this->bo->addBRule('Please select Journal account.'); 
                }
            }
        }
    }
    
    public function validateBeforeUnpost() {
        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select count(*) as count from fa.ad_control where branch_id=:pbranch_id and :pdate <=(select max(dep_date_to) as max_dep_date from fa.ad_control where branch_id=:pbranch_id)');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pdate', $this->bo->doc_date);
        $result= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if($result->Rows()[0]['count']>0){
                $this->bo->addBRule('Cannot unpost sale because depreciation for this period is already calculated.');
            }
        }        
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}
