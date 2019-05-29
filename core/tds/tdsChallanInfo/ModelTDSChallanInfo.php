<?php

namespace app\core\tds\tdsChallanInfo;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelTDSChallanInfo{
    public $filters;
    public $view_type_id;
    public $view_type_option;
    public $from_date;
    public $to_date;
    public $as_on;
    public $challandata;
    public $brokenrules=array();
    const VIEW_TYPE_NOTUPDATED = 0;
    const VIEW_TYPE_UPDATED = 1;    
    const VIEW_TYPE_ALL = 2;
    
    public function __construct() {
        $this->view_type_option=array();
            $this->view_type_option[0]= 'Not Updated';
            $this->view_type_option[1]= 'Updated';
            $this->view_type_option[2]= 'All';
        $this->view_type_id=0;
        $this->from_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $this->to_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
        $this->dt=array();
    }
    
    public function setFilters($filter){
        $this->view_type_id=$filter['view_type_id'];
        if(strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($filter['as_on']))< strtotime($this->from_date)){
           $this->as_on=\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->from_date);
        }
        elseif(strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($filter['as_on']))> strtotime($this->to_date)){
           $this->as_on=\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->to_date);
        }
        else{
           $this->as_on=$filter['as_on'];
        }
        $this->getData();
    }
    
    public function getData(){
        $this->GetRecoData();
    }
    
    function GetRecoData(){        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select false as selected, * from tds.sp_tds_challan_collection(:pcompany_id, :pbranch_id, :pupdated, :pas_on)");
        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_id'));
        $cmm->addParam('pas_on', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->as_on));
        $cmm->addParam('pupdated', $this->view_type_id);
        $this->dt= DataConnect::getData($cmm);
    }
    
    public function setData($model){
        $this->validate($model);
        if(count($this->brokenrules)==0){
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try{

                $cmm=new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update tds.tds_payment_control set challan_bsr =:pchallan_bsr, challan_serial=:pchallan_serial where voucher_id =:pvoucher_id');
                $cmm->addParam('pchallan_bsr', '');
                $cmm->addParam('pchallan_serial', '');
                $cmm->addParam('pvoucher_id', '');
                $cn->beginTransaction();
                for ($rowIndex=0;$rowIndex< count($model->dt);$rowIndex++) {
                    if($model->view_type_id == self::VIEW_TYPE_NOTUPDATED ){
                        if($model->dt[$rowIndex]->selected== TRUE){      
                            $cmm->setParamValue('pchallan_bsr', $model->dt[$rowIndex]->challan_bsr);
                            $cmm->setParamValue('pchallan_serial', $model->dt[$rowIndex]->challan_serial);
                            $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                    
                    if($model->view_type_id == self::VIEW_TYPE_UPDATED ){
                        if($model->dt[$rowIndex]->selected== TRUE){      
                            $cmm->setParamValue('pchallan_bsr', '');
                            $cmm->setParamValue('pchallan_serial', '');
                            $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                            DataConnect::exeCmm($cmm, $cn);
                        }                        
                    }
                }
                $cn->commit();
                $cn = null;
            } catch (\Exception $ex) {
                if($cn->inTransaction()){
                    $cn->rollBack();
                    $cn = null;
                }
                return $ex->getMessage();
            }
        }
    }
    
    public function validate($model){  
    }        
}