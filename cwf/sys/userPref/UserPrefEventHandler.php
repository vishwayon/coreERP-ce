<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userPref;

/**
 * Description of UserEventHandler
 *
 * @author Priyanka
 */
class UserPrefEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        // Create temp teble for doc_group Temp
        $this->bo->bo_temp = new \app\cwf\vsla\data\DataTable();

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->bo_temp->addColumn('bo_id', $phpType, $default, 500, 0, false);
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('boolean');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->bo_temp->addColumn('select', $phpType, $default, 0, 4, false);
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bigint');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->bo_temp->addColumn('menu_text', $phpType, $default, 0, 250, false);

        foreach ($this->bo->bo_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('bo_temp', $cols);

        // Fill bo_temp table
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select d.bo_id, d.menu_text, max(b.en_access_level) as max_acc_lvl, false as select
                                From sys.role a
                                Inner Join sys.role_access_level b on a.role_id = b.role_id
                                Inner Join sys.user_branch_role c on a.role_id = c.role_id
                                Inner Join sys.menu d on b.menu_id = d.menu_id
                                where b.en_access_level > 1 and c.user_id = {user_id} and d.is_staged = true
                                group by d.bo_id, d.menu_text, d.menu_key
                                order by d.menu_key");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($dt->Rows() as $row) {
            $newRow = $this->bo->bo_temp->NewRow();
            $newRow['bo_id'] = $row['bo_id'];
            $newRow['select'] = $row['select'];
            $this->bo->bo_temp->AddRow($newRow);
        }

        if ($this->bo->user_pref_id == -1) {
            $this->bo->user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
        } else {
            $str = str_replace('{', '', $this->bo->pref_info->Value()->wf_auto_adv);
            $dg_ids = str_replace('}', '', $str);
            $doc_grp_ids = explode(',', $dg_ids);
            $a = $doc_grp_ids;
            foreach ($doc_grp_ids as $dr) {
                foreach ($this->bo->bo_temp->Rows() as &$ref_dg_row) {
                    if ($dr == $ref_dg_row['bo_id']) {
                        $ref_dg_row['select'] = true;
                    }
                }
            }
        }
    }

    public function onSave($cn, $tablename) {
        
    }

}
