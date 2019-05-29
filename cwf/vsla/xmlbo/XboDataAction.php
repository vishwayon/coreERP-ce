<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\vsla\xmlbo;

class XboDataAction {
    
    public static function map_json_field(\app\cwf\vsla\data\JsonField $boField, string $jsonData) {
        $jsonNode = json_decode($jsonData);
        $mdata = $boField->get_metaInfo();
        if($mdata != null) {
            foreach($mdata->jfields as $jfield) {
                self::map_jfield($jfield, $boField->Value(), $jsonNode);
            }
            foreach($mdata->jobjects as $jobject) {
                self::map_jobject($jobject, $boField->Value(), $jsonNode);
            }
        }
    }
    
    private static function map_jfield(JFieldMeta $fieldMeta, $parentMemb, $jsonNode) {
        $fld_id =  $fieldMeta->name;
        if (isset($jsonNode->$fld_id)) {
            $parentMemb->$fld_id = $jsonNode->$fld_id;
        }
    }
    
    private static function map_jobject(JObjectMeta $fieldMeta, $parentMemb, $jsonNode) {
        if($fieldMeta->type == JObjectMeta::SIMPLE_TYPE) {
            $fname = $fieldMeta->name;
            if(isset($jsonNode->$fname)) {      
                foreach($fieldMeta->jfields as $jfield) {
                    self::map_jfield($jfield, $parentMemb->$fname, $jsonNode->$fname);
                }
                foreach($fieldMeta->jobjects as $jobject) {
                    self::map_jobject($jobject, $parentMemb->$fname, $jsonNode->$fname);
                }
            }
        } else if($fieldMeta->type == JObjectMeta::ARRAY_TYPE) {
            $fname = $fieldMeta->name;
            $typ_info = "__type__".$fname;
            $item = $parentMemb->$typ_info;
            $cfieldMeta = clone $fieldMeta;
            $cfieldMeta->type = JObjectMeta::SIMPLE_TYPE;
            if(isset($jsonNode->$fname)) {
                foreach($jsonNode->$fname as $row) {
                    $jrow = new \stdClass();
                    $jrow->$fname = $row;
                    $jitem = new \stdClass();
                    $jitem->$fname = clone $item;
                    self::map_jobject($cfieldMeta, $jitem, $jrow);
                    $parentMemb->$fname[] = $jitem->$fname;
                }
            }
        }
    }
}