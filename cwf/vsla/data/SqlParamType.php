<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */



/**
 * Description of SqlParamType
 *
 * @author girish
 */
namespace app\cwf\vsla\data {
    class SqlParamType {
        const PARAM_IN = 0;
        const PARAM_INOUT = 1;
        const PARAM_OUT = 2;
        
        const PARAM_PREFIX = 'p';


        public $ParamName = '';
        public $ParamValue = null;
        public $ParamDirection = self::PARAM_IN;
        public $DataType = DataAdapter::PHPDATA_TYPE_UNKNOWN; 

        public function __construct($paramName, $paramValue, $paramDirection = self::PARAM_IN, $dataType = DataAdapter::PHPDATA_TYPE_UNKNOWN) {
            $this->ParamName = $paramName;
            $this->ParamValue = $paramValue;
            $this->ParamDirection = $paramDirection;
            $this->DataType = $dataType;
        }
    }
}
