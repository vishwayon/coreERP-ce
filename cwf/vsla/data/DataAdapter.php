<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DataAdapter
 *
 * @author girish
 */
namespace app\cwf\vsla\data;
    
class DataAdapter {
    const PHPDATA_TYPE_UNKNOWN = 'unknown';
    const PHPDATA_TYPE_STRING  = 'string';
    const PHPDATA_TYPE_INT     = 'int';
    const PHPDATA_TYPE_DECIMAL = 'decimal';
    const PHPDATA_TYPE_DATE    = 'date';
    const PHPDATA_TYPE_DATETIME= 'datetime';
    const PHPDATA_TYPE_BOOL    = 'bool';
    const PHPDATA_TYPE_DATATABLE='datatable';
    const PHPDATA_TYPE_ARRAY  = 'array';
    const PHPDATA_TYPE_JSON   = 'json';

    //put your code here
    public static function Fill(DataTable $dt, \PDOStatement $query) {
        // First fill the column names
//        for($i=0;$i<$query->columnCount();$i++) {
//            $colMeta = $query->getColumnMeta($i);
//            $colName = $colMeta['name'];
//            $phpDataType = self::getPDOtoPHPDataType((string)$colMeta['pdo_type']);
//            $default = self::getPHPDataTypeDefault($phpDataType);
//            $length = $colMeta['len'];
//
//            $dt->addColumn($colName, $phpDataType, $default, $length);
//        }

        // Second fill the data for each row only
        //$rawData = $query->fetchAll();
        //self::setDtData($dt, $rawData);
        $rowData = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        if(count($rowData)>0) {
            $row = $rowData[0];
            $cols = array_keys($row);
            $colCount = count($cols);
            for($i=0;$i<$colCount;$i++) {
                $colName = $cols[$i];
                $phpDataType = self::getPHPTypeHint($row[$colName]);
                $default = self::getPHPDataTypeDefault($phpDataType);
                $dt->addColumn($colName, $phpDataType, $default, -1);
            }
        } else {
            for($i=0;$i<$query->columnCount();$i++) {
                $colMeta = $query->getColumnMeta($i);
                $colName = $colMeta['name'];
                $phpDataType = self::getPDOtoPHPDataType((string)$colMeta['pdo_type']);
                $default = self::getPHPDataTypeDefault($phpDataType);
                $length = $colMeta['len'];
                $dt->addColumn($colName, $phpDataType, $default, $length);
            }   
        } 
        $dt->setData($rowData);
    }
    
    private static function setDtData(DataTable $dt, array $rawData) {
        // This is not working as intended. Do not use
        foreach($rawData as $sourcerow) {
            $newRow = array();
            foreach($sourcerow as $col => $dbval) {
                $newRow[$col] = self::getDBtoPHPDataValue($dt->getColumn($col)->dbType, $dbval);
            }
            $dt->addRow($newRow);
        }
    }

    public static function getPDOtoPHPDataType($pdoType) {
        switch($pdoType) {
            case \PDO::PARAM_INT:
                return DataAdapter::PHPDATA_TYPE_INT;
            case \PDO::PARAM_STR:
                return DataAdapter::PHPDATA_TYPE_STRING;
            case \PDO::PARAM_BOOL:
                return DataAdapter::PHPDATA_TYPE_BOOL;
            default :
                return DataAdapter::PHPDATA_TYPE_STRING;
        }
    }

    public static function getDBtoPHPDataType($dbType) {
        $args = explode("_", $dbType);
        switch($args[0]) {
            case "int2":
            case "int4":
            case "int8":
                return DataAdapter::PHPDATA_TYPE_INT;
            case "numeric":
            case "decimal":
                return DataAdapter::PHPDATA_TYPE_DECIMAL;
            case "text":
            case "varchar":
            case "bpchar":
                return DataAdapter::PHPDATA_TYPE_STRING;
            case "date":
                return DataAdapter::PHPDATA_TYPE_DATE;
            case "timestamp":
                return DataAdapter::PHPDATA_TYPE_DATETIME;
            case "boolean":
            case "bool":
                return DataAdapter::PHPDATA_TYPE_BOOL;
            case "ARRAY":
                return DataAdapter::PHPDATA_TYPE_ARRAY;
            case "jsonb":
            case "json":
                return DataAdapter::PHPDATA_TYPE_JSON;
            default :
                return DataAdapter::PHPDATA_TYPE_UNKNOWN;
        }
    }
    
