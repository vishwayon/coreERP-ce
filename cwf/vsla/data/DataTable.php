<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DataTable
 *
 * @author girish
 */

namespace app\cwf\vsla\data;

class DataTable implements \JsonSerializable {

    //put your code here
    private $columns = array();
    private $rowData = array();
    private $pkField = '';
    // Contains a collection of nested table definitions
    private $ntDefs = array();

    public function __construct() {
        
    }

    public function &Rows() {
        return $this->rowData;
    }

    public function NewRow() {
        $newRow;
        foreach ($this->columns as $col) {
            if ($col->phpDataType == DataAdapter::PHPDATA_TYPE_DATATABLE) {
                $newRow[$col->columnName] = $this->ntDefs[$col->ntName]->createDataTable();
            } else {
                $newRow[$col->columnName] = $col->default;
            }
        }
        return $newRow;
    }

    public function addRow($newRow) {
        array_push($this->rowData, $newRow);
    }

    public function removeRow($index) {
        unset($this->rowData[$index]);
        $this->rowData = array_values($this->rowData);
    }

    public function getRowIndex(string $fieldName, $fieldValue) {
        $index = 0;
        foreach ($this->Rows() as $row) {
            if ($row[$fieldName] == $fieldValue) {
                return $index;
            }
            $index++;
        }
        return -1;
    }

    /** Removes all rows from the collection */
    public function removeAll() {
        $this->rowData = array();
    }

    public function getColumns() {
        return $this->columns;
    }
    
    public function getColumnsStripped(string $keyField = '') {
        $cols = [];
        foreach($this->columns as $col) {
            $cols[] = [
                'columnName' => $col->columnName,
                'dataType' => ($keyField == $col->columnName ? 'id' : $col->phpDataType)
            ];
        }
        return $cols;
    }
    
    public function getColumn($columnName) {
        foreach ($this->columns as $col) {
            if ($col->columnName == $columnName) {
                return $col;
            }
        }
    }

    public function cloneColumns($sourceTable) {
        foreach ($sourceTable->getColumns() as $col) {
            $this->addColumn($col->columnName, $col->phpDataType, $col->default, $col->length, $col->scale, $col->isUnique);
        }
    }

    /*     * @param string $colName 
     * @param string $dataType 
     *          */

    public function addColumn($colName, $phpDataType, $default, $length = 0, $scale = 0, $isUnique = false, $ntName = '') {
        $col = new DataColumn($colName, $phpDataType, $default, $length, $scale, $isUnique, $ntName);
        $this->columns[$colName] = $col;

        // Add this column to each row in the already existing data
        if (count($this->rowData) > 0) {
            foreach ($this->rowData as &$row) {
                // Add a new column with default value only if it does not exist
                if (!array_key_exists($col->columnName, $row)) {
                    if ($col->phpDataType == DataAdapter::PHPDATA_TYPE_DATATABLE) {
                        $dt = $this->ntDefs[$col->ntName]->createDataTable();
                    } else {
                        $row[$col->columnName] = $col->default;
                    }
                }
            }
        }
    }

    public function setPKField($pkfield) {
        $this->pkField = $pkfield;
    }

    public function getPKField() {
        return $this->pkField;
    }

    public function setData(array $data) {
        if (count($data) > 0) {
            $this->rowData = $data;
        } else {
            $this->rowData = [];
        }
    }

    /**
     * Groups data based on the 'keyField'. 
     * If valueField is an array, an array of arrays is returned with the keyField as array index.
     * If valueField is string, an array is returned with keyField as array index. 
     * @param string $keyField The field that would be evaluated for grouping
     * @param mixed $valueField The columns included in the returned array.
     * @return array The resulting array of groups
     */
    public function asArray($keyField, $valueField) {
        $result = array();
        if (is_array($valueField)) {
            foreach ($this->Rows() as $row) {
                $newRow = array();
                foreach ($valueField as $col) {
                    $newRow[$col] = $row[$col];
                }
                $result[$row[$keyField]][] = $newRow;
            }
        } else {
            foreach ($this->Rows() as $row) {
                $result[$row[$keyField]] = $row[$valueField];
            }
        }
        return $result;
    }

    /**
     * Creates an instance of nested table and returns that instance
     * @param string $tname
     * @return \app\cwf\vsla\data\NestedTable
     */
    public function addNTDef($tname) {
        $nt = new NestedTable();
        $this->ntDefs[$tname] = $nt;
        return $nt;
    }

    /**
     * Returns an already exisiting instance of nested table
     * @param string $tname
     * @return \app\cwf\vsla\data\NestedTable
     */
    public function getNTDef($tname) {
        return $this->ntDefs[$tname];
    }

    public function jsonSerialize() {
        return $this->rowData;
    }

    public function getDump() {
        var_dump($this->columns);
        var_dump($this->rowData);
    }

    /**
     * If cols is an array, an array of arrays is returned.
     * If cols is string, a single dimension array is returned.
     * @param mixed $cols
     * @return array
     */
    public function select($cols) {
        $result = array();
        if (is_array($cols)) {
            // request is array, therefore return multi-dimension array
            foreach ($this->Rows() as $row) {
                $newRow = array();
                foreach ($cols as $col) {
                    $newRow[$col] = $row[$col];
                }
                $result[] = $newRow;
            }
        } else {
            // request is single column. Therefore return single-dimension array
            foreach ($this->Rows() as $row) {
                $result[] = $row[$cols];
            }
        }
        return $result;
    }

    /**
     * Finds all rows in the table matching the criteria in fieldValue.
     * e.g: account_type_id = 12, would translate to $fieldName=account_id, $fieldValue=12
     * Warning: Changes made to the rows are not reflected in the table
     * 
     * @param string $fieldName The field name to search on
     * @param mixed $fieldValue The field value to filter by
     * @param array $cols Optional: collection of column names to be returned in result. 
     * if no column names are mentioned, all columns in the row are returned
     * @return array An multi-dimensional array of field=>value pairs  (non-referenced)
     */
    public function findRows(string $fieldName, $fieldValue, array $cols = []) {
        $result = array();
        if (count($cols) > 0) {
            foreach ($this->Rows() as $row) {
                if ($row[$fieldName] == $fieldValue) {
                    $crow = [];
                    foreach($cols as $col) {
                        $crow[$col] = $row[$col]; 
                    }
                    $result[] = $crow;
                }
            }
        } else {
            foreach ($this->Rows() as $row) {
                if ($row[$fieldName] == $fieldValue) {
                    $result[] = $row;
                }
            }
        }
        return $result;
    }

    /**
     * Finds the first row in the table matching the criteria in fieldValue.
     * e.g: account_type_id = 12, would translate to $fieldName=account_id, $fieldValue=12
     * Warning: Changes made to the row are not reflected in the table
     * 
     * @param string $fieldName The field name to search on
     * @param mixed $fieldValue The field value to filter by
     * @return array A array of field=>value pairs  (non-referenced)
     */
    public function findRow(string $fieldName, $fieldValue) {
        $result = array();
        foreach ($this->Rows() as $row) {
            if ($row[$fieldName] == $fieldValue) {
                $result = $row;
                break;
            }
        }
        return $result;
    }

}
