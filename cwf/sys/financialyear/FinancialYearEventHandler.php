<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\financialyear;

/**
 * Description of FinancialYearEventHandler
 *
 * @author Ravindra
 */
class FinancialYearEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->isnew=false;
        if($this->bo->finyear_id==-1)
        {
            // Fetch Max year ends from fin year table and set it as year begins
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select date(max(year_end) + INTERVAL '1 Day') as year_end from sys.finyear");
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            
            if(count($result->Rows())>0) {                          
                $this->bo->year_begin=$result->Rows()[0]['year_end'];
                $year_end = strtotime('- 1 day',  strtotime('+ 1 year', strtotime($this->bo->year_begin)));
        
                $this->bo->year_end = date("Y-m-d", $year_end);
            }            
            $this->bo->isnew=true;
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
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
               $cmm->addParam('pfinyear', $this->bo->finyear_code);
               $cmm->addParam('pcompany_id', $this->bo->company_id);
               $cmm->addParam('pbranch_id', 0);
               \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
        }
    }
}