        public static function getEXTNtoPHPDataType($xType) {
        $args = explode("_", $xType);
        switch($args[0]) {           
            case "int":
                return DataAdapter::PHPDATA_TYPE_INT;
            case "decimal":
                return DataAdapter::PHPDATA_TYPE_DECIMAL;
            case "string":
                return DataAdapter::PHPDATA_TYPE_STRING;
            case "date":
                return DataAdapter::PHPDATA_TYPE_DATE;           
            case "bool":
                return DataAdapter::PHPDATA_TYPE_BOOL;            
            default :
                return DataAdapter::PHPDATA_TYPE_UNKNOWN;
        }
    }
        
    // Converts the Data type returned from the database to a php type
    public static function getDBtoPHPDataValue($dbType, $dbValue, $fieldName = '') {
        if(is_null($dbValue)) {
            return null;
        }
        $args = explode("_", $dbType);
        switch($args[0]) {
            case "int2":
            case "int4":
            case "int8":
                return intval($dbValue) ;
            case "numeric":
            case "decimal":
                return floatval($dbValue);
            case "varchar":
            case "bpchar":
                return $dbValue;
            case "date":
                return $dbValue;
            case "timestamp":
                return (new \DateTime($dbValue))->format('Y-m-d H:i:s T');
            case "boolean":
            case "bool":
                return boolval($dbValue);
            case "ARRAY":
                return ArrayField::parse_dbvalue($dbValue, $args[1]);
            case "json":
            case "jsonb":
                return JsonField::parse_dbvalue($fieldName, $dbValue);
            default :
                return $dbValue;
        }
    }       
    

    public static function getPHPDataTypeDefault($phpType) {
        switch ($phpType) {
            case DataAdapter::PHPDATA_TYPE_INT:
                return -1;
            case DataAdapter::PHPDATA_TYPE_DECIMAL:
                return 0.00;
            case DataAdapter::PHPDATA_TYPE_STRING:
                return '';
            case DataAdapter::PHPDATA_TYPE_DATE:
                return date("Y-m-d", time());
            case DataAdapter::PHPDATA_TYPE_DATETIME:
                return date("Y-m-d H:i:s T", time());
            case DataAdapter::PHPDATA_TYPE_BOOL:
                return false;
            case DataAdapter::PHPDATA_TYPE_ARRAY:
                return new ArrayField();
            case DataAdapter::PHPDATA_TYPE_JSON:
                return JsonField::parse_dbvalue('', '{}');
            case DataAdapter::PHPDATA_TYPE_DATATABLE:
                return new DataTable();
            default :
                return null;
        }
    }

    public static function getPHPTypeHint($var) {
        $typ = gettype($var);
        if ($typ == 'integer') {
            return self::PHPDATA_TYPE_INT;
        } else if ($typ == 'double' || $typ == 'float') {
            return self::PHPDATA_TYPE_DECIMAL;
        } else if ($typ == 'boolean') {
            return self::PHPDATA_TYPE_BOOL;
        } else {
            // try for timestamp
                if(preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]\s[0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $var)) {
                    return self::PHPDATA_TYPE_DATETIME;
                }
            // try for date
                if(preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $var)) {
                    return self::PHPDATA_TYPE_DATE;
                }
            // try float
                if(preg_match('/^[\d]*\.[\d]*$/', $var)) {
                    return self::PHPDATA_TYPE_DECIMAL;
                }
            // default to string
            return self::PHPDATA_TYPE_STRING;
        }
    }
    
    /**
     * Gets the DBType constant for php field data type
     * @param string $phpType
     */
    public static function getPHPtoDBDataType(string $phpType) {
        switch(strtolower($phpType)) {
            case "string":
                return DataAdapter::PHPDATA_TYPE_STRING;
            case "int":
            case "bigint":
                return DataAdapter::PHPDATA_TYPE_INT;
            case "decimal":
            case "numeric":
                return DataAdapter::PHPDATA_TYPE_DECIMAL;
            case "date":
                return DataAdapter::PHPDATA_TYPE_DATE;
            case "time":
            case "datetime":
                return DataAdapter::PHPDATA_TYPE_DATETIME;
            case "bool":
            case "boolean":
                return DataAdapter::PHPDATA_TYPE_BOOL;
            default :
                return DataAdapter::PHPDATA_TYPE_STRING;
        }
    }
}
