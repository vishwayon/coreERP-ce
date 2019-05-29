<?php

namespace app\cwf\sys\entityextn;

class EntityExtnHelper {
    public static function getFields($bo_id) {
        $type = null;
        $fields = new \app\cwf\vsla\data\DataTable();
        $fields->addColumn('id', 'string', '');
        $fields->addColumn('label', 'string', '');
        $fields->addColumn('type', 'string', '');
        $fields->addColumn('control', 'string', '');
        $fields->addColumn('isOptional', 'bool', false);
        $cmd = new \app\cwf\vsla\data\SqlCommand();
        $str_cmd = 'Select * from sys.menu where bo_id=:pbo_id';
        $cmd->setCommandText($str_cmd);
        $cmd->addParam('pbo_id', $bo_id);
        $res = \app\cwf\vsla\data\DataConnect::getData($cmd);
        if(count($res->Rows()) > 0) {
            $type = (int)$res->Rows()[0]['menu_type'];
            $path = $res->Rows()[0]['link_path'];
            $modulename = strstr($path, '/form/', true);
            $modulepath = '@app/'. $modulename;
            $formView = str_replace('&formName=','', strstr($path, '&formName='));
            $coll = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($modulepath, $formView);
            $form = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($modulepath, $coll->editView);
            $keyfield = $coll->keyField;
            $params;
            if(isset($coll->newDocParam) && isset($coll->newDocParam->docType)) {
                $doctype = $coll->newDocParam->docType;
                $params = [$keyfield => -1, 'doc_type' => $doctype];
            } else {
                $params = [$keyfield => -1];
            }
            
            $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
            $helperOption->bo_id = $form->bindingBO;
            $helperOption->modulePath = $modulepath;
            $helperOption->moduleNamespace = '\\app\\'.  str_replace('/', '\\', $modulename);
            $helperOption->inParam = $params;
            $helperOption->formName = $coll->editView;

            $helper = new \app\cwf\vsla\base\RestBoHelper();
            $result = $helper->actionFetch($helperOption);
            
            $boxmlpath = $helperOption->modulePath.DIRECTORY_SEPARATOR.$helperOption->bo_id.'.xml';
            $cwframework =  simplexml_load_file($boxmlpath);
            $boxml=$cwframework->businessObject;
            $boparser=new \app\cwf\vsla\xmlbo\BoParser($boxml);
            $controlTable = $boparser->bometa->controlTable->tableName;
            
            $fieldsarray = $form->controlSection->dataBinding->items;
            foreach ($fieldsarray as $fld) {
                if($fld instanceof \app\cwf\vsla\design\CButton 
                        || $fld instanceof \app\cwf\vsla\design\NextRow
                        || $fld instanceof \app\cwf\vsla\design\Dummy) {
                    continue;
                }
                if(property_exists($fld, 'id') && ($fld->id != null || $fld->id != '')) {
                    if(strpos($fld->id,'xf_') === FALSE) {
                        $rw = $fields->NewRow();
                        $rw['id'] = $fld->id;
                        $rw['label'] = $fld->label;
                        $rw['type'] = $fld->type;
                        $rw['control'] = $fld->control;
                        $fields->addRow($rw);
                    }
                }
            }
        }
        return ['fields'=> $fields, 'type'=> $type, 'control_table'=> $controlTable];
    }
    
    public static function getExtnFields() {
        $fields = new \app\cwf\vsla\data\DataTable();
        $fields->addColumn('pre', 'string', 'xf_');
        $fields->addColumn('id', 'string', '');
        $fields->addColumn('label', 'string', '');
        $fields->addColumn('type', 'string', '');
        $fields->addColumn('control', 'string', '');
        $fields->addColumn('size', 'int', '0');
        $fields->addColumn('scale', 'int', '');
        $fields->addColumn('namedLookup', 'string', '');
        $fields->addColumn('valueMember', 'string', '');
        $fields->addColumn('displayMember', 'string', '');
        $fields->addColumn('filter', 'string', '');
        $fields->addColumn('filterEvent', 'string', '');
        $fields->addColumn('nextRow', 'bool', false);
        $fields->addColumn('dummy', 'int', 0);
        $fields->addColumn('isOptional', 'bool', false);
        return $fields;
    }
    
