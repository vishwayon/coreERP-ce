<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\user;

/**
 * Description of UserEventHandler
 *
 * @author Priyanka
 */
class UserEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);

        if ($this->bo->user_id == '' or $this->bo->user_id == -1) {
            $this->bo->is_active = true;
        }
    }

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->user_id != -1) {
            $this->bo->user_pass = 'aaaaa';
            $this->bo->user_pass_confirm = 'aaaaa';
        } else {
            $this->bo->user_pass_confirm = '';
        }
        $this->bo->is_admin = false;
        $this->createBranchTran();
    }

    public function onSave($cn, $tablename) {
        if ($this->bo->user_id == -1) {
            $this->saveOnNew($cn, $tablename);
        } else if ($this->bo->user_id != -1 && $this->bo->user_pass != 'aaaaa') {
            $this->saveOnEditPass($cn, $tablename);
        } else {
            $this->saveOnEditOther($cn);
        }
        $this->saveBranchTran();
    }

    public function afterCommit($generatedKeys) {
        $this->fetchBranchTranTemp();
    }

    private function saveOnNew($cn, $tablename) {
        $companyid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        $userid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($companyid, $tablename, $cn);
        $as = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts($tablename, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $cmm = $as->getInsertCmm();
        $cmm->setParamValue('puser_id', $userid);
        $cmm->setParamValue('puser_name', $this->bo->user_name);
        $cmm->setParamValue('puser_pass', \Yii::$app->getSecurity()->generatePasswordHash($this->bo->user_pass));
        $cmm->setParamValue('pfull_user_name', $this->bo->full_user_name);
        $cmm->setParamValue('pemail', $this->bo->email);
        $cmm->setParamValue('pis_active', $this->bo->is_active);
        $cmm->setParamValue('pis_owner', $this->bo->is_owner);
        $cmm->setParamValue('pis_admin', $this->bo->is_admin);
        $cmm->setParamValue('pauth_client', $this->bo->auth_client);
        $cmm->setParamValue('pauth_person_id', $this->bo->auth_person_id);
        $cmm->setParamValue('pauth_account', $this->bo->auth_account);
        $cmm->setParamValue('pis_mac_addr', $this->bo->is_mac_addr);
        $cmm->setParamValue('pmac_addr', $this->bo->mac_addr);
        $cmm->setParamValue('pphone', $this->bo->phone);
        $cmm->setParamValue('pmobile', $this->bo->mobile);
        $cmm->setParamValue('puser_attr', json_encode($this->bo->user_attr->Value()));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        $this->bo->user_id = $userid;

        // Save in company to user
        $cmmComp = new \app\cwf\vsla\data\SqlCommand();
        $cmmComp->setCommandText("Select count(*) as comp_count From sys.company");
        $dtcomp = \app\cwf\vsla\data\DataConnect::getData($cmmComp);
        if (count($dtcomp->Rows()) == 1 && intval($dtcomp->Rows()[0]['comp_count']) == 1) {
            $cmmComp = new \app\cwf\vsla\data\SqlCommand();
            $sql = "Insert Into sys.user_to_company(user_to_company_id, user_id, company_id, last_updated)";
            $sql .= "Values(:pcomp_id || ':' || :puser_id, :puser_id::BigInt, :pcomp_id::BigInt, current_timestamp(0))";
            $cmmComp->setCommandText($sql);
            $cmmComp->addParam("puser_id", $userid);
            $cmmComp->addParam("pcomp_id", $companyid);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmmComp, $cn);
        }
    }

    private function saveOnEditPass($cn) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = 'Update sys.user 
                    Set user_name = :puser_name, full_user_name = :pfull_user_name, email = :pemail, is_active = :pis_active, user_pass = :puser_pass, 
                        is_owner = :pis_owner, is_admin = :pis_admin, 
                        mac_addr = :pmac_addr, is_mac_addr = :pis_mac_addr, phone = :pphone, mobile = :pmobile,
                        user_attr = :puser_attr
                    Where user_id = :puser_id';
        $cmm->setCommandText($cmmText);
        $cmm->addParam('puser_id', $this->bo->user_id);
        $cmm->addParam('puser_name', $this->bo->user_name);
        $cmm->addParam('puser_pass', \Yii::$app->getSecurity()->generatePasswordHash($this->bo->user_pass));
        $cmm->addParam('pfull_user_name', $this->bo->full_user_name);
        $cmm->addParam('pemail', $this->bo->email);
        $cmm->addParam('pis_active', $this->bo->is_active);
        $cmm->addParam('pis_owner', $this->bo->is_owner);
        $cmm->addParam('pis_admin', $this->bo->is_admin);
        $cmm->addParam('pis_mac_addr', $this->bo->is_mac_addr);
        $cmm->addParam('pphone', $this->bo->phone);
        $cmm->addParam('pmobile', $this->bo->mobile);
        $cmm->addParam('pmac_addr', $this->bo->mac_addr, \app\cwf\vsla\data\SqlParamType::PARAM_IN, \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_ARRAY);
        $cmm->addParam('puser_attr', json_encode($this->bo->user_attr->Value()));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
    }

    private function saveOnEditOther($cn) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = 'Update sys.user 
                    Set user_name = :puser_name,  full_user_name = :pfull_user_name, email = :pemail, is_active = :pis_active, 
                        is_owner = :pis_owner, is_admin = :pis_admin,
                        mac_addr = :pmac_addr, is_mac_addr = :pis_mac_addr, phone = :pphone, mobile = :pmobile,
                        user_attr = :puser_attr
                    Where user_id = :puser_id';
        $cmm->setCommandText($cmmText);
        $cmm->addParam('puser_id', $this->bo->user_id);
        $cmm->addParam('puser_name', $this->bo->user_name);
        $cmm->addParam('pfull_user_name', $this->bo->full_user_name);
        $cmm->addParam('pemail', $this->bo->email);
        $cmm->addParam('pis_active', $this->bo->is_active);
        $cmm->addParam('pis_owner', $this->bo->is_owner);
        $cmm->addParam('pis_admin', $this->bo->is_admin);
        $cmm->addParam('pis_mac_addr', $this->bo->is_mac_addr);
        $cmm->addParam('pphone', $this->bo->phone);
        $cmm->addParam('pmobile', $this->bo->mobile);
        $cmm->addParam('pmac_addr', $this->bo->mac_addr, \app\cwf\vsla\data\SqlParamType::PARAM_IN, \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_ARRAY);
        $cmm->addParam('puser_attr', json_encode($this->bo->user_attr->Value()));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
    }

    private function createBranchTran() {

        $this->bo->user_branch_role = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->user_branch_role->addColumn('user_branch_role_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('string'), $default, 0, $scale, $isUnique);
        $this->bo->user_branch_role->addColumn('user_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $this->bo->user_branch_role->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $this->bo->user_branch_role->addColumn('company_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $this->bo->user_branch_role->addColumn('role_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('timestamp');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->user_branch_role->addColumn('last_updated', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->user_branch_role->setPKField('user_branch_role_id');
        foreach ($this->bo->user_branch_role->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('user_branch_role', $cols);

        $this->bo->user_branch_role_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->user_branch_role_temp->cloneColumns($this->bo->user_branch_role);


        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.user_branch_role where user_id=:puser_id');
        $cmm->addParam('puser_id', $this->bo->user_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        foreach ($dt->Rows() as $row) {

            $newRow = $this->bo->user_branch_role->NewRow();
            $newRow['user_branch_role_id'] = $row['user_branch_role_id'];
            $newRow['user_id'] = $row['user_id'];
            $newRow['branch_id'] = $row['branch_id'];
            $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            $newRow['role_id'] = $row['role_id'];
            $this->bo->user_branch_role->AddRow($newRow);
        }
    }

    private function fetchBranchTranTemp() {

        foreach ($this->bo->user_branch_role->Rows() as $row) {
            $newRow = $this->bo->user_branch_role_temp->NewRow();
            $newRow['user_branch_role_id'] = $row['user_branch_role_id'];
            $newRow['user_id'] = $row['user_id'];
            $newRow['branch_id'] = $row['branch_id'];
            $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            $newRow['role_id'] = $row['role_id'];
            $this->bo->user_branch_role_temp->AddRow($newRow);
        }
    }

    protected function saveBranchTran() {

        $cnComp = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        $cnComp->beginTransaction();
        try {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM sys.user_branch_role WHERE user_id=:puser_id And company_id=:pcompany_id');
            $cmm->addParam('puser_id', $this->bo->user_id);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cnComp);
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('sys.user_branch_role', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN);

            $cmm = $ac->getInsertCmm();
            foreach ($this->bo->user_branch_role->Rows() as &$ref_branch_row) {
                $tran_pk_id = $this->bo->user_id . ':' . $ref_branch_row['branch_id'] . ':' . $ref_branch_row['role_id'];
                $cmm->setParamValue('puser_branch_role_id', $tran_pk_id);
                $cmm->setParamValue('puser_id', $this->bo->user_id);
                $cmm->setParamValue('pbranch_id', $ref_branch_row['branch_id']);
                $cmm->setParamValue('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
                $cmm->setParamValue('prole_id', $ref_branch_row['role_id']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cnComp);
                $ref_branch_row['user_branch_role_id'] = $tran_pk_id;
            }
            $cnComp->commit();
        } catch (Exception $ex) {
            if (isset($cnComp)) {
                $cnComp->rollBack();
            }
            throw $ex;
        } finally {
            $cnComp = null;
        }
    }

}
