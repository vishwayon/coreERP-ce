<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xmlbo;

abstract class EventHandlerBase {

    protected $bo;

    public function initialise(BoBase $bo) {
        $this->bo = $bo;
    }

    public function beforeFetch(&$criteriaparam) {
        
    }

    public function afterFetch($criteriaparam) {
        
    }

    public function onFetch($criteriaparam, $tablename) {
        
    }

    public function beforeSave($cn) {
        
    }

    public function onSave($cn, $tablename) {
        
    }

    public function beforeEntitySave($cn, $tablename) {
        
    }

    public function afterEntitySave($cn, $tablename) {
        
    }

    public function afterSave($cn) {
        
    }

    public function afterPost() {
        
    }

    public function afterUnPost() {
        
    }

    public function afterCommit($generatedKeys) {
        
    }

    public function onDelete($cn, $tablename) {
        
    }

    public function beforeEntityDelete($cn, $tablename) {
        
    }

    public function afterEntityDelete($cn, $tablename) {
        
    }

    public function afterDeleteCommit() {
        
    }

    public function afterApplySecurity() {
        
    }
    
    public function onArchive($cn, $action){
        
    }
    
    public function resetLastUpdated($cn, $tableName, $primaryKey) {
        // This is a master. Therefore, pickup last_updated
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select last_updated From ' . $tableName .
                ' Where ' . $primaryKey . ' = :ppk_id');
        $cmm->addParam('ppk_id', $this->bo[$primaryKey]);
        $dtStatus = \app\cwf\vsla\data\DataConnect::getData($cmm, null, $cn);
        $this->bo->last_updated = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataValue('timestamp', $dtStatus->Rows()[0]['last_updated']);
    }

}
