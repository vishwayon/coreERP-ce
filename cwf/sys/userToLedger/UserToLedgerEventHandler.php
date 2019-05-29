<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userToLedger;

/**
 * Description of UserToLedgerEventHandler
 *
 * @author Priyanka
 */
class UserToLedgerEventHandler extends \app\cwf\sys\user\UserEventHandler {

    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);

        if ($this->bo->user_id == '' or $this->bo->user_id == -1) {
            $this->bo->is_active = true;
        }
    }

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->createLedger();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.user_to_ledger Where user_id = :puser_id');
        $cmm->addParam('puser_id', $this->bo->user_id);
        $dtResult =  \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach($dtResult->Rows() as $row){
            $newRow = $this->bo->user_to_ledger->NewRow();
            $newRow['account_id'] = $row['account_id'];
            $newRow['user_id'] = $row['user_id'];
            $newRow['user_to_ledger_id'] = $row['user_to_ledger_id'];
            $this->bo->user_to_ledger->AddRow($newRow);
        }        
    }

    private function createLedger() {
        $this->bo->user_to_ledger = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->user_to_ledger->addColumn('user_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $this->bo->user_to_ledger->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->user_to_ledger->addColumn('user_to_ledger_id', $phpType, $default, 50, $scale, true);
        $this->bo->user_to_ledger->setPKField('user_to_ledger_id');
        foreach ($this->bo->user_to_ledger->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('user_to_ledger', $cols);
    }

    public function onSave($cn, $tablename) {
        // Do not call base method here
        // This is just an anchoring object and the base table 
        // should not be affected
    }

    public function afterSave($cn) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From sys.user_to_ledger Where user_id = :puser_id');
        $cmm->addParam('puser_id', $this->bo->user_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        $aid = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('INSERT INTO sys.user_to_ledger(
                                user_to_ledger_id, user_id, account_id, company_id, last_updated)
                                VALUES (:puser_to_ledger_id, :puser_id, :paccount_id, :pcompany_id, :plast_updated);');
        $cmm->addParam('puser_to_ledger_id', '');
        $cmm->addParam('puser_id', $this->bo->user_id);
        $cmm->addParam('paccount_id', -1);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('plast_updated', null);
        foreach ($this->bo->user_to_ledger->Rows() as &$ref_row) {
            $aid = (string) $this->bo->user_id . ':' . (string) $ref_row['account_id'];
            $cmm->setParamValue('puser_to_ledger_id', $aid);
            $cmm->setParamValue('paccount_id', $ref_row['account_id']);
            $cmm->setParamValue('plast_updated', date('Y-m-d'));
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }
    }

    public function onDelete($cn, $tablename) {
        parent::onDelete($cn, $tablename);
    }

}
