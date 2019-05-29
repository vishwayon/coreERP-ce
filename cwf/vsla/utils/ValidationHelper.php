<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\vsla\utils;

/**
 * Description of ValidationHelper
 *
 * @author girish
 */
class ValidationHelper {

    /**
     * Validates the BO based on properties mentioned in the form xml
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\ui\viewparser $formInfo
     */
    public static function validateUsingForm(\app\cwf\vsla\xmlbo\BoBase $bo, \app\cwf\vsla\ui\viewparser $formInfo) {
        //foreach($formInfo->sections as $sec) {
        $sec = $formInfo->section;
        if ($sec->sectiontype == 'ControlSection' && $sec->editMode == 'Edit') {
            foreach ($sec->fields as $fld) {
                if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                    continue;
                }
                if ($fld->optional) {
                    continue;
                }
                // This is written in 3 lines to enable easier debugging
                $fld_id = $fld->id;
                $val = $bo->$fld_id;
                switch ($fld->type) {
                    case 'Int64':
                        if (!is_numeric($val) or is_null($val)) {
                            $bo->addBRule(self::labelresolve($fld) . ' is required');
                        } else if ($val < 0) {
                            $bo->addBRule(self::labelresolve($fld) . ' is required');
                        }
                        break;
                    case 'String':
                        if (strlen($val) == 0) {
                            $bo->addBRule(self::labelresolve($fld) . ' is required');
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        //}
    }

    private static function labelresolve($fld) {
        if ($fld->label != NULL && $fld->label != '') {
            return $fld->label;
        } else if ($fld->id != NULL && $fld->id != '') {
            return $fld->id;
        }
    }

}
