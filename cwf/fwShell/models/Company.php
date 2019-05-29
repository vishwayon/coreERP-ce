<?php

namespace app\cwf\fwShell\models;
use app\cwf\vsla\data\DataConnect;

class Company{
    public $companies;
    public $formname, $listname, $disp;
    private $dt;
    private $userinfo;
            
    function __construct() {
        $sessionid = \Yii::$app->request->get('sessionid');
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance($sessionid)->getUserInfo();
        if($this->userinfo===NULL){
            return;
        }
        $this->formname='companylist';
        $this->listname='companies';
        $this->disp='company_name';
        $this->companies=array();
        $this->GetCompanies();
    }
    
    function GetCompanies(){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmmtext='';
        if($this->userinfo->isAdmin()){
            $cmmtext='select * from sys.company b order by company_name asc';
        }else{
            $cmmtext='select * from sys.user_to_company a '
                . 'inner Join sys.company b on a.company_id=b.company_id '
                . 'where a.user_id=:puserid order by company_name asc';
            $cmm->addParam('puserid', $this->userinfo->getUser_ID());
        }
        $cmm->setCommandText($cmmtext);
        $this->dt= DataConnect::getData($cmm,  DataConnect::MAIN_DB);
        if(count($this->dt->Rows())>0){
            $this->SetCompanyList();
        }
    }
    
    function SetCompanyList(){
        foreach ($this->dt->Rows() as $rw) {
            $companyinfo=array();
            $companyinfo['company_code']=$rw['company_code'];
            $companyinfo['company_short_name']=$rw['company_short_name'];
            $companyinfo['company_name']=$rw['company_name'];
            $companyinfo['company_address']=$rw['company_address'];
            $this->companies[$rw['company_id']]=$companyinfo;
        }
    }
    
    function SetCompanyInfo($companyid){
        foreach ($this->dt->Rows() as $rw){
            if($rw['company_id']===(int)$companyid){
                $this->userinfo->setSessionVariable('company_id', $rw['company_id']);
                $this->userinfo->setSessionVariable('companyDB', $rw['database']);
                $this->userinfo->setSessionVariable('company_short_name', $rw['company_short_name']);
                $this->userinfo->persistSessionVariables();
            }
        }
//        $cmm=new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('select * from sys.branch where branch_is_ho=true');
//        $dtbr= DataConnect::getData($cmm);
//        if(count($dtbr->Rows())>0){
//            $this->userinfo->setSessionVariable('branch_id', $dtbr->Rows()[0]['branch_id']);
//            $this->userinfo->setSessionVariable('branch_name', $dtbr->Rows()[0]['branch_name']);
//            $this->userinfo->setSessionVariable('date_format', $dtbr->Rows()[0]['date_format']);
//            $this->userinfo->persistSessionVariables();
//        }
    }    
}
