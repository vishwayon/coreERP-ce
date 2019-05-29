<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetSale;

/**
 * Description of AssetSaleEventHandler
 *
 * @author Priyanka
 */
class AssetSaleEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->as_id=="" or $this->bo->as_id=="-1")
        {
            $this->bo->asset_class_id=$criteriaparam['formData']['SelectAssetClass']['asset_class_id'];
            $this->bo->doc_date=$criteriaparam['formData']['SelectAssetClass']['doc_date'];
            $this->bo->as_id="";
            $this->bo->fc_type_id=0;
            $this->bo->exch_rate=1; 
            //$this->bo->cheque_number="0"; 
            $this->bo->status=0;
            $this->bo->en_sales_type=0;
            
            // Fill as Tran
            $count=0;
            foreach($criteriaparam['formData']['SelectAssetItem'] as $pltran){
                $count=$count+1;
                $newRow=$this->bo->as_tran->newRow();                
                $newRow['asset_item_id']= $pltran['asset_item_id'];
                $newRow['asset_code']= $pltran['asset_code'];
                $newRow['asset_name']= $pltran['asset_name'];
                $newRow['purchase_amt']= $pltran['purchase_amt'];
                $newRow['dep_amt']= $pltran['dep_amt'];
                $newRow['credit_amt']= $pltran['sale_amt'];   
                $newRow['credit_amt_fc']= $pltran['sale_amt_fc']; 
                $this->bo->as_tran->AddRow($newRow);                
            }

            foreach($this->bo->as_tran->Rows() as $row){                
                $lastDepDate=$this->setLastDepDate($row['asset_item_id']);
                $classinst= new \app\core\fa\assetDep\worker\AssetDepTemp($lastDepDate, $this->bo->doc_date, $this->bo->asset_dep_ledger);

                $adWorker= new \app\core\fa\assetDep\worker\AssetDepWorker(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), 
                        \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), 
                        \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
                $adWorker->CalculateDepreciation($classinst, $row['asset_item_id']);
            }
            foreach($this->bo->asset_dep_ledger->Rows() as &$refadrow){
                // Add row in asset book tran
                $newASBookRow=$this->bo->as_book_tran->newRow();
                $newASBookRow['asset_book_id']= $refadrow['asset_book_id'];
                $newASBookRow['asset_class_id']= $refadrow['asset_class_id'];
                $newASBookRow['asset_item_id']= $refadrow['asset_item_id'];
                $newASBookRow['asset_name']= $refadrow['asset_name'];
                $newASBookRow['asset_class']= $refadrow['asset_class'];
                $newASBookRow['asset_book']= $refadrow['asset_book'];   
                $newASBookRow['dep_amt']= $refadrow['dep_amt'];   
                $newASBookRow['dep_date_from']= $lastDepDate; 
                $this->bo->as_book_tran->AddRow($newASBookRow); 
                $refadrow['doc_date']=$this->bo->doc_date;
            }

            //calculate Profit/Loss on sale
            foreach($this->bo->as_book_tran->Rows() as &$refbook_row){
                $refbook_row['acc_dep_amt']=$this->getAccDepAmt($refbook_row['asset_item_id']);
                $refbook_row['profit_loss_amt']=$this->getSaleAmt($refbook_row['asset_item_id']) - ($this->getPurchaseAmt($refbook_row['asset_item_id']) - ($refbook_row['acc_dep_amt'] + $refbook_row['dep_amt']));
            }
        } 
        else{
            if($this->bo->cheque_number == 0){
                $this->bo->cheque_date = date("Y-m-d", time());
            }
            // Fetch Dep Amt
            $this->fetchDepAmt();
            
            foreach($this->bo->as_book_tran->Rows() as &$refbook_row){
                $dt=$this->fetchAssetNameWithClass($refbook_row['asset_item_id']);
                foreach($dt->Rows() as $dr){
                    if(($dr['asset_item_id'] == $refbook_row['asset_item_id']) && ($dr['asset_book_id'] == $refbook_row['asset_book_id']) ){
                        $refbook_row['asset_name']=$dr['asset_name'];
                        $refbook_row['asset_class']=$dr['asset_class'];
                        $refbook_row['asset_class_id']=$dr['asset_class_id'];
                        $refbook_row['asset_book']=$dr['asset_book_desc'];
                        break;
                    }
                }                    
            }
            foreach($this->bo->as_tran->Rows() as &$reftranrow){  
                $dt=$this->fetchAssetName($reftranrow['asset_item_id']);
                foreach($dt->Rows() as $dr){ 
                    if($dr['asset_item_id'] == $dr['asset_item_id']){
                        $reftranrow['asset_name']=$dr['asset_name'];
                        $reftranrow['asset_code']=$dr['asset_code'];
                    }
                }
            }    
        }
         $this->bo->asset_class =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/fa/lookups/AssetClass.xml', 'asset_class', 'asset_class_id', $this->bo->asset_class_id);
           
    }
    
    private function fetchDepAmt(){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("  select a.as_tran_id, a.as_id, coalesce(sum(d.dep_amt), 0) as dep_amt, a.asset_item_id, c.asset_code, c.asset_name 
                                from fa.as_tran a
                                inner join fa.as_control b on a.as_id=b.as_id
                                Inner join fa.asset_item c on a.asset_item_id=c.asset_item_id
                                inner join fa.asset_dep_ledger d on a.asset_item_id=d.asset_item_id and b.asset_class_id=d.asset_class_id
                                where b.as_id=:pas_id
                                group by a.as_tran_id, a.as_id, a.asset_item_id, c.asset_code, c.asset_name 
                            ");
        $cmm->addParam('pas_id', $this->bo->as_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach($this->bo->as_tran->Rows() as &$refas_tran_row){  
            foreach($result->Rows() as $row){
                if($refas_tran_row['as_tran_id'] == $row['as_tran_id']){
                    $refas_tran_row['dep_amt']=$row['dep_amt'];
                }
            }
        }
    }
    
    private function fetchAssetNameWithClass($asset_item_id){       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("  select a.asset_item_id, a.asset_name, b.asset_class_id, b.asset_class, d.asset_book_id, d.asset_book_desc
                                From fa.asset_item a 
                                inner Join fa.asset_class b on a.asset_class_id=b.asset_class_id
                                Inner Join fa.asset_class_book c on b.asset_class_id=c.asset_class_id
                                Inner Join fa.asset_book d on c.asset_book_id=d.asset_book_id
                                where a.asset_item_id=:passet_item_id
                                group by  a.asset_item_id, a.asset_name, b.asset_class_id, b.asset_class, d.asset_book_id, d.asset_book_desc
                            ");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return  $result;
    }
    
    private function fetchAssetName($asset_item_id){       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("  select a.asset_item_id, a.asset_name, a.asset_code
                                From fa.asset_item a 
                                where a.asset_item_id=:passet_item_id
                            ");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return  $result;
    }
    
    private function setLastDepDate($asset_item_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT max(dep_date_to) + integer '1' as max_date  FROM fa.asset_dep_ledger where asset_item_id=:passet_item_id and voucher_id<>:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->as_id);
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if ($result->Rows()[0]['max_date'] == ''){
                return $this->getPurchaseDate($asset_item_id);
            }
            else{
               return  $result->Rows()[0]['max_date'];
            }
        }
    }
    
    private function getSaleAmt($asset_item_id){
        $count=0;
        $sale_amt=0;
        foreach($this->bo->as_tran->Rows() as $row){     
            if($row['asset_item_id']==$asset_item_id){
                $sale_amt=$row['credit_amt'];
                $count=$count+1;
            }
        }
        
        if($count==1){
            return $sale_amt;
        }
        return 0;
    }
    
    private function getPurchaseAmt($asset_item_id){
        $count=0;
        $purchase_amt=0;
        foreach($this->bo->as_tran->Rows() as $row){     
            if($row['asset_item_id']==$asset_item_id){
                $purchase_amt=$row['purchase_amt'];
                $count=$count+1;
            }
        }
        
        if($count==1){
            return $purchase_amt;
        }
        return 0;
    }
    
    private function getPurchaseDate($asset_item_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT purchase_date  FROM fa.asset_item where asset_item_id=:passet_item_id");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if ($result->Rows()[0]['purchase_date'] == NULL){
                throw new \Exception('Purchase Date not resolved for Asset Item: ' . (string)$asset_item_id);
            }
            else{
               return $result->Rows()[0]['purchase_date'];
            }
        }
    }
    
    private function getAccDepAmt($asset_item_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT coalesce(sum(dep_amt), 0) as acc_dep_amt FROM fa.asset_dep_ledger where asset_item_id=:passet_item_id and voucher_id<>:pvoucher_id");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $cmm->addParam('pvoucher_id', $this->bo->as_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            return $result->Rows()[0]['acc_dep_amt'];
        }
    }
}
