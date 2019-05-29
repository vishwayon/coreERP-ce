<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\section;

/**
 * Description of SectionEventHandler
 *
 * @author Shrishail
 */
class SectionEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
    }
    
    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
        
        if($tablename=='tds.section'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.section_id, a.section, a.section_code, a.section_desc, 
                                            coalesce(b.tds_account_id, -1) as tds_account_id, c.account_head as tds_account, a.last_updated
                                    from tds.section a
                                    left Join tds.section_acc b on a.section_id=b.section_id
                                    left Join ac.account_head c on b.tds_account_id = c.account_id
                                    where a.section_id=:psection_id;');
          
            $cmm->addParam('psection_id', $criteriaparam['section_id']);     
            $dtTran=  \app\cwf\vsla\data\DataConnect::getData($cmm);
            
            foreach ($dtTran->Rows() as $row) {
                $this->bo->section_id = $row['section_id'];
                $this->bo->section = $row['section'];
                $this->bo->section_code = $row['section_code'];
                $this->bo->section_desc = $row['section_desc'];
                $this->bo->tds_account_id = $row['tds_account_id'];
                $this->bo->last_updated = $row['last_updated'];
            }   
        }
    }
    
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
        
        if($tablename=='tds.section'){  
             // Insert records in account balance for new financial year
            $cmm = new \app\cwf\vsla\data\SqlCommand();        
            $cmm->setCommandText("select * from tds.section_acc_add_update(:psection_id, :ptds_account_id)");
            $cmm->addParam('psection_id', $this->bo->section_id);
            $cmm->addParam('ptds_account_id', $this->bo->tds_account_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
    
}
