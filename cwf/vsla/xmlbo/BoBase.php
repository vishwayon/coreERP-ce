<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xmlbo;


/**
 * This is an abstract base class that contains the property bag 
 * and implements arrayaccess
 * @author girish
 */
abstract class BoBase implements \ArrayAccess {
    //put your code here
    private $PropertyBag = null;
    /**
     *
     * @var \app\cwf\vsla\data\DataColumn
     */
    private $FieldMetaData = array();
    private $BRule = array();
    private $Warnings = array();
    private $TranMetaData=array();
    
    public function BOPropertyBag(){
        return $this->PropertyBag;
    }
    
    public function FieldMetaData() {
        return $this->FieldMetaData;
    }
    
    public function setFieldMetaData(\app\cwf\vsla\data\DataColumn $dataCol) {
        $this->FieldMetaData[] = $dataCol;
    }
    
    public function TranMetaData($tranName){
        return $this->TranMetaData[$tranName];
    }
    
    public function getAllTranMetaData(){
        return $this->TranMetaData;
    }
    
    public function setTranMetaData($tranName, $tranMeta){
        $this->TranMetaData[$tranName]=$tranMeta;
    }

    public function setTranColDefault($tranName, $colName, $default){
        $refcols = &$this->TranMetaData[$tranName];
        foreach($refcols as &$refcol){
            if($refcol['columnName'] == $colName){
                $refcol['default'] = $default;
                break;
            }
        }
    }
    
    public function addBRule($ruleDesc) {
        array_push($this->BRule, $ruleDesc);
    }
    
    public function getBRules() {
        return $this->BRule;
    }
    
    public function addWarning($warningDesc) {
        array_push($this->Warnings, $warningDesc);
    }
    
    public function getWarnings() {
        return $this->Warnings;
    }
    
    public function resetWarnings() {
        return $this->Warnings = array();
    }
    
    
    // <editor-fold defaultstate="collapsed" desc="ArrayAccess Implementation">
    public function offsetExists($offset) {
        return isset($this->PropertyBag[$offset]);
    }
    
    public function offsetGet($offset) {
        return isset($this->PropertyBag[$offset]) ? $this->PropertyBag[$offset] : null;
    }
    
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->PropertyBag[] = $value;
        } else {
            $this->PropertyBag[$offset] = $value;
        }
    }
    
    public function offsetUnset($offset) {
        unset($this->PropertyBag[$offset]);
    }

    // </editor-fold>
    
    //<editor-fold defaultstate="collapsed" desc="Array to Property Implementation">
    public function &__get($key) {
        
        return $this->PropertyBag[$key];
    }
    
    public function __set($key, $value) {
        $this->PropertyBag[$key] = $value;
    }
    
    public function __isset($key) {
        return isset($this->PropertyBag[$key]);
    }
    
    public function __unset($key) {
        unset($this->PropertyBag[$key]);
    }
    
    // </editor-fold>
}
