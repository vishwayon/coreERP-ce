<?php
namespace app\cwf\vsla\data;

use app\cwf\vsla\data\SqlCommand;

class SqlParser{
    public static function getSql($sql){
        $cmm=new SqlCommand();
        $cmm->setCommandText((string)$sql->command);
        if(isset($sql->params)){
            foreach ($sql->params->param as $param) {
                $paramval=NULL;
                if($param->session){
                    $paramval=\app\cwf\vsla\security\SessionManager::
                            getSessionVariable((string)$param->session);                
                }else if($param->text){                         
                    $paramval= (string)$param->text; 
                }else if($param->dateFormat){                        
                    $paramval=  \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                }else if($param->numberFormat){                        
                    $paramval=  \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                }else if($param->currentDate){                        
                    $paramval=  date("Y-m-d", time());
                }else if($param->userID){
                    $paramval=$paramval=\app\cwf\vsla\security\SessionManager::
                    getInstance()->getUserInfo()->getUser_ID();
                }
                $cmm->addParam((string)$param['id'], $paramval);
            }
        }
        return $cmm;
    }
}