<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\iBAccount;
use app\cwf\vsla\data\DataConnect;

/**
 * Description of IBAccountEventHandler
 *
 * @author Priyanka
 */
class IBAccountEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
        
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);   
    }    
        
    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
        
        if($tablename=='sys.branch'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select a.branch_id, a.branch_name, COALESCE(b.account_id, -1) as account_id, a.last_updated '
                                    . ' from sys.branch a left join ac.ib_account b on a.branch_id = b.branch_id'
                                    . ' where a.branch_id =:pbranch_id;');
          
            $cmm->addParam('pbranch_id', $criteriaparam['branch_id']);     
            $dtTran=  \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtTran->Rows())> 0){
                $this->bo->account_id = $dtTran->Rows()[0]['account_id'];
                $this->bo->branch_id = $criteriaparam['branch_id'];
                $this->bo->branch_name = $dtTran->Rows()[0]['branch_name'];
                $this->bo->last_updated = $dtTran->Rows()[0]['last_updated'];
            }  
        }
    }
    
    public function beforeSave($cn) {            
        parent::beforeSave($cn);
    }
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);       
        
        if($tablename=='sys.branch'){
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from ac.sp_ib_account_add_update(:pbranch_id, :paccount_id)');                
            $cmm->addParam('pbranch_id',$this->bo->branch_id);                
            $cmm->addParam('paccount_id', $this->bo->account_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
        }
    }   
}
