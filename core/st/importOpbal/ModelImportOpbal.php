<?php

namespace app\core\st\importOpbal;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelImportOpbal{
    public $prev_year;
    public $prev_year_desc;
    public $current_year_desc;
    public $brokenrules=array();
    public $msg;
    
    public function __construct() {
        
    }
    
    public function getData(){
        $this->GetImportOpbalData();
    }
    
    function GetImportOpbalData(){   
        
        // Get Prev year details
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.* from sys.finyear a Where a.year_end = (select b.year_begin - '1 day'::interval from sys.finyear b where b.finyear_code = :pfinyear)");
        $cmm->addParam('pfinyear',  SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));        
        $dt= DataConnect::getData($cmm);
        
        if(count($dt->Rows()) > 0){
            $this->prev_year = $dt->Rows()[0]['finyear_code'];
            $this->prev_year_desc = "Finyear " . $dt->Rows()[0]['finyear_code'] . " starts from ".  \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dt->Rows()[0]['year_begin']) 
                ." and ends on ". \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dt->Rows()[0]['year_end']);
        }
        
        $this->current_year_desc  = "Finyear " .  SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear') . " starts from "
                .  \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin')) 
                ." and ends on ". \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end'));
    }
    
    public function validate($model){   
        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select year_close from sys.finyear a Where a.finyear_code = :pfinyear");
        $cmm->addParam('pfinyear',  SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));        
        $dt= DataConnect::getData($cmm);
        if(count($dt->Rows()) > 0){
            if($dt->Rows()[0]['year_close'] == true){
                array_push($this->brokenrules, 'Connected year is closed. Cannot import stock balance.');
            }
        }
    }    
    
    public function setData($model){
        $this->validate($model);
        if(count($this->brokenrules) ==0){
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try{

                $cmm=new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from st.sp_import_stock_opbal(:pcompany_id, :ptarget_year);');
                $cmm->addParam('pcompany_id',  SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id') );
                $cmm->addParam('ptarget_year', SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear') );
                $cn->beginTransaction();
                DataConnect::exeCmm($cmm, $cn);
                
                $cn->commit();
                $cn = null;
                $this->msg='Ok';
                return $this->msg;
            } catch (\Exception $ex) {
                if($cn->inTransaction()){
                    $cn->rollBack();
                    $cn = null;
                } 
                $this->msg = $ex->getMessage();
                return $this->msg;
            }
        }
        else{            
                $this->msg='Error';
        }
    }
}