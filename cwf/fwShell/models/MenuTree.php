<?php

namespace app\cwf\fwShell\models;

use app\cwf\vsla\data\SqlCommand;
use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\AccessLevels;

class MenuTypes {

    const DOCUMENT = 1;
    const MASTER = 2;
    const REPORT = 3;
    const UIFORM = 4;

}

class AccessLevel {

    public $val = 0;
    public $name;

}

class MenuItem {

    public $menu_id, $parent_menu_id, $menu_name, $menu_text, $stages = [], $is_staged = FALSE, $doc_stages = [],
            $link_path = '', $menu_type, $access_level, $children, $options, $access_levels;
    public $selected = false;

}

class MenuTree implements \app\cwf\vsla\xmlbo\CustomBase {

    public $menus, $menutree, $role_id, $role_name, $menu_items, $branch_id, $user_id, $dt_al;
    public $docAccess = [AccessLevels::NOACCESS, AccessLevels::READONLY, AccessLevels::DATAENTRY, AccessLevels::AUTHORIZE];
    public $masterAccess = [AccessLevels::NOACCESS, AccessLevels::READONLY, AccessLevels::DATAENTRY];
    public $reportAccess = [AccessLevels::NOACCESS, AccessLevels::READONLY, AccessLevels::CONSOLIDATED];
    public $uiformAccess = [AccessLevels::NOACCESS, AccessLevels::READONLY];
    public $docStageAccess = [AccessLevels::NOACCESS, AccessLevels::READONLY, AccessLevels::DATAENTRY];

    public function __construct($params) {
        $paramz = is_array($params) ? $params : json_decode($params);
        foreach ($paramz as $key => $value) {
            if ($key === 'role_id') {
                $this->role_id = $value;
            }
        }
        $this->init();
    }

    private function init() {

        $cmm2 = new SqlCommand();
        $cmmtext2 = 'select role_id,role_name from sys.role where role_id=:prole_id';
        $cmm2->setCommandText($cmmtext2);
        $cmm2->addParam('prole_id', $this->role_id);
        $dt = DataConnect::getData($cmm2);

        if (count($dt->Rows()) > 0) {
            $this->role_name = (string) $dt->Rows()[0]['role_name'];
        }
    }

    public function getMenuAccess() {
        $cmm = new SqlCommand();
        $cmmtext = 'select menu_id, parent_menu_id, menu_name, menu_text, menu_type, is_staged,'
                . ' link_path, 0 as access_level,false as selected, \'{}\' as doc_stages from sys.menu where is_hidden=false';
        $cmm->setCommandText($cmmtext);
        $this->menus = DataConnect::getData($cmm, DataConnect::COMPANY_DB);


        $cmm = new SqlCommand();
        $cmmtext = 'select role_id, menu_id, en_access_level, 1 as menu_type, array_to_json(doc_stages) as ds from sys.role_access_level
            where role_id=:prole_id and en_access_level>0';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('prole_id', $this->role_id);
        $this->dt_al = DataConnect::getData($cmm, DataConnect::COMPANY_DB);

        if (count($this->dt_al->Rows()) > 0) {
            foreach ($this->menus->Rows() as &$refrwmenu) {
                foreach ($this->dt_al->Rows() as $rwal) {
                    if ((string) $refrwmenu['menu_id'] === (string) $rwal['menu_id']) {
                        $refrwmenu['selected'] = true;
                        $refrwmenu['access_level'] = (int) $rwal['en_access_level'];
                        $refrwmenu['doc_stages'] = $rwal['ds'];
                    }
                }
            }
        }
        $this->menu_items = array();
        $tmp = NULL;
        $this->setMenu('-1', $tmp);
    }
    
    public function getBranchAccess($branch_id) {
        $cmm = new SqlCommand();
        $cmmtext = 'select menu_id, parent_menu_id, menu_name, menu_text, menu_type, is_staged,'
                . ' link_path, 0 as access_level,false as selected, \'{}\' as doc_stages from sys.menu where is_hidden=false';
        $cmm->setCommandText($cmmtext);
        $this->menus = DataConnect::getData($cmm, DataConnect::COMPANY_DB);


        $cmm = new SqlCommand();
        $cmmtext = 'select * from (select role_id, menu_id, en_access_level, 1 as menu_type, array_to_json(doc_stages) as ds from sys.role_access_level_doc
            where role_id=:prole_id and branch_id=:pbranch_id
            union all
            select role_id, menu_id, en_access_level_master, 2, \'{}\' as ds from sys.role_access_level_master
            where role_id=:prole_id
            union all
            select role_id, menu_id, en_access_level_report, 3, \'{}\' as ds from sys.role_access_level_report
            where role_id=:prole_id and branch_id=:pbranch_id
            union all
            select role_id, menu_id, en_access_level_ui_form, 4, \'{}\' as ds from sys.role_access_level_ui_form
            where role_id=:prole_id and branch_id=:pbranch_id) a where a.en_access_level>0';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('prole_id', $this->role_id);
        $cmm->addParam('pbranch_id', $branch_id);
        $this->dt_al = DataConnect::getData($cmm, DataConnect::COMPANY_DB);

