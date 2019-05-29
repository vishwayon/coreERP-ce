<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\core\pos\tday;
/**
 * Description of TdayEventHandler
 *
 * @author girish
 */
class TdayEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase implements \app\cwf\vsla\xmlbo\ISequence  {
    
    
    public function generateNewSeqID(\PDO $cn) {
        $id = -1;
        // We generate a sudo company id with remote server is as prefix (would work upto 99 companies)
        $sudo_comp_id = ($this->bo->remote_server_id * 100) + $this->bo->company_id;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_get_mast_id(:pcompany_id, :pmast_seq_type, :pnew_mast_id)');
        $cmm->addParam('pcompany_id', $sudo_comp_id);
        $cmm->addParam('pmast_seq_type', 'pos.tday');        
        $cmm->addParam('pnew_mast_id', $id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        $id = $cmm->getParamValue('pnew_mast_id');
        return $id;
    }
    
    public function afterFetch($criteriaparam) {
        if($this->bo->tday_id == -1 || $this->bo->tday_id == '') {
            // New Tday
            $this->bo->tday_date = date("Y-m-d");
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
            $this->bo->user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
            $this->bo->tday_status = TdayValidator::STATUS_OPEN;           
            
        }
        // create dummy property for rendering
        $this->bo->show_eod_data = false;
    }
    
}
