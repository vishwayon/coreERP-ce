<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\hsnRate;
use app\cwf\vsla\data\DataConnect;

/**
 * Description of HSNRateEventHandler
 *
 * @author Priyanka
 */
class HSNRateEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);   
    }    
        
    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
        
        if($tablename=='tx.hsn_sc'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select a.hsn_sc_id, a.hsn_sc_code, a.hsn_sc_code, a.hsn_sc_desc, 
                                    COALESCE(b.gst_rate_id, -1) as gst_rate_id, 
                                    COALESCE(c.gst_rate_desc, '') gst_rate_desc,
                                    COALESCE(b.hsn_sc_uom_id, -1) as hsn_sc_uom_id,
                                    COALESCE(b.is_exempt, false) as is_exempt,
                                    a.last_updated
                                from tx.hsn_sc a 
                                left join tx.hsn_sc_rate b on a.hsn_sc_id = b.hsn_sc_id
                                left Join tx.gst_rate c on b.gst_rate_id =c.gst_rate_id
                                where a.hsn_sc_id = :phsn_sc_id;");
          
            $cmm->addParam('phsn_sc_id', $criteriaparam['hsn_sc_id']);     
            $dtTran=  \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtTran->Rows())> 0){
                $this->bo->gst_rate_id = $dtTran->Rows()[0]['gst_rate_id'];
                $this->bo->hsn_sc_id = $criteriaparam['hsn_sc_id'];
                $this->bo->hsn_sc_desc = $dtTran->Rows()[0]['hsn_sc_desc'];
                $this->bo->hsn_sc_code = $dtTran->Rows()[0]['hsn_sc_code'];
                $this->bo->hsn_sc_uom_id = $dtTran->Rows()[0]['hsn_sc_uom_id'];
                $this->bo->is_exempt = $dtTran->Rows()[0]['is_exempt'];
                $this->bo->last_updated = $dtTran->Rows()[0]['last_updated'];
            }  
        }
    }
    
    public function beforeSave($cn) {            
        parent::beforeSave($cn);
    }
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);       
        
        if($tablename=='tx.hsn_sc'){            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from tx.sp_hsn_sc_rate_add_update(:pcompany_id, :phsn_sc_id, :pgst_rate_id, :phsn_sc_uom_id, :pis_exempt)');                
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));                
            $cmm->addParam('phsn_sc_id', $this->bo->hsn_sc_id);        
            $cmm->addParam('pgst_rate_id', $this->bo->gst_rate_id);
            $cmm->addParam('phsn_sc_uom_id', $this->bo->hsn_sc_uom_id);
            $cmm->addParam('pis_exempt', $this->bo->is_exempt);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
        }
    }   
}
