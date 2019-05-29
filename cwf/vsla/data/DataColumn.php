<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\data;
/**
 * Description of DataColumn
 *
 * @author girish
 */
class DataColumn {
        
        public $columnName  = '';
        public $phpDataType = DataAdapter::PHPDATA_TYPE_UNKNOWN;
        public $default     = null;
        public $length      = 0;
        public $scale       = 0;
        public $isUnique    = false;
        public $ntName      = '';
        
        public function __construct($columnName, $phpDataType, $default, $length=0, $scale=0, $isUnique=false, $ntName='') {
            $this->columnName = $columnName;
            $this->phpDataType =$phpDataType;
            $this->default = $default;
            $this->length = $length;
            $this->scale = $scale;
            $this->isUnique = $isUnique;
            if($phpDataType==DataAdapter::PHPDATA_TYPE_DATATABLE) {
                $this->ntName = $ntName;
            }
        }
    }
