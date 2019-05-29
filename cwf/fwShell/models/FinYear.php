<?php
namespace app\cwf\fwShell\models;

class FinYear{
    public $finyears;
    public $formname, $listname, $disp;
    private $dt;
    private $userinfo;
            
    function __construct() {
        $sessionid = \Yii::$app->request->get('sessionid');
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance($sessionid)->getUserInfo();
        if($this->userinfo===NULL){
            return;
        }
        $this->formname='finyearlist';
        $this->listname='finyears';
        $this->disp='code';
        $this->finyears=array();
        $this->GetFinyears();
    }
    
    
    function GetFinyears(){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.finyear '
                . 'where company_id=:pcompanyid order by year_begin desc');
        $cmm->addParam('pcompanyid', $this->userinfo->getCompany_ID());
        $this->dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($this->dt->Rows())>0){
            $this->SetFinYearList();
        }
    }
    
    function SetFinYearList(){
        foreach ($this->dt->Rows() as $rw) {
            $finyearinfo=array();
            $finyearinfo['code']=$rw['finyear_code'];
            $finyearinfo['starts']=$this->formatdate($rw['year_begin']);
            $finyearinfo['ends']=$this->formatdate($rw['year_end']);
            $finyearinfo['close']=$rw['year_close'];
            $this->finyears[$rw['finyear_id']]=$finyearinfo;
        }
    }
    
    function formatdate($date){
        $tmp=new \DateTime($date);
        return $tmp->format('d M, Y');
    }
    
    function SetFinYearInfo($fyid){
        foreach ($this->dt->Rows() as $rw){
            if($rw['finyear_id']===(int)$fyid){
                $this->userinfo->setSessionVariable('finyear_id', $rw['finyear_id']);
                $this->userinfo->setSessionVariable('finyear', $rw['finyear_code']);
                $this->userinfo->setSessionVariable('year_begin', $rw['year_begin']);
                $this->userinfo->setSessionVariable('year_end', $rw['year_end']);
                $this->userinfo->persistSessionVariables();
            }
        }
    }
}

