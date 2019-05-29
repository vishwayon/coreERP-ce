<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ActionScript
 *
 * @author girish
 */

namespace app\cwf\vsla\entity;

use app\cwf\vsla\data\SqlCommand;
use app\cwf\vsla\data\DataTable;
use app\cwf\vsla\data\DataConnect;

class ActionScript {

    //Constants
    const TABLE_TYPE_MASTER_CONTROL = 1;
    const TABLE_TYPE_MASTER_TRAN = 2;
    const TABLE_TYPE_DOC_CONTROL = 3;
    const TABLE_TYPE_DOC_TRAN = 4;

    //private variables
    private $tableName = '';
    private $tableType = ActionScript::TABLE_TYPE_MASTER_CONTROL;
    private $fKey = '';
    private $rootFKey       = '';
    private $tranGroup = null;
    private $tableFieldCollection = null;
    private $insertCmm = null;
    private $updateCmm = null;
    private $deleteCmm = null;
    private $fetchCmm = null;

    /**
     * @param string $tname <p>
     * Mention the name of the table along with schema
     * e.g: accounts.account_head
     * If schema is not mentioned, the default is public </p>
     * @param int $tableType [optional] <p>
     * Mentions the type of table. This value
     * must be one of the ActionScript::TABLE_TYPE_* constants,
     * defaulting to value of ActionScript::TABLE_TYPE_MASTER_CONTROL </p>
     * @param string $fKey <p>
     * Mention the foreign key used for delete of tran table. 
     * Is useful only when TABLE_TYPE_MASTER_TRAN is set </p>
     */
    public function __construct($tname, $dbType = DataConnect::COMPANY_DB, $tableType = ActionScript::TABLE_TYPE_MASTER_CONTROL, $fKey = '', $rootFKey = '', $tranGroup = null) {
        $this->tableName = $tname;
        $this->tableType = $tableType;
        $this->fKey = $fKey;
        $this->rootFKey = $rootFKey;
        $this->tranGroup = $tranGroup;

        $tableParts = explode(".", $this->tableName);
        $cmm = new SqlCommand();
        //$cmm->setCommandText("Select * From sys.fntablefieldcollection(:pSchema, :pTable);");
        // Experimental Feature: Comment the following line incase of entity problems
        // and uncomment the previous line
        $cmm->setCommandText("Select * From sys.fn_table_def(:pSchema, :pTable);");
        if (count($tableParts) == 2) {
            $cmm->addParam("pSchema", $tableParts[0]);
            $cmm->addParam("pTable", $tableParts[1]);
        } else {
            $cmm->addParam("pSchema", "public");
            $cmm->addParam("pTable", $tname);
        }
        $this->tableFieldCollection = DataConnect::getData($cmm, $dbType);
    }

    public function getTableName() {
        return $this->tableName;
    }

    /**
     * 
     * @return \vsla\data\DataTable
     */
    public function getTableFieldCollection() {
        return $this->tableFieldCollection;
    }

    public function getInsertCmm() {
        if ($this->insertCmm == null) {
            $this->insertScript($this->tableFieldCollection);
        }
        return $this->insertCmm;
    }

    public function getUpdateCmm() {
        if ($this->updateCmm == null) {
            $this->updateScript($this->tableFieldCollection);
        }
        return $this->updateCmm;
    }

    public function getFetchCmm($orderby = '') {
        if ($this->fetchCmm == null) {
            $this->generateFetchScript($this->tableFieldCollection, $orderby);
        }
        return $this->fetchCmm;
    }

    /**
     * 
     * @return SqlCommand
     */
    public function getDeleteCmm() {
        if ($this->deleteCmm == null) {
            $this->generateDeleteScript($this->tableFieldCollection);
        }
        return $this->deleteCmm;
    }

    /**
     * 
     * @param \vsla\data\DataTable $dt
     * @return SqlCommand
     */
    private function generateFetchScript(DataTable $dt, $orderby = '') {
        switch ($this->tableType) {
            case ActionScript::TABLE_TYPE_MASTER_CONTROL:
                $this->fetchScript($dt);
                break;
            case ActionScript::TABLE_TYPE_MASTER_TRAN:
                $this->fetchTranScript($dt, $orderby);
                break;
        }
    }

    /**
     * 
     * @param \vsla\data\DataTable $dt
     * @return SqlCommand
     */
    private function generateDeleteScript(DataTable $dt) {
        switch ($this->tableType) {
            case ActionScript::TABLE_TYPE_MASTER_CONTROL:
                $this->deleteScript($dt);
                break;
            case ActionScript::TABLE_TYPE_MASTER_TRAN:
                $this->deleteTranScript($dt);
                break;
            default:
                break;
        }
    }

