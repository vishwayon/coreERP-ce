<?php

namespace app\cwf\fwShell\models;

use app\cwf\vsla\data\SqlCommand;
use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\AccessLevels;

require_once 'MenuTree.php';

class UserAccessMenuItem {

    public $menu_id, $parent_menu_id, $menu_name, $menu_text,
            $menu_type, $allow_delete = FALSE, $allow_unpost = FALSE, $allow_audit_trail = FALSE, $children, $options;
    public $selected = false;

}

class UserAccess {

    public $menus, $menutree, $user_name, $menu_items, $branch_id, $user_id, $dt_al;

    public function __construct($params) {
        $paramz = is_array($params) ? $params : json_decode($params);
        foreach ($paramz as $key => $value) {
            if ($key === 'user_id') {
                $this->user_id = $value;
            }
        }
        $this->init();
    }

    private function init() {
        $cmm2 = new SqlCommand();
        $cmmtext2 = 'select user_id, full_user_name from sys.user where user_id=:puser_id';
        $cmm2->setCommandText($cmmtext2);
        $cmm2->addParam('puser_id', $this->user_id);
        $dt = DataConnect::getData($cmm2);

        if (count($dt->Rows()) > 0) {
            $this->user_name = (string) $dt->Rows()[0]['full_user_name'];
        }
    }

    public function getBranchAccess($branch_id) {
        $cmm = new SqlCommand();
        $cmmtext = 'select menu_id, parent_menu_id, menu_name, menu_text, menu_type, false as selected'
                . ', false as allow_delete, false as allow_unpost, false as allow_audit_trail from sys.menu where is_hidden=false';
        $cmm->setCommandText($cmmtext);
        $this->menus = DataConnect::getData($cmm, DataConnect::COMPANY_DB);


        $cmm = new SqlCommand();
        $cmmtext = 'SELECT user_access_level_id, user_id, branch_id, menu_id, allow_delete, 
                    allow_unpost, allow_audit_trail FROM sys.user_access_level
                    WHERE user_id=:puser_id and branch_id=:pbranch_id';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('puser_id', $this->user_id);
        $cmm->addParam('pbranch_id', $branch_id);
        $this->dt_al = DataConnect::getData($cmm, DataConnect::COMPANY_DB);

        if (count($this->dt_al->Rows()) > 0) {
            foreach ($this->menus->Rows() as &$refrwmenu) {
                foreach ($this->dt_al->Rows() as $rwal) {
                    if ((string) $refrwmenu['menu_id'] === (string) $rwal['menu_id']) {
                        $refrwmenu['selected'] = true;
                        $refrwmenu['allow_delete'] = (bool) ($rwal['allow_delete']);
                        $refrwmenu['allow_unpost'] = (bool) ($rwal['allow_unpost']);
                        $refrwmenu['allow_audit_trail'] = (bool) ($rwal['allow_audit_trail']);
                    }
                }
            }
        }
        $this->menu_items = array();
        $tmp = NULL;
        $this->setMenu('-1', $tmp);
    }

    private function setMenu($parentkey, &$parent) {
        $this->menutree .= '<ul>';
        $temp = NULL;
        foreach ($this->menus->Rows() as $rw) {
            if ((string) $rw['parent_menu_id'] == $parentkey) {
                $itm = new UserAccessMenuItem();
                $itm->selected = (bool) $rw['selected'];
                $itm->menu_id = (string) $rw['menu_id'];
                $itm->parent_menu_id = (string) $rw['parent_menu_id'];
                $itm->menu_name = (string) $rw['menu_name'];
                $itm->menu_text = (string) $rw['menu_text'];
                $itm->menu_type = (string) $rw['menu_type'];
                if ((int) $rw['menu_type'] == 0) {
                    $itm->allow_delete = NULL;
                    $itm->allow_unpost = NULL;
                    $itm->allow_audit_trail = NULL;
                } else if ((int) $rw['menu_type'] == 1) {
                    if (isset($rw['allow_delete'])) {
                        $itm->allow_delete = (bool) $rw['allow_delete'];
                    }
                    if (isset($rw['allow_unpost'])) {
                        $itm->allow_unpost = (bool) $rw['allow_unpost'];
                    }
                    if (isset($rw['allow_audit_trail'])) {
                        $itm->allow_audit_trail = (bool) $rw['allow_audit_trail'];
                    }
                } else if ((int) $rw['menu_type'] == 2) {
                    if (isset($rw['allow_delete'])) {
                        $itm->allow_delete = (bool) $rw['allow_delete'];
                    }
                    if (isset($rw['allow_audit_trail'])) {
                        $itm->allow_audit_trail = (bool) $rw['allow_audit_trail'];
                    }
                }
                $itm->children = array();
                if ($temp === NULL) {
                    $temp = '<ul>';
                }
                $temp .= <<<menuitem
                        <li id="{$rw['menu_name']}" cmenutype="{$rw['menu_type']}">
                            <input type="checkbox"><span>{$rw['menu_text']}</span>
menuitem;
                $this->setmenu((string) $rw['menu_id'], $itm);
                if ($itm->menu_type <= 2) {
                    if ($parent == NULL) {
                        array_push($this->menu_items, $itm);
                    } else {
                        array_push($parent->children, $itm);
                    }
                }
            }
        }
        if ($temp !== NULL) {
            $temp = '</ul>';
            $this->menutree .= $temp;
        }
    }

}
