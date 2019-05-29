<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\pos\tday;

/**
 * Description of TdayValidator
 *
 * @author girish
 */
class TdayValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    const STATUS_OPEN = 0;
    const STATUS_EOD_STARTED = 1;
    const STATUS_EOD_COMPLETED = 2;
    const STATUS_HANDOVER_STARTED = 3;
    const STATUS_CLOSED = 5;
    
    public function validateTdayEditForm() {
        $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id');
        
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
        // create session id
         if($this->bo->tday_id == -1 || $this->bo->tday_id == '') {
            $this->bo->start_time = date("Y-m-d H:i:s T", time());
            if(strtotime($this->bo->tday_date) >= strtotime('2017-07-01')) {
                $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
            }
            $this->bo->end_time = null;
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select remote_server_id From pos.terminal Where terminal_id=:pterminal_id and company_id=:pcompany_id');
            $cmm->addParam('pterminal_id', $this->bo->terminal_id);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));            
            $dtrs = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtrs->Rows())==1) {
                $this->bo->remote_server_id = $dtrs->Rows()[0]['remote_server_id'];
            }
            $this->bo->tday_session_id = $this->uuid_make(md5($this->bo->remote_server_id.$this->bo->terminal_id.$this->bo->tday_date.$this->bo->start_time));
        } else if ($this->bo->tday_id != -1 && $this->bo->is_open == false) {
            $this->bo->end_time = date("Y-m-d H:i:s T", time());
        }
    }
    
    private function validateBusinessRules() {
        // New tday
        if($this->bo->tday_id == -1 || $this->bo->tday_id == '') {
            // Ensure that tday is open
            if(!$this->bo->tday_status == self::STATUS_OPEN) {
                $this->bo->addBRule('POS Terminal Txn. Day should be open at time of creation.');
            }
            
            // Validate duplicate Open Session
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select tday From pos.tday 
                                  Where terminal_id=:pterminal_id
                                    And tday_status In (0, 1) And tday_id!=:ptday_id');
            $cmm->addParam('pterminal_id', $this->bo->terminal_id);
            $cmm->addParam('ptday_id', $this->bo->tday_id);
            //$cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $dtos = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtos->Rows())>0) {
                $this->bo->addBRule('POS Terminal Txn. Day already open. Multiple open sessions not allowed.');
            }
            
            // Prohibit Multiple user session for same user in same company
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select tday From pos.tday 
                                  Where user_id=:puser_id
                                    And tday_status In (0, 1) And tday_id!=:ptday_id and company_id=:pcompany_id');
            $cmm->addParam('puser_id', $this->bo->user_id);
            $cmm->addParam('ptday_id', $this->bo->tday_id);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $dtus = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtus->Rows())>0) {
                $this->bo->addBRule('POS User has a open session. Multiple open sessions not allowed.');
            }
            
            // Ensure tday is within current finyear
            if(!$this->validateDateValue($this->bo->tday_date)) {
                $this->bo->addBRule('Txn. Date does not belong to current Financial Year. Please login into proper Financial Year to start Txn. day.');
            }
        } else {
            // Editing existing day
            if($this->bo->user_id != \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID()) {
                $this->bo->addBRule('Only User(s) starting a Txn. Day can modify/close for handover.');
            }
        }
    }
    
    private function uuid_make($string){
        $string = substr($string, 0, 8 ) .'-'.
        substr($string, 8, 4) .'-'.
        substr($string, 12, 4) .'-'.
        substr($string, 16, 4) .'-'.
        substr($string, 20);
        return $string;
    }
    
}
