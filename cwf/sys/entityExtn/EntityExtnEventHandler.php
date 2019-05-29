<?php

namespace app\cwf\sys\entityextn;
include 'EntityExtnHelper.php';

class EntityExtnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);
        $this->bo->fields = new \app\cwf\vsla\data\DataTable();
        $this->bo->custom_fields = new \app\cwf\vsla\data\DataTable();
    }
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);  
        
        if(($this->bo->entity_type == null || $this->bo->entity_type == -1) 
                && ($this->bo->bo_id == null || $this->bo->bo_id == -1)) {
            $this->bo->entity_type = $criteriaparam['formData']['SelectEntity']['entity_type'];
            $this->bo->bo_id = $criteriaparam['formData']['SelectEntity']['bo_id'];
        }
        $info = EntityExtnHelper::getFields($this->bo->bo_id);
        $this->bo->fields = $info['fields'];
        $this->bo->metainfo_controlTable = $info['control_table'];
        if(($this->bo->entity_type == null || $this->bo->entity_type == -1)) {
            $this->bo->entity_type = $info['type'];
        }
        foreach($this->bo->fields->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('fields', $cols);
        $cols = [];
        $this->bo->custom_fields = EntityExtnHelper::fromExtnFields($this->bo->extn_info);
        foreach($this->bo->custom_fields->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('custom_fields', $cols);
        
    }
    
    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
    }
    
    public function afterSave($cn) {
        parent::afterSave($cn);
        $cols = [];
        $this->bo->custom_fields = EntityExtnHelper::fromExtnFields($this->bo->extn_info);
        foreach($this->bo->custom_fields->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('custom_fields', $cols);        
        
        $tbl = explode('.', $this->bo->metainfo_controlTable);
        if(count($tbl) == 2) {
            $cmdtext = 'SELECT column_name
                        FROM information_schema.columns
                        WHERE table_schema = :pschema
                          AND table_name   = :ptable AND column_name = :pxf';
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText($cmdtext);
            $cmd->addParam('pschema', $tbl[0]);
            $cmd->addParam('ptable', $tbl[1]);
            $cmd->addParam('pxf', 'xf');
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmd);
            if(count($dt->Rows()) == 0) {
                $cmdtext = 'ALTER TABLE '.$this->bo->metainfo_controlTable.' ADD COLUMN xf jsonb NOT NULL DEFAULT \'{}\'';
                $cmd = new \app\cwf\vsla\data\SqlCommand();
                $cmd->setCommandText($cmdtext);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmd);
            }            
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }
}