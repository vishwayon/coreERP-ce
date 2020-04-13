<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\utils;

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

    // regex verification link https://developer.gst.gov.in/apiportal/taxpayer/returns
    private static $regexes = [
        'normal' => "/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Zz1-9A-Ja-j]{1}[0-9a-zA-Z]{1}$/",
        'UNBODY' => "/^[0-9]{4}[A-Z]{3}[0-9]{5}[UO]{1}[N][A-Z0-9]{1}$/",
        'GOVT_DEPTID' => "/^[0-9]{2}[a-zA-Z]{4}[0-9]{5}[a-zA-Z]{1}[0-9]{1}[Z]{1}[0-9]{1}$/",
        'NRI_ID' => "/^[0-9]{4}[a-zA-Z]{3}[0-9]{5}[N][R][0-9a-zA-Z]{1}$/",
        'TDS' => "/^[0-9]{2}[a-zA-Z]{4}[a-zA-Z0-9]{1}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[D]{1}[0-9a-zA-Z]{1}$/",
        'TCS' => "/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[C]{1}[0-9a-zA-Z]{1}$/",
        'OIDAR' => "/^[9][9][0-9]{2}[a-zA-Z]{3}[0-9]{5}[O][S][0-9a-zA-Z]{1}$/"
    ];

    public static function validateGSTIN($gstin) {
        foreach (self::$regexes as $k => $regex) {
            if (preg_match($regex, $gstin)) {
                return true;
            }
        }
        return false;
    }
    
    public static function validateDuration($duration) {
        $re = '/P(?:(?:\d+D|\d+M(?:\d+D)?|\d+Y(?:\d+M(?:\d+D)?)?)(?:T(?:\d+H(?:\d+M(?:\d+S)?)?|\d+M(?:\d+S)?|\d+S))?|T(?:\d+H(?:\d+M(?:\d+S)?)?|\d+M(?:\d+S)?|\d+S)|\d+W)/';
        if (!preg_match($re, $duration)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

}