    public static function fromExtnFields($xFields) {
        $dt = self::getExtnFields();
        if($xFields != NULL && $xFields != '') {
            $xfield = simplexml_load_string($xFields);
            $nextrow = false;
            $dummy = 0;
            foreach ($xfield->children() as $el) {                
                if($el->getName() == 'nextRow') {
                    $nextrow = true;
                    continue;
                }
                if($el->getName() == 'dummy') {
                    $dummy = (int)$el->attributes()->size;
                    continue;
                }
                $rw = $dt->NewRow();
                if(!isset($el->attributes()->id) || (string)$el->attributes()->id != '') {
                    $rw['pre'] = 'xf_';
                    $rw['id'] = str_replace('xf_', '', (string)$el->attributes()->id);
                }
                $rw['label'] = (string)$el->attributes()->label;
                $rw['type'] = (string)$el->attributes()->type;
                $rw['control'] = (string)$el->attributes()->control;
                $rw['size'] = isset($el->attributes()->size) ? (int)$el->attributes()->size : 0;
                $rw['scale'] = isset($el->attributes()->scale) ? (int)$el->attributes()->scale : 0;
                if(isset($el->lookup)) {
                    $rw['namedLookup'] = (string)$el->lookup->namedLookup;
                    $rw['valueMember'] = (string)$el->lookup->valueMember;
                    $rw['displayMember'] = (string)$el->lookup->displayMember;
                    $rw['filter'] = (string)$el->lookup->filter;
                    $rw['filterEvent'] = (string)$el->lookup->filterEvent;
                }                
                $rw['nextRow'] = $nextrow;
                $rw['dummy'] = $dummy;
                $rw['isOptional'] = (strtolower($el->attributes()->isOptional))=='true'?true:false;
                $dt->addRow($rw);
                $nextrow = FALSE;
                $dummy = 0;
            }
        }
        return $dt;
    }
    
    public static function toExtnFields($cFields) {
        $xml = new \SimpleXMLElement('<extnFields></extnFields>');
        if(count($cFields)>0) {
            foreach ($cFields as $cfld) {
                if($cfld->nextRow){
                    $xml->addChild('nextRow');
                }
                if($cfld->dummy != 0) {
                    $dm = $xml->addChild('dummy');
                    $dm->addAttribute('size', $cfld->dummy);
                }
                $fld = $xml->addChild('field');
                $fld->addAttribute('control', $cfld->control);                
                $fld->addAttribute('id', 'xf_'.$cfld->id);
                $fld->addAttribute('label', $cfld->label);
                $fld->addAttribute('type', $cfld->type);
                $fld->addAttribute('isOptional', $cfld->isOptional?'true':'false');
                $fld->addAttribute('scale', $cfld->scale);
                if($cfld->control == 'SmartCombo') {
                    $lookup = $fld->addChild('lookup');
                    $lookup->addChild('namedLookup', $cfld->namedLookup);
                    $lookup->addChild('valueMember', $cfld->valueMember);
                    $lookup->addChild('displayMember', $cfld->displayMember);
                    $lookup->addChild('filter', $cfld->filter);
                    $lookup->addChild('filterEvent', $cfld->filterEvent);
                }                
                $fld->addAttribute('size', $cfld->size);
            }
            return $xml->asXML();
        }
        return '';
    }
    
    public static function validateFields($cFields, $bo_id) {
        $brules = [];
        $info = self::getFields($bo_id);
        $current_fields = $info['fields'];
        foreach ($current_fields->Rows() as $cfield) {
            foreach ($cFields as $cfld) {
                if($cfld->id == $cfield['id']) {
                    $brules[] = $cfld->id.' already exists in the table.';
                }
            }
        }
        return $brules;
    }
}
