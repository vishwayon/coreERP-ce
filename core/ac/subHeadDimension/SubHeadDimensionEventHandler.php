<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHeadDimension;

/**
 * Description of SubHeadDimensionEventHandler
 *
 * @author Priyanka
 */
class SubHeadDimensionEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        // Create temp table for Account HJead
        $this->bo->sub_head_dim_acc = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->sub_head_dim_acc->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $this->bo->sub_head_dim_acc->addColumn('account_head', $phpType, $default, 250, $scale, $isUnique);
        
        if($this->bo->sub_head_dim_id !=-1)
        {
            // Fetch accounts for the sub head dimension
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select account_id, account_head
                                        from ac.account_head where sub_head_dim_id = :psub_head_dim_id');
            $cmm->addParam('psub_head_dim_id', $this->bo->sub_head_dim_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($dt->Rows() as $rowbr) {
                $newRow = $this->bo->sub_head_dim_acc->NewRow();
                $newRow['account_head'] = $rowbr['account_head'];
                $newRow['account_id'] = $rowbr['account_id'];
                $this->bo->sub_head_dim_acc->AddRow($newRow);
            }
            foreach($this->bo->sub_head_dim_acc->getColumns() as $col) {
               $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
            }
            $this->bo->setTranMetaData('sub_head_dim_acc', $cols);
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}
