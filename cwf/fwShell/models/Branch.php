<?php
namespace app\cwf\fwShell\models;

class Branch{
    public $branchdetails;
    public $formname, $listname, $disp;
    private $dt;
    private $userinfo;
            
    function __construct() {
        $sessionid = \Yii::$app->request->get('sessionid');
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance($sessionid)->getUserInfo();
        if($this->userinfo===NULL){
            return;
        }
        $this->formname='branchlist';
        $this->listname='branchdetails';
        $this->disp='branch_name';
        $this->branchdetails=array();
        $this->GetBranchDetails();
    }
    
    
    function GetBranchDetails(){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
//         $cmmtext='select * from sys.branch '
//                . 'order by branch_name asc';
        if($this->userinfo->isAdmin()){
            $cmmtext='select * from  sys.branch order by branch_name asc';
        }else{
            $cmmtext='select * from sys.sp_get_branch_for_user(:puserid) '
                . 'order by branch_name asc';
            $cmm->addParam('puserid', $this->userinfo->getUser_ID());
        }
        $cmm->setCommandText($cmmtext);
        $this->dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($this->dt->Rows())>0){
            $this->SetBranchDetailList();
        }
    }
    
    function SetBranchDetailList(){
        foreach ($this->dt->Rows() as $rw) {
            $branchitem=array();
            $branchitem['branch_code']=$rw['branch_code'];
            $branchitem['branch_name']=$rw['branch_name'];
            $branchitem['branch_address']=$rw['branch_address'];
            $this->branchdetails[$rw['branch_id']]=$branchitem;
        }
    }
    
    function SetBranchInfo($brid){
        foreach ($this->dt->Rows() as $rw){
            if($rw['branch_id']===(int)$brid){
                $this->userinfo->setSessionVariable('branch_id', $rw['branch_id']);
                $this->userinfo->setSessionVariable('branch_name', $rw['branch_name']);
                $this->userinfo->setSessionVariable('date_format', $rw['date_format']);
                $this->userinfo->persistSessionVariables();
            }
        }
    }
}

