<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userAccessLevel;

/**
 * Description of UserAccessLevelEventHandler
 *
 * @author Priyanka
 */
class UserAccessLevelEventHandler extends \app\cwf\sys\user\UserEventHandler {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo['menuItems'] = NULL;
        $this->bo['menu_items'] = NULL;
        $this->bo['branch_id'] = -1;
        $this->bo['is_admin'] = FALSE;
        $this->GetUserAccessForBranches();
    }

    public function onSave($cn, $tablename) {
        // Do not call base method here
        // This is just an anchoring object and the base table 
        // should not be affected
    }

    private function GetUserAccessForBranches() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select branch_id,branch_code,branch_name from sys.branch');
        $br = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $params = array();
        $params['user_id'] = (int) $this->bo['user_id'];
        $mnutree = new \app\cwf\fwShell\models\UserAccess($params);
        $menuitems = array();
        foreach ($br->Rows() as $rw) {
            $mnutree->getBranchAccess((int) $rw['branch_id']);
            $menuitems[(int) $rw['branch_id']] = $mnutree->menu_items;
        }
        $this->bo['menuItems'] = $menuitems;
    }

    public function afterSave($cn) {
        parent::afterSave($cn);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From sys.user_access_level Where user_id = :puser_id And branch_id = :pbranch_id');
        $cmm->addParam('puser_id', $this->bo->user_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        $this->saveAccess(-1, $this->bo->menu_items);
    }

    private function saveAccess($parentkey, $parent) {
        $temp = NULL;
        foreach ($parent as $rw) {
            if ($rw->parent_menu_id == $parentkey) {
                if (count($rw->children) == 0) {
                    if ($rw->selected == true && ($rw->allow_unpost == TRUE || $rw->allow_delete == TRUE || $rw->allow_audit_trail == TRUE)) {
                        $aid = (string) $this->bo->user_id . ':' . (string) $this->bo->branch_id . ':' . (string) $rw->menu_id;
                        $cmm = new \app\cwf\vsla\data\SqlCommand();
                        $cmm->setCommandText('INSERT INTO sys.user_access_level(
                                                user_access_level_id, user_id, branch_id, menu_id, allow_delete, 
                                                allow_unpost, allow_audit_trail)
                                                VALUES (:puser_access_level_id, :puser_id, :pbranch_id, :pmenu_id, :pallow_delete, 
                                                :pallow_unpost, :pallow_audit_trail);');
                        $cmm->addParam('puser_access_level_id', $aid);
                        $cmm->addParam('puser_id', $this->bo->user_id);
                        $cmm->addParam('pbranch_id', $this->bo->branch_id);
                        $cmm->addParam('pmenu_id', $rw->menu_id);
                        $cmm->addParam('pallow_delete', $rw->allow_delete);
                        $cmm->addParam('pallow_unpost', $rw->allow_unpost);
                        $cmm->addParam('pallow_audit_trail', $rw->allow_audit_trail);
                        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
                    }
                } else {
                    $this->saveAccess($rw->menu_id, $rw->children);
                }
            }
        }
    }

    public function onDelete($cn, $tablename) {
        parent::onDelete($cn, $tablename);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From sys.user_access_level Where user_id = :puser_id');
        $cmm->addParam('puser_id', $this->bo->user_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
    }

}
