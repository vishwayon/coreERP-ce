<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\branch;

/**
 * Description of BranchEventHandler
 *
 * @author Shrishail
 */
class BranchEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->has_access_rights= true;
        $this->bo->number_format= "";
        $this->bo->has_work_flow= true;
        $this->bo->company_group_id= -1;
        $this->bo->company_name='';
        $this->bo->isnew=false;
        // Fetch company name for selected company        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.company where company_id=:pcompany_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($dtbr->Rows())>0){
            $this->bo->company_name=$dtbr->Rows()[0]['company_name'];
            $this->bo->company_code=$dtbr->Rows()[0]['company_code'];
        }
        
        // Fetch Branch defaults from HO Branch               
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_is_ho = true and company_id=:pcompany_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            $this->bo->currency=$dt->Rows()[0]['currency'];
            $this->bo->sub_currency=$dt->Rows()[0]['sub_currency'];
            $this->bo->currency_displayed=$dt->Rows()[0]['currency_displayed'];
            $this->bo->currency_system=$dt->Rows()[0]['currency_system'];
            $this->bo->date_format=$dt->Rows()[0]['date_format'];
        }
        
        
        if($this->bo->branch_id==-1){ 
            $this->bo->branch_is_ho= false;
            $this->bo->isnew= true;   
            $this->bo->date_format="dd/mm/yyyy";
        }
        
        if(count($this->bo->branch_tax_info->Rows())==0){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select tax_info_type_id, sl_no from sys.tax_info_type');
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if(count($result->Rows())>0){   
                foreach ($result->Rows() as $row) {
                    $newRow = $this->bo->branch_tax_info->NewRow();
                    $newRow['sl_no'] =  $row['sl_no'];
                    $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                    $newRow['tax_info_type_id'] = $row['tax_info_type_id'];  
                    $newRow['branch_tax_info_desc'] = '';  
                    $this->bo->branch_tax_info->AddRow($newRow);
                }
            }
        }        
    }
    
    public function afterSave($cn) {
        parent::afterSave($cn);
        if($this->bo->isnew){
             // If the accounting module is not implemented, inserting account balance will through erro. 
            // So verify accounting module exists.
            $cmm = new \app\cwf\vsla\data\SqlCommand();        
            $cmm->setCommandText("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'ac'");
            $dt=\app\cwf\vsla\data\DataConnect::getData($cmm);
            
            if(count($dt->Rows())>0){
                // Insert records in account balance for new financial year
                $cmm = new \app\cwf\vsla\data\SqlCommand();        
                $cmm->setCommandText("select * from ac.sp_account_balance_add(:pfinyear, :pcompany_id, :pbranch_id)");
                $cmm->addParam('pfinyear', '');
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', $this->bo->branch_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        if($this->bo->branch_id!=-1){ 
            $this->bo->isnew = false;   
        }
    }
}
