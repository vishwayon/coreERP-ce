<?php

namespace app\cwf\sys\importbalance;

class modelImportBalance{
    
    public $importAccBal = false;
    public $importInvBal = false;
    public $resAccBal = NULL;
    public $resInvBal = NULL;
    public $errmsg = '';
    public $msg = '';
    
    public function __construct() {
        
    }
    
    public function getAccBal() {
        if($this->resAccBal != NULL) {
            return $this->resAccBal->Rows();
        }
        return [];
    }
    public function getInvBal() {
        if($this->resInvBal != NULL) {
            return $this->resInvBal->Rows();
        }
        return [];
    }
    
    public function importBalance() {
        $msg = '';
        if($this->importAccBal == TRUE) {
            $msg = $this->importAccountBalance();
            if($msg == ''){
                $this->resAccBal = $this->checkAccountBalance();
            }
        }
        if($this->importInvBal == TRUE) {
            $this->importInventoryBalance();
            $this->resInvBal = $this->checkInventoryBalance();
        }
        if ($msg == ''){
            $this->msg = (($this->importAccBal == TRUE) ? 'Account' : '').
                    (($this->importAccBal == TRUE && $this->importInvBal == TRUE) ? ' and ' : '').
                    (($this->importInvBal == TRUE) ? 'Inventory' : '').
                    ' balance imported successfully.';
        }
        else{
            $this->msg =$msg;
        }
    }

    private function importAccountBalance() {
        $def_finyear = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = "Select cast(value as varchar) as finyear from sys.settings where key='ac_start_finyear'";
        $cmm->setCommandText($cmmText);
        $dt =  \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows()) > 0){
            $def_finyear = $dt->Rows()[0]['finyear'];
        }
        
        if($def_finyear == '' || ($def_finyear != \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'))){        
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            try{
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmmText = 'Select * from ac.sp_import_account_opbal(:pcompany_id, :ptarget_year)';
                $cmm->setCommandText($cmmText);
                $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
                $cmm->addParam('ptarget_year', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
                $cn->beginTransaction();
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $cn->commit();
            } catch (\Exception $ex) {
                if($cn->inTransaction()){
                    $cn->rollBack();
                    $cn = null;
                } 
                $this->errmsg = $ex->getMessage();
                return $this->errmsg;
            } 
        }
        else{
            return 'Import not allowed as start financial year and connected year are same.';
        }
        return '';
    }
    
    private function checkAccountBalance() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = 'Select * from ac.fn_tb_report(:pcompany_id, :pbranch_id, :pfinyear, :pyear_begin, :pyear_end)';
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
        $cmm->addParam('pyear_begin', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin'));
        $cmm->addParam('pyear_end', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin'));
        $cmm->setCommandText($cmmText);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function importInventoryBalance() {
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        try{
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmmText = 'Select * from st.sp_import_stock_opbal(:pcompany_id, :ptarget_year)';
            $cmm->setCommandText($cmmText);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
            $cmm->addParam('ptarget_year', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
            $cn->beginTransaction();
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            $cn->commit();
        } catch (\Exception $ex) {
            if($cn->inTransaction()){
                $cn->rollBack();
                $cn = null;
            } 
            $this->errmsg = $ex->getMessage();
            return $this->errmsg;
        }
    }
    
    private function checkInventoryBalance() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = 'Select * from st.fn_material_balance_wac_by_inv_ac(:pcompany_id, :pbranch_id, :pfinyear, :pto_date)';
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
        $to_date = strtotime(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin'));
        $prev_date = date("Y-m-d", strtotime('-1 days', $to_date) );
        $cmm->addParam('pto_date', $prev_date);
        $cmm->setCommandText($cmmText);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
}