        if (count($this->dt_al->Rows()) > 0) {
            foreach ($this->menus->Rows() as &$refrwmenu) {
                foreach ($this->dt_al->Rows() as $rwal) {
                    if ((string) $refrwmenu['menu_id'] === (string) $rwal['menu_id']) {
                        $refrwmenu['selected'] = true;
                        $refrwmenu['access_level'] = (int) $rwal['en_access_level'];
                        $refrwmenu['doc_stages'] = $rwal['ds'];
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
                $itm = new MenuItem();
                $itm->selected = (bool) $rw['selected'];
                $itm->menu_id = (string) $rw['menu_id'];
                $itm->parent_menu_id = (string) $rw['parent_menu_id'];
                $itm->menu_name = (string) $rw['menu_name'];
                $itm->menu_text = (string) $rw['menu_text'];
                $itm->menu_type = (string) $rw['menu_type'];
                $itm->link_path = (string) $rw['link_path'];
                if (isset($rw['access_level'])) {
                    $itm->access_level = (int) $rw['access_level'];
                } else {
                    $itm->access_level = AccessLevels::NOACCESS;
                }
                if($itm->menu_text == 'Estimate'){
                    $var =1;
                }
                $itm->access_levels = $this->getOptionArray($itm->menu_type);
                if (isset($rw['is_staged']) && $itm->menu_type == 1) {
                    $itm->is_staged = (bool) $rw['is_staged'];
                    if ($itm->is_staged) {
                        $this->getStages($itm);
                        $itm->access_levels = $this->getOptionArray(99);
                        $tmpstg = \json_decode((string) $rw['doc_stages']);
                        if (is_array($tmpstg)) {
                            $itm->doc_stages = $tmpstg;
                        } else {
                            $itm->doc_stages = [];
                        }
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
                if ($parent == NULL) {
                    array_push($this->menu_items, $itm);
                } else {
                    array_push($parent->children, $itm);
                }
            }
        }
        if ($temp !== NULL) {
            $temp = '</ul>';
            $this->menutree .= $temp;
        }
    }

    public function getOptions($objType, $name) {
        $res = NULL;
        $opts = '';
        switch ($objType) {
            case 1:
                $res = $this->docAccess;
                break;
            case 2:
                $res = $this->masterAccess;
                break;
            case 3:
                $res = $this->reportAccess;
                break;
            case 4:
                $res = $this->uiformAccess;
                break;
            case 99:
                $res = $this->docStageAccess;
                break;
        }
        if ($res !== NULL) {
            $opts = '<span style="margin-right:40%;float:right;">';
            foreach ($res as $opt) {
                $opts .= '&nbsp&nbsp<input type="radio" name="' . $name
                        . '" value="' . $opt . '" data-bind="checked: access_level"/>' . $this->getName($opt);
            }
            $opts .= '</span>';
        }
        return $opts;
    }

    public function getOptionArray($objType) {
        $res = NULL;
        $opts = array();
        switch ($objType) {
            case 1:
                $res = $this->docAccess;
                break;
            case 2:
                $res = $this->masterAccess;
                break;
            case 3:
                $res = $this->reportAccess;
                break;
            case 4:
                $res = $this->uiformAccess;
                break;
            case 99:
                $res = $this->docStageAccess;
                break;
        }
        if ($res !== NULL) {
            foreach ($res as $opt) {
                $temp = new AccessLevel();
                if ($objType == 3 && $opt == 1) {
                    $temp->name = 'View';
                    $temp->val = 2;
                } else if ($objType == 4 && $opt == 1) {
                    $temp->name = 'Read/Edit';
                    $temp->val = $opt;
                } else {
                    $temp->name = $this->getName($opt);
                    $temp->val = $opt;
                }
                array_push($opts, $temp);
            }
        }
        return $opts;
    }

    private function getName($val) {
        switch ($val) {
            case AccessLevels::NOACCESS:
                return 'No Access';
            case AccessLevels::READONLY:
                return 'Read Only';
            case AccessLevels::DATAENTRY:
                return 'Read/Edit';
            case AccessLevels::AUTHORIZE:
                return 'Authorize';
            case AccessLevels::CONSOLIDATED:
                return 'Consolidated';
            default:
                return '';
        }
    }

    public function fetch() {
        
    }

    public function save() {
        
    }

    public function render() {
        $renderstr = <<<str
<div>
    <div class="row">
        <h3 class="col-md-5">Role Access Level</h3>
        <div class="col-md-7">
            <button id="cmdclose" class="btn btn-info formoptions" name="close-button" style="background-color:lightgrey;border-color:lightgrey;color:black;">
                <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Close
            </button>
            <button id="cmdsave" class="btn btn-primary formoptions" style="" type="submit" name="save-button">
                <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Save
            </button>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-4 field-role_name required">
            <label class="control-label" for="role_name">Role Name</label>
            <input id="role_name" class=" form-control " readonly type="TextBox" data-validation-length="1-50" data-bind="value: role_name" data-validation="length" data-validation-error-msg="Role Name is required. max(50)" maxlength="50" name="role_name">
        </div>
    
        <div class="form-group col-md-4 field-branch_id required">
            <label class="control-label" for="branch_id">Branch</label>
            <input id="branch_id" class=" smartcombo form-control " type="SmartCombo" data-bind="value: branch_id" data-validation="required" data-filter="" data-valuemember="branch_id" data-displaymember="branch_name" data-namedlookup="../cwf/sys/lookups/Branch.xml" data-validations="number" data-validation-error-msg="Please select Branch" name="branch_id" tabindex="-1" title="Branch" style="display: none;">
        </div>
    </div>
            <script type="text/html" id="tree-node">
                <li data-bind="attr: { cmenutype: menu_type, id: menu_name }">
                    <input data-bind="attr: {id: menu_name()+'-chk' }, checked: selected" type="checkbox">
                        <span data-bind="text: menu_text"></span>
                        <span data-bind="template: {name: 'access-levels', foreach: access_levels, as: 'opt'}, visible: selected"></span>
                        <ul data-bind="template: {name: 'tree-node', foreach: children, as: 'chld'}"></ul>
                </li>
            </script>
            <script type="text/html" id="access-levels">
                &nbsp&nbsp<input type="radio" data-bind="attr: { name: chld.menu_name},checked: chld.access_level,value: val"/><span data-bind="text: name"></span>
            </script>
  
                <div class="row">
        <div class="form-group col-md-12">
            <label class="control-label" for="menu_items">Access Levels</label>
                
            <div id='divtree'>
            <ul data-bind="template: {name: 'tree-node', foreach: menu_items}"></ul>
            </div>
        </div>
</div>
str;
        return $renderstr;
    }

    private function getStages(MenuItem &$itm) {
        $stages = [];
        if ($itm->link_path != '') {
            $posn = strpos($itm->link_path, '&');
            $modulepath = substr($itm->link_path, 0, $posn);
            $module = str_replace('form/collection', '', $modulepath);
            $cview = substr($itm->link_path, $posn);
            $collectionview = str_replace('&formName=', '', $cview);
            $collectionpath = $module . $collectionview;
            $collpath = \yii::getAlias('@app/' . $collectionpath . '.xml');
            $xcoll = simplexml_load_file($collpath);
            if (isset($xcoll->collectionView)) {
                $editView = (string) $xcoll->collectionView->attributes()->editView;
                $editviewpath = \yii::getAlias('@app/' . $module . $editView . '.xml');
                $xeditview = simplexml_load_file($editviewpath);
                if (isset($xeditview->formView)) {
                    $bo = (string) $xeditview->formView->attributes()->bindingBO;
                    $bopath = \yii::getAlias('@app/' . $module . $bo . '.xml');
                    $xbo = simplexml_load_file($bopath);
                    if (isset($xbo->businessObject->docStageInfo)) {
                        $stage_count = count($xbo->businessObject->docStageInfo->children());
                        for ($cnt = 0; $cnt < ($stage_count - 1); $cnt++) {
                            $stg = (object) array(
                                        'step' => ($cnt + 1),
                                        'val' => (string) $xbo->businessObject->docStageInfo->children()[$cnt]->attributes()->id,
                                        'desc' => (string) $xbo->businessObject->docStageInfo->children()[$cnt]->attributes()->desc,
                            );
                            $stages[] = $stg;
                        }
                    }
                }
            }
        }
        $itm->stages = $stages;
    }

}
