<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\data;

/**
 * Nested table is used inside a DataTable to create 
 * multiple tran relations. It can only contain table definitions
 * @author girish
 */
class NestedTable {
    private $columns    = array();
    private $pkField = '';
    
    public function addColumn($colName, $phpDataType, $default, $length=0, $scale=0, $isUnique=false, $ntName='') {
        $col = new DataColumn($colName, $phpDataType, $default, $length, $scale, $isUnique, $ntName);
        $this->columns[$colName] = $col;
    }
    
    /**
     * Returns a new Datatable with the required column definitions
     */
    public function createDataTable() {
        $dt = new DataTable();
        foreach($this->columns as $col) {
            if($col->phpDataType == DataAdapter::PHPDATA_TYPE_DATATABLE) {
                $dt->addColumn($col->columnName, $col->phpDataType, $col->default, $col->length, $col->scale, $col->isUnique, $col->ntName);
                $nt = $dt->addNTDef($col->ntName);
                $nt->cloneColumns($col->default);
            } else {
                $dt->addColumn($col->columnName, $col->phpDataType, $col->default, $col->length, $col->scale, $col->isUnique, $col->ntName);
            }
        }
        $dt->setPKField($this->pkField);
        return $dt;
    }
    
    public function cloneColumns(DataTable $dt) {
        foreach($dt->getColumns() as $col) {
            $this->addColumn($col->columnName, $col->phpDataType, $col->default, $col->length, $col->scale, $col->isUnique, $col->ntName);
        }
        $this->pkField=$dt->getPKField();
    }
}
