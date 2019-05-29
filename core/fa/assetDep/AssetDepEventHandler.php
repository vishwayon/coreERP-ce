<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep;

/**
 * Description of AssetDepEventHandler
 *
 * @author Priyanka
 */
class AssetDepEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        $this->bo->asset_dep_ledger->getColumn("company_id")->default= \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
        $this->bo->setTranColDefault('asset_dep_ledger', 'company_id',  \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        
        $this->bo->asset_dep_ledger->getColumn("branch_id")->default= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $this->bo->setTranColDefault('asset_dep_ledger', 'branch_id',  \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        
        $this->bo->asset_dep_ledger->getColumn("finyear")->default= \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');   
        $this->bo->setTranColDefault('asset_dep_ledger', 'finyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        
        if($this->bo->ad_id=="" or $this->bo->ad_id=="-1")
        {
            $this->bo->ad_id="";
            $this->bo->status=0;
            
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            if(strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->doc_date= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
            
            if(strtotime($this->bo->dep_date_to) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->dep_date_to= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT max(dep_date_to) + integer '1' as max_date  FROM fa.ad_control where company_id=:pcompany_id And branch_id=:pbranch_id and doc_date between :pfrom_date and :pto_date");
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));            
            $cmm->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0){
                if ($result->Rows()[0]['max_date'] == ''){
                    $this->bo->dep_date_from=\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
                }
                else{
                    $this->bo->dep_date_from= $result->Rows()[0]['max_date'];
                }
            }
       
        }
        else{
            // Fetch Text for id fields
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.ad_id, a.ad_tran_id, b.asset_class, c.asset_book_desc, d.account_head as dep_account, e.account_head as acc_dep_account
                                    from fa.ad_tran a
                                    inner join fa.asset_class b on a.asset_class_id=b.asset_class_id
                                    inner Join fa.asset_book c on a.asset_book_id=c.asset_book_id
                                    inner join ac.account_head d on a.dep_account_id=d.account_id
                                    inner join ac.account_head e on a.acc_dep_account_id=e.account_id
                                    where a.ad_id=:pad_id');
            $cmm->addParam('pad_id', $this->bo->ad_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0){
                foreach($this->bo->ad_tran->Rows() as &$refad_tran_row){
                    foreach($result->Rows() as $row){
                        if($row['ad_tran_id'] == $refad_tran_row['ad_tran_id']){
                            $refad_tran_row['asset_class']=$row['asset_class'];
                            $refad_tran_row['asset_book']=$row['asset_book_desc'];
                            $refad_tran_row['dep_account']=$row['dep_account'];
                            $refad_tran_row['acc_dep_account']=$row['acc_dep_account'];
                            break;
                        }
                    }
                }                    
            }
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('  select a.voucher_id, a.asset_dep_ledger_id, b.asset_class, c.asset_book_desc, d.asset_name as asset_name
                                    from fa.asset_dep_ledger a
                                    inner join fa.asset_class b on a.asset_class_id=b.asset_class_id
                                    inner Join fa.asset_book c on a.asset_book_id=c.asset_book_id
                                    inner join fa.asset_item d on a.asset_item_id=d.asset_item_id
                                    where a.voucher_id=:pvoucher_id');
            $cmm->addParam('pvoucher_id', $this->bo->ad_id);
            $resultledger = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0){
                foreach($this->bo->asset_dep_ledger->Rows() as &$refad_row){
                    foreach($resultledger->Rows() as $row){
                        if($row['asset_dep_ledger_id'] == $refad_row['asset_dep_ledger_id']){
                            $refad_row['asset_class']=$row['asset_class'];
                            $refad_row['asset_book']=$row['asset_book_desc'];
                            $refad_row['asset_name']=$row['asset_name'];
                            break;
                        }
                    }
                }                    
            }
        }            
    }
}
