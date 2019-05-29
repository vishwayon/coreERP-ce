<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\role;

/**
 * Description of RoleEventHandler
 *
 * @author dev
 */
class RoleEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo['menu_items'] = NULL;
        $this->GetMenuTree();
    }

    private function GetMenuTree() {
        $params = array();
        $params['role_id'] = (int) $this->bo['role_id'];
        $mnutree = new \app\cwf\fwShell\models\MenuTree($params);
        $mnutree->getMenuAccess();
        $menuitems = $mnutree->menu_items;
        $this->bo['menuItems'] = $menuitems;
    }

}
