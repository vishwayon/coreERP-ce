<?php

namespace app\cwf\sys\csvimport;

class ImportParser {

    public static function getImportList() {
        $miList = [];
        $filename = '../config/miList.xml';
        $xmilist = simplexml_load_file($filename);
        foreach ($xmilist->mi as $xmi) {
            $mitem = new ImportItem();
            $mitem->name = (string) $xmi->name;
            $mitem->bo = (string) $xmi->bo;
            $mitem->module = (string) $xmi->module;
            $mitem->boPath = (string) $xmi->boPath;
            $mitem->editView = (string) $xmi->editView;
            $mitem->primaryKey = (string) $xmi->primaryKey;
            $mitem->crudKey = (string) $xmi->crudKey;
            if (isset($xmi->forBranch)) {
                $mitem->forBranch = TRUE;
            } else {
                $mitem->forBranch = FALSE;
            }
            $miList[] = $mitem;
        }
        return $miList;
    }

    public static function getMasterInfo($mastername) {
        $miList = self::getImportList();
        $masterInfo = new ImportItem();
        foreach ($miList as $mItem) {
            if ($mItem->name == $mastername) {
                $masterInfo = $mItem;
                break;
            }
        }
        return $masterInfo;
    }

    public static function getFieldList($mastername) {
        return ImportHelper::getFieldList(self::getMasterInfo($mastername));
    }

    public static function getImportTemplate($mastername) {
        $fieldList = self::getFieldList($mastername);
        $fieldHeader = [];
        foreach ($fieldList as $field) {
            $fieldHeader[] = $field->cname;
        }
        return $fieldHeader;
    }

}
