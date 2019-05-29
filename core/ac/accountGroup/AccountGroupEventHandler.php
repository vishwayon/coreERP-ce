<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\accountGroup;

/**
 * Description of AccountGroupEventHandler
 *
 * @author Kaustubh
 */
class AccountGroupEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->group_id == '' or $this->bo->group_id == -1) {
            $this->bo['parent_group_id'] = -1;
            $this->bo['old_parent_group_id'] = -1;
        } else {
            // *** Set 'Parent Group' when form is open in edit mode
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('SELECT group_id FROM ac.account_group WHERE group_key=:pparent_key');
            $cmm->addParam('pparent_key', $this->bo->parent_key);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($result->Rows() as $row) {
                $this->bo->parent_group_id = $row['group_id'];
            }

            $this->bo->old_parent_group_id = $this->bo->parent_group_id;
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);

        if ($this->bo->group_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('SELECT * FROM ac.sp_account_group_move(:pparent_group_id, :pgroup_id, :pcompany_id)');
            $cmm->addParam('pparent_group_id', $this->bo->parent_group_id);
            $cmm->addParam('pgroup_id', $this->bo->group_id);
            $cmm->addParam('pcompany_id', $this->bo->company_id);

            $cmm->addParam('pgroup_key', '', \app\cwf\vsla\data\SqlParamType::PARAM_OUT);
            $cmm->addParam('pgroup_path', '', \app\cwf\vsla\data\SqlParamType::PARAM_OUT);
            $cmm->addParam('pparent_key', '', \app\cwf\vsla\data\SqlParamType::PARAM_OUT);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

            $this->bo->parent_key = $cmm->getParamValue('pparent_key');
            $this->bo->group_key = $cmm->getParamValue('pgroup_key');
            $this->bo->group_path = $cmm->getParamValue('pgroup_path');
        }
    }

}
