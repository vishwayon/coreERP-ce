<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SqlCommand
 *
 * @author girish
 */
namespace app\cwf\vsla\data {
    class SqlCommand {
        
        //put your code here
        private $cmmText    =   '';
        private $cmmParam   =   [];
        
        public $returnValue = null;
        
        /**
         * 
         * @param string $text
         */
        public function setCommandText($text) {
            $this->cmmText = $text;
        }
        
        /**
         * 
         * @param string $paramName
         * @param mixed $paramValue
         * @param int $paramDirection
         */
        public function addParam($paramName, $paramValue, $paramDirection = SqlParamType::PARAM_IN, $dataType = DataAdapter::PHPDATA_TYPE_UNKNOWN) {
            $this->cmmParam[$paramName] = new SqlParamType($paramName, $paramValue, $paramDirection, $dataType);            
        }
        
        public function getCommandText() {
            $ct = $this->parseConstants();
            return $ct;
        }
        
        public function getParams() {
            return $this->cmmParam;
        }
        
        public function getParamsForBind() {
            $result = null;
            if($this->cmmParam!==null){
            foreach($this->cmmParam as $key => $param) {
                if($param->ParamDirection == SqlParamType::PARAM_IN || $param->ParamDirection == SqlParamType::PARAM_INOUT) {
                    $result[$param->ParamName] = $this->parseValue($param);                    
                }
            }}
            return $result;
        }
        
        public function setParamValue($paramName, $paramValue) {
            $this->cmmParam[$paramName]->ParamValue = $paramValue;
        }
        
        public function getParamValue($paramName) {
            return $this->cmmParam[$paramName]->ParamValue;
        }
        
        public function setOutput($result) {
            if($result === null || $this->cmmParam===null) {
                return;
            }
            if(!is_array($result)&& $result.length()===0){
                return;
            }
            foreach($this->cmmParam as $key => $param) {
                if($param->ParamDirection == SqlParamType::PARAM_INOUT || $param->ParamDirection == SqlParamType::PARAM_OUT) {
                    $param->ParamValue = $result[0][str_replace(':', '', $param->ParamName)];//substr_replace($param->ParamName, ':', 0, 1)];
                }
            }
            if(count($result)>0) {
                $this->returnValue = $result[0];
            } else {
                $this->returnValue = $result;
            }
        }
        
        private function parseConstants(){
            $ct = $this->cmmText;
            if(strstr($ct, '{company_id}')){
                $companyid=\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
                $ct = str_replace('{company_id}', $companyid, $ct);
            }
            if(strstr($ct, '{branch_id}')){
                $branchid=\app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $ct = str_replace('{branch_id}', $branchid, $ct);
            }
            if(strstr($ct, '{finyear}')){
                $finyear=\app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
                $ct = str_replace('{finyear}', $finyear, $ct);
            }
            if(strstr($ct, '{user_id}')){
                $user_id=\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
                $ct = str_replace('{user_id}', $user_id, $ct);
            }
            if(strstr($ct, '{http_host}')){
               $http_host=$_SERVER['HTTP_HOST'];
               $ct = str_replace('{http_host}', $http_host, $ct);
            }
            return $ct;
        }
        
        private function parseValue(SqlParamType $param) {
            $result = null;
            if($param->DataType == DataAdapter::PHPDATA_TYPE_UNKNOWN) {
                if(gettype($param->ParamValue) == "boolean") {
                    // boolean needs to be passed as 0 = false and 1 = true for postgres PDO
                    $result = $param->ParamValue ? 1 : 0;
                } else {
                    $result = $param->ParamValue;
                }                
            } else {
                switch ($param->DataType) {
                    case DataAdapter::PHPDATA_TYPE_BOOL:
                        $result = $param->ParamValue ? 1 : 0;
                        break;
                    case DataAdapter::PHPDATA_TYPE_ARRAY:
                        if($param->ParamValue instanceof ArrayField) {
                            $result = $param->ParamValue->get_dbvalue();
                        } else {
                            $result = '{'.ArrayField::str_putcsv($param->ParamValue, ',', '"').'}';
                        }
                        break;
                    case DataAdapter::PHPDATA_TYPE_JSON:
                        $result = json_encode($param->ParamValue);
                        break;
                    default :
                        $result = $param->ParamValue;
                        break;
                }               
            }
            return $result;
        
        }        
              
    }
}
