<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\role;

/**
 * Description of RoleValidator
 *
 * @author dev
 */
class RoleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateRoleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {

        // Validate duplicate role
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select role_name from sys.role where role_name ilike :prole_name and role_id!=:prole_id');
        $cmm->addParam('prole_name', $this->bo->role_name);
        $cmm->addParam('prole_id', $this->bo->role_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Role name already exists. Duplicate role name not allowed.');
        }
        $this->validateRoleAccessLevelEditForm();
    }

    public function validateRoleAccessLevelEditForm() {

        if (count($this->bo->menu_items) == 0) {
            $this->bo->addBRule('Get Access Levels to save.');
        }

        if (count($this->bo->getBRules()) == 0) {
            // conduct default form validations
            $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
            $this->validateUsingForm($this->bo, $formView);

            $this->setMenu('-1', $this->bo->menu_items);

            // Delete Rows with No Access
            for ($i = 0; $i < count($this->bo->role_access_level->Rows()); $i++) {
                if ($this->bo->role_access_level->Rows()[$i]['en_access_level'] == 0) {
                    $this->bo->role_access_level->removeRow($i);
                }
            }
            $this->validateRoleAccessLevel();
        }
    }

    private function updateMenuItems() {
        
    }

    private function setMenu($parentkey, $parent) {
        foreach ($parent as $rw) {
            if ($rw->parent_menu_id == $parentkey) {
                if (count($rw->children) == 0) {
                    if ($rw->selected == true) {
                        $this->updateMenuAccess($rw->menu_id, $rw->access_level, $rw->doc_stages);
                    } else {
                        $this->updateMenuAccess($rw->menu_id, 0, $rw->doc_stages);
                    }
                } else {
                    $this->setmenu($rw->menu_id, $rw->children);
                }
            }
        }
    }

    private function updateMenuAccess($menu_id, $access_level, $doc_stages) {
        $found = FALSE;
        if ($access_level != \app\cwf\vsla\security\AccessLevels::DATAENTRY) {
            $doc_stages = [];
        }
        foreach ($this->bo->role_access_level->Rows() as &$refdocrow) {
            if ($refdocrow['menu_id'] == $menu_id) {
                $refdocrow['en_access_level'] = $access_level;
                $refdocrow['doc_stages'] = $doc_stages;
                $found = true;
                break;
            }
        }

        if ($found == false && $access_level != 0) {
            $newRow = $this->bo->role_access_level->NewRow();
            $newRow['role_access_level_doc_id'] = '';
            $newRow['role_id'] = -1;
            $newRow['menu_id'] = $menu_id;
            $newRow['en_access_level'] = $access_level;
            $newRow['doc_stages'] = $doc_stages;
            $this->bo->role_access_level->AddRow($newRow);
        }
    }

    private function validateRoleAccessLevel() {

        foreach ($this->bo->role_access_level->Rows() as $row) {
            if ($row['en_access_level'] == -1) {
                $this->bo->addBRule('Please select access level for Document.');
            }
        }
    }

}
