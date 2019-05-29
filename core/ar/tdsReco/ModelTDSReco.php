<?php

namespace app\core\ar\tdsReco;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelTDSReco{
    public $view_type_option;
    public $filters;
    public $customer_id;
    public $view_type_id;
    public $from_date;
    public $to_date;
    public $as_on;
    public $recodata;
    public $brokenrules=array();
    public $bookBalance=0;
    const VIEW_TYPE_UNRECONCILED = 0;
    const VIEW_TYPE_RECONCILED = 1;    
    const VIEW_TYPE_ALL = 2;
    
    public function __construct() {
        $this->view_type_option=array();
            $this->view_type_option[0]= 'unReconciled';
            $this->view_type_option[1]= 'Reconciled';
            $this->view_type_option[2]= 'All';
        $this->customer_id=-1;
        $this->view_type_id=0;
        $this->from_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $this->to_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
        $this->dt=array();
    }
    
    public function setFilters($filter){
        $this->customer_id=$filter['customer_id'];
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
//        if($this->customer_id>0){
            $this->GetRecoData();
//        }
    }
    
    function GetRecoData(){        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from ar.sp_tds_reco_collection(:pcompany_id, :pbranch_id, :pcustomer_id, :preconciled, :pas_on)');
        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $this->customer_id);
        $cmm->addParam('preconciled', $this->view_type_id);
        $cmm->addParam('pas_on', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->as_on));
        $recodt = DataConnect::getData($cmm);
        $recodt->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($recodt->Rows() as &$dr) {
            $dr['doc_date_sort'] = strtotime($dr['doc_date']);
        }
        $this->dt= $recodt;        
        
//        // Get balance as per books
//        $sql = 'Select debit_closing_balance-credit_closing_balance as book_balance From ac.fn_gl_bal_as_on(:pcompany_id, :pbranch_id, :pcustomer_id, :pfinyear, :pfrom_date, :pto_date);';
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText($sql);
//        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
//        $cmm->addParam('pbranch_id', 0);
//        $cmm->addParam('pcustomer_id', $this->customer_id);
//        $cmm->addParam('pfinyear', SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
//        $cmm->addParam('pfrom_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->as_on));
//        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->as_on));
//        
//        $result = DataConnect::getData($cmm);
//        if(count($result->Rows())==1) {
//            $this->bookBalance = $result->Rows()[0]['book_balance'];
//        }
    }
    
    public function setData($model){
        $this->validate($model);
        if(count($this->brokenrules)==0){
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try{

                $cmm=new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update ar.tds_reconciled '
                                        . ' Set reconciled=:preconciled, reco_date=:preco_date '
                                        . ' where voucher_id=:pvoucher_id;');
                $cmm->addParam('pvoucher_id', '');
                $cmm->addParam('preconciled', false);
                $cmm->addParam('preco_date', null);
                $cn->beginTransaction();
                for ($rowIndex=0;$rowIndex< count($model->dt);$rowIndex++) {
                    if($model->view_type_id == self::VIEW_TYPE_RECONCILED ){
                        if($model->dt[$rowIndex]->reconciled== FALSE){      
                            $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                            $cmm->setParamValue('preconciled', 0);
                            $cmm->setParamValue('preco_date', Null);
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                    if($model->view_type_id == self::VIEW_TYPE_UNRECONCILED){
                        if($model->dt[$rowIndex]->reconciled== TRUE){                
                            $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                            $cmm->setParamValue('preconciled', 1);
                            if($model->dt[$rowIndex]->reco_date=='1970-01-01'){
                                $cmm->setParamValue('preco_date',  date("Y-m-d", time()));
                            }
                            else{
                                $cmm->setParamValue('preco_date',  $model->dt[$rowIndex]->reco_date);
                            }
                            DataConnect::exeCmm($cmm, $cn);
                        } 
                        if($model->dt[$rowIndex]->reconciled== FALSE){      
                            $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                            $cmm->setParamValue('preconciled', 0);
                            $cmm->setParamValue('preco_date', Null);
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
        for ($rowIndex=0;$rowIndex< count($model->dt);$rowIndex++) {
            if($model->view_type_id == self::VIEW_TYPE_UNRECONCILED){
                if($model->dt[$rowIndex]->reconciled== TRUE){ 
                    if($model->dt[$rowIndex]->reco_date == '1970-01-01'){
                        array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id.' : Select proper Reconcilation Date.');
                    }
                    if(strtotime($model->dt[$rowIndex]->reco_date) <
                        strtotime($model->dt[$rowIndex]->doc_date)){
                        array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id.' : Reconcilation Date cannot be less than Doc Date');
                    }
                }                
            }
        }
    }        
}