    private function insertScript(DataTable $dt) {
        $this->insertCmm = new SqlCommand();
        $stmt = "Insert Into " . $this->tableName . "(";
        $flds = '';
        $params = '';
        foreach ($dt->Rows() as $row) {
            if ($flds != '') {
                $flds .= ', ' . $row['column_name'];
                if ($row['column_name'] == 'last_updated') {
                    $params .= ', current_timestamp(0)';
                } else {
                    $params .= ', :p' . $row['column_name'];
                }
            } else {
                $flds = $row['column_name'];
                if ($row['column_name'] == 'last_updated') {
                    $params = 'current_timestamp(0)';
                } else {
                    $params = ':p' . $row['column_name'];
                }
            }
            if ($row['column_name'] != 'last_updated') {
                $this->insertCmm->addParam('p' . $row['column_name'], null, \app\cwf\vsla\data\SqlParamType::PARAM_IN, \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType($row['udt_name']));
            }
        }
        $this->insertCmm->setCommandText($stmt . $flds . ')' . "\nValues(" . $params . ')');
    }

    private function fetchScript(DataTable $dt) {
        $criteria = '';
        $this->fetchCmm = new SqlCommand();
        $stmt = "Select * From " . $this->tableName;
        foreach ($dt->Rows() as $row) {
            if ($row['is_primary'] == TRUE) {
                if ($criteria == '') {
                    $criteria = $row['column_name'] . "=:p" . $row['column_name'];
                } else {
                    $criteria .= " And a." . $row['column_name'] . "=:p" . $row['column_name'];
                }
                $this->fetchCmm->addParam('p' . $row['column_name'], null);
            }
        }
        $stmt .= "\nWhere " . $criteria;
        $this->fetchCmm->setCommandText($stmt);
    }

    private function fetchTranScript(DataTable $dt, $orderby) {
        $this->fetchCmm = new SqlCommand();
        $stmt = "Select * From " . $this->tableName;
        $criteria = $this->fKey . "=:p" . $this->fKey;       
        if ($this->tranGroup != null) {
            $criteria .= " And " . $this->tranGroup . "=:p" . $this->tranGroup;
        }
        $stmt .= "\nWhere " . $criteria;
        if ($orderby <> '') {
            $stmt .= "\nOrder by " . $orderby;
        }
        $this->fetchCmm->setCommandText($stmt);
        $this->fetchCmm->addParam('p' . $this->fKey, null);
        if ($this->tranGroup != null) {
            $this->fetchCmm->addParam('p' . $this->tranGroup, null);
        }
    }

    private function updateScript(DataTable $dt) {
        $this->updateCmm = new SqlCommand();
        $stmt = "Update " . $this->tableName . " a \nSet ";
        $flds = '';
        $criteria = '';
        foreach ($dt->Rows() as $row) {
            if ($row['is_primary'] == FALSE) {
                if ($flds != '') {
                    if ($row['column_name'] == 'last_updated') {
                        $flds .= ",\n" . $row['column_name'] . "=current_timestamp(0)";
                    } else {
                        $flds .= ",\n" . $row['column_name'] . "=:p" . $row['column_name'];
                    }
                } else {
                    if ($row['column_name'] == 'last_updated') {
                        $flds = $row['column_name'] . "=current_timestamp(0)";
                    } else {
                        $flds = $row['column_name'] . "=:p" . $row['column_name'];
                    }
                }
            } else {
                if ($criteria == '') {
                    $criteria = "a." . $row['column_name'] . "=:p" . $row['column_name'];
                } else {
                    $criteria .= " And a." . $row['column_name'] . "=:p" . $row['column_name'];
                }
            }
            if ($row['column_name'] != 'last_updated') {
                $this->updateCmm->addParam('p' . $row['column_name'], null, \app\cwf\vsla\data\SqlParamType::PARAM_IN, \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType($row['udt_name']));
            }
        }
        $stmt .= $flds . "\nWhere " . $criteria;
        $this->updateCmm->setCommandText($stmt);
    }

    private function deleteScript(DataTable $dt) {
        $this->deleteCmm = new SqlCommand();
        $stmt = "Delete From " . $this->tableName;
        $criteria = '';
        foreach ($dt->Rows() as $row) {
            if ($row['is_primary'] == TRUE) {
                if ($criteria == '') {
                    $criteria = $row['column_name'] . "=:p" . $row['column_name'];
                } else {
                    $criteria .= " And a." . $row['column_name'] . "=:p" . $row['column_name'];
                }
                $this->deleteCmm->addParam('p' . $row['column_name'], null);
            }
        }
        $stmt .= "\nWhere " . $criteria;
        $this->deleteCmm->setCommandText($stmt);
    }

    private function deleteTranScript() {
        $this->deleteCmm = new SqlCommand();
        $stmt = "Delete From " . $this->tableName;
        if($this->rootFKey == ''){
            $criteria = $this->fKey."=:p".$this->fKey;
        }
        else if ($this->rootFKey != ''){
            $criteria = $this->rootFKey."=:p".$this->rootFKey;
        }        
        if ($this->tranGroup != null) {
            $criteria .= " And " . $this->tranGroup . "=:p" . $this->tranGroup;
        }
        $stmt .= "\nWhere " . $criteria;
        $this->deleteCmm->setCommandText($stmt);
        if($this->rootFKey == ''){
            $this->deleteCmm->addParam('p'.$this->fKey, null);
        }
        else if ($this->rootFKey != ''){
            $this->deleteCmm->addParam('p'.$this->rootFKey, null);            
        }
        if ($this->tranGroup != null) {
            $this->deleteCmm->addParam('p' . $this->tranGroup, null);
        }
    }
}
