<?php

namespace app\cwf\vsla\ui;

/**
 * This class provides a prelookup text for each SmartCombo proposed to be
 * rendered as part of view. The result can be used during select init
 * @author girish
 */
class PreLookup {

    //put your code here
    private $bo;
    private $xformView;
    private $lookupResults = array();

    public function init($bo, $xView) {
        $this->bo = $bo;
        $this->xformView = $xView->formView;
    }

    public function getPreLookupData() {
        $this->parseFromView();
        return $this->lookupResults;
    }

    private function parseFromView() {
        if (isset($this->xformView->controlSection) && isset($this->xformView->controlSection->dataBinding)) {
            // Get control section lookup bindings
            $dataBind = $this->xformView->controlSection->dataBinding;
            // Since this is the control section. It is always bound to the base bo.
            $this->parseDataBindFields($this->bo, $dataBind);
            // Get tran section lookup bindings
            foreach ($dataBind->children() as $nodeName => $nodeDef) {
                if ($nodeName == 'tranSection' && isset($nodeDef->dataBinding)) {
                    $tranbinding = $nodeDef->dataBinding;
                    if (isset($tranbinding->attributes()->dataProperty)) {
                        $dataProperty = $tranbinding->attributes()->dataProperty;
                        $bindingContext = $this->bo->$dataProperty;
                        if ($bindingContext instanceof \app\cwf\vsla\data\DataTable) {
                            $this->parseFromTranDataBind($bindingContext, $tranbinding);
                        }
                    }
                }
            }
        }
    }

    private function parseFromTranDataBind(\app\cwf\vsla\data\DataTable $bindingContext, $dataBind) {
        // First set values for all lookup fields
        foreach ($bindingContext->Rows() as $crow) {
            $this->parseDataBindFields($crow, $dataBind);
            // search for child trans if any
            foreach ($dataBind->children() as $nodeName => $nodeDef) {
                if ($nodeName == 'tranSection' && isset($nodeDef->dataBinding)) {
                    $tranbinding = $nodeDef->dataBinding;
                    if (isset($tranbinding->attributes()->dataProperty)) {
                        $dataProperty = (string) $tranbinding->attributes()->dataProperty;
                        $childBinding = $crow[$dataProperty];
                        $this->parseFromTranDataBind($childBinding, $tranbinding);
                    }
                }
            }
        }
    }

    private function parseDataBindFields($bindingContext, $dataBind) {
        foreach ($dataBind->children() as $nodeName => $nodeDef) {
            if ($nodeName == 'field') {
                $fieldDef = $nodeDef;
                if (isset($fieldDef->attributes()->control)) {
                    if ((string) $fieldDef->attributes()->control == 'SmartCombo') {
                        $field = (string) $fieldDef->attributes()->id;
                        $valueid = -1;
                        if (is_array($bindingContext)) {
                            $valueid = $bindingContext[$field];
                        } else {
                            if (strpos($field, ".") > 0) {
                                // This is a json field that has nested values
                                $jpath = explode(".", $field);
                                $jfield = $bindingContext->{$jpath[0]};
                                if ($jfield instanceof \app\cwf\vsla\data\JsonField) {
                                    $parentbc = $jfield->Value();
                                    for ($i = 1; $i < count($jpath) - 1; $i++) {
                                        $parentbc = $parentbc->{$jpath[$i]};
                                    }
                                    $valueid = $parentbc->{$jpath[count($jpath) - 1]};
                                }
                            } else {
                                $valueid = $bindingContext->$field;
                            }
                        }
                        if ($valueid != -1) {
                            $result = $this->getLookupResult($fieldDef, $valueid);
                            if (isset($result)) {
                                array_push($this->lookupResults, $result);
                            }
                        }
                    }
                }
            }
        }
    }

    private function getLookupResult($fieldDef, $valueid) {
        $result = new PreLookupResult();
        $result->lookupid = str_replace('../', '@app/', (string) $fieldDef->lookup->namedLookup) . "|" . (string) $fieldDef->lookup->displayMember . "|" . (string) $fieldDef->lookup->valueMember . "|" . $fieldDef->attributes()->id;
        $result->valueid = $valueid;
        // First try to find in existing array
        foreach ($this->lookupResults as $oldr) {
            if ($oldr->lookupid == $result->lookupid && $oldr->valueid == $result->valueid) {
                // Already in collection, nothing required
                return null;
            }
        }
        $dispText = \app\cwf\vsla\utils\LookupHelper::GetLookupText(
                        (string) $fieldDef->lookup->namedLookup, (string) $fieldDef->lookup->displayMember, (string) $fieldDef->lookup->valueMember, $valueid);
        if ($dispText != '' && isset($dispText)) {
            $result->dispText = $dispText;
        } else {
            return null;
        }
        return $result;
    }

}

class PreLookupResult {

    public $lookupid = '';
    public $valueid = -1;
    public $dispText = '';

}
