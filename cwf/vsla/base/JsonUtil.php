<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of JsonUtil
 *
 * @author girish
 */

namespace app\cwf\vsla\base;

class JsonUtil {

    private $bo;
    private $boParams;
    private $xmlViewPath;

    public function __construct($bo, $boParams, $xmlViewPath) {
        $this->bo = $bo;
        $this->boParams = $boParams;
        $this->xmlViewPath = $xmlViewPath;
    }

    public function reverseBind($postData) {
        // for each field mentioned in the form, we try to retreive the data
        // from the postData and remap it to the model
        \yii::beginProfile('viewParser');
        $viewParser = \app\cwf\vsla\ui\ViewParserFactory::getParser($this->xmlViewPath, "", $this->boParams);
        \yii::endProfile('viewParser');
        foreach ($viewParser->section->fields as $fld) {
            if (!$fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                    continue;
                }
                $fld_id = $fld->id;
                if (strpos($fld_id, ".") > 0) {
                    $this->set_val($postData, $fld_id, $this->bo);
                } else {
                    if (property_exists($postData, $fld_id)) {
                        $val = $postData->$fld_id;
                    } else {
                        throw new \Exception("Missing property/field [" . $fld_id . "]");
                    }
                    if (gettype($val) === "string") {
                        $this->bo->$fld_id = trim($val);
                    } else {
                        if ($this->bo->$fld_id instanceof \app\cwf\vsla\data\ArrayField) {
                            $this->bo->$fld_id->resetItems($val);
                        } else {
                            $this->bo->$fld_id = $val;
                        }
                    }
                }
            } else {
                $sec = $fld;
                if ($sec->editMode == "Add|Edit|Delete" or $sec->editMode == "Add|Edit") {
                    $dataProperty = $sec->dataProperty;
                    // todo: get the path based property
                    if (strpos($dataProperty, ".") > 0) {
                        $targetTable = $this->get_propbypath($dataProperty);
                    } else {
                        $targetTable = $this->bo[$dataProperty];
                    }
                    if ($targetTable instanceof \app\cwf\vsla\data\DataTable) {
                        $pkField = $this->bo->$dataProperty->getPKField();
                        $tranData = &$postData->$dataProperty;

                        // Step 1: Remove missing records
                        for ($i = count($targetTable->Rows()) - 1; $i >= 0; $i--) {
                            $found = false;
                            for ($j = 0; $j < count($tranData); $j++) {
                                $rowData = $tranData[$j];
                                if ($targetTable->Rows()[$i][$pkField] == $rowData->$pkField) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $targetTable->removeRow($i);
                            }
                        }


                        // Step 2: Update records where PKField value matches
                        for ($i = 0; $i < count($targetTable->Rows()); $i++) {
                            for ($j = 0; $j < count($tranData); $j++) {
                                $rowData = $tranData[$j];
                                if ($targetTable->Rows()[$i][$pkField] == $rowData->$pkField) {
                                    foreach ($sec->fields as $fld) {
                                        if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                            $fld_id = $fld->dataProperty;
                                            $this->reverseBindTran($fld
                                                    , $targetTable->Rows()[$i][$fld_id]
                                                    , $rowData->$fld_id);
                                            continue;
                                        }
                                        $fld_id = $fld->id;
                                        if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                            continue;
                                        }
                                        // This is written in 3 lines to enable easier debugging
                                        if (property_exists($rowData, $fld_id)) {
                                            $val = $rowData->$fld_id;
                                        } else {
                                            throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                                        }
                                        if (gettype($val) === "string") {
                                            $targetTable->Rows()[$i][$fld_id] = trim($val);
                                        } else {
                                            $targetTable->Rows()[$i][$fld_id] = $val;
                                        }
                                    }
                                    break;
                                }
                            }
                        }

                        // Step 3: Insert records where PK Field value is empty
                        for ($i = 0; $i < count($tranData); $i++) {
                            $rowData = $tranData[$i];
                            if ($rowData->$pkField == '' or $rowData->$pkField == -1) {
                                $newRow = $targetTable->NewRow();
                                foreach ($sec->fields as $fld) {
                                    if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                        $fld_id = $fld->dataProperty;
                                        $this->reverseBindTran($fld
                                                , $newRow[$fld_id]
                                                , $rowData->$fld_id);
                                        continue;
                                    }
                                    $fld_id = $fld->id;
                                    if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                        continue;
                                    }
                                    // This is written in 3 lines to enable easier debugging
                                    if (property_exists($rowData, $fld_id)) {
                                        $val = $rowData->$fld_id;
                                    } else {
                                        throw new \Exception("Missing property/field [$dataProperty." . $fld_id . "]");
                                    }
                                    if (gettype($val) === "string") {
                                        $newRow[$fld_id] = trim($val);
                                    } else {
                                        $newRow[$fld_id] = $val;
                                    }
                                }
                                $targetTable->addRow($newRow);
                            }
                        }
                    } else if ($targetTable instanceof \app\cwf\vsla\data\ArrayField) {
                        $targetTable->clearItems();
                        foreach ($postData->$dataProperty as $row) {
                            $targetTable->addItem($row->item_value);
                        }
                    } else if (gettype($targetTable) == 'array') {
                        //Todo:: This is a tran, json array and should be populated accordingly
                        $this->set_val($postData, $dataProperty, $this->bo);
                    }
                } else if ($sec->editMode == "Edit" || $sec->editMode == "Edit|Delete") {
                    $dataProperty = $sec->dataProperty;
                    if (strpos($dataProperty, ".") > 0) {
                        $targetTable = $this->get_propbypath($dataProperty);
                    } else {
                        $targetTable = $this->bo[$dataProperty];
                    }
                    if ($targetTable instanceof \app\cwf\vsla\data\DataTable) {
                        $pkField = $this->bo->$dataProperty->getPKField();
                        $tranData = &$postData->$dataProperty;

                        // Update records where PKField value matches
                        for ($i = 0; $i < count($targetTable->Rows()); $i++) {
                            for ($j = 0; $j < count($tranData); $j++) {
                                $rowData = $tranData[$j];
                                if ($i == $j) {
                                    foreach ($sec->fields as $fld) {
                                        if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                            $fld_id = $fld->dataProperty;
                                            $this->reverseBindTran($fld
                                                    , $targetTable->Rows()[$i][$fld_id]
                                                    , $rowData->$fld_id);
                                            continue;
                                        }
                                        $fld_id = $fld->id;
                                        if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                            continue;
                                        }
                                        // This is written in 3 lines to enable easier debugging
                                        if (property_exists($rowData, $fld_id)) {
                                            $val = $rowData->$fld_id;
                                        } else {
                                            throw new \Exception("Missing property/field [$dataProperty." . $fld_id . "]");
                                        }
                                        if (gettype($val) === "string") {
                                            $targetTable->Rows()[$i][$fld_id] = trim($val);
                                        } else {
                                            $targetTable->Rows()[$i][$fld_id] = $val;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    } else if ($targetTable instanceof \app\cwf\vsla\data\ArrayField) {
                        $targetTable->clearItems();
                        foreach ($postData->$dataProperty as $row) {
                            $targetTable->addItem($row->item_value);
                        }
                    }
                } else if ($sec->editMode == "Auto") {
                    $dataProperty = $sec->dataProperty;
                    if (strpos($dataProperty, ".") > 0) {
                        $targetTable = $this->get_propbypath($dataProperty);
                    } else {
                        $targetTable = $this->bo[$dataProperty];
                    }
                    if ($targetTable instanceof \app\cwf\vsla\data\DataTable) {
                        $pkField = $this->bo->$dataProperty->getPKField();
                        $tranData = &$postData->$dataProperty;
                        $targetTableTemp = clone $this->bo[$dataProperty];

                        // Step 1: Remove missing records
                        for ($i = count($targetTable->Rows()) - 1; $i >= 0; $i--) {
                            $targetTable->removeRow($i);
                        }
                        if (!is_array($tranData) || count($tranData) == 0) {
                            continue;
                        }
                        // Step 3: Insert records where PK Field value is empty
                        for ($i = 0; $i < count($tranData); $i++) {
                            $rowData = $tranData[$i];
                            $newRow = $targetTable->NewRow();
                            foreach ($sec->fields as $fld) {
                                if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                    $fld_id = $fld->dataProperty;
                                    if ($i < count($targetTableTemp->Rows())) {
                                        $newRow[$fld_id] = $targetTableTemp->Rows()[$i][$fld_id];
                                    }
                                    $this->reverseBindTran($fld
                                            , $newRow[$fld_id]
                                            , $rowData->$fld_id);
                                    continue;
                                }
                                $fld_id = $fld->id;
                                if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                    continue;
                                }
                                // This is written in 3 lines to enable easier debugging
                                if (property_exists($rowData, $fld_id)) {
                                    $val = $rowData->$fld_id;
                                } else {
                                    throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                                }
                                if (gettype($val) === "string") {
                                    $newRow[$fld_id] = trim($val);
                                } else {
                                    $newRow[$fld_id] = $val;
                                }
                            }
                            $targetTable->addRow($newRow);
                        }
                    } else if ($targetTable instanceof \app\cwf\vsla\data\ArrayField) {
                        $targetTable->clearItems();
                        foreach ($postData->$dataProperty as $row) {
                            $targetTable->addItem($row->item_value);
                        }
                    } else if (gettype($targetTable) == 'array') {
                        //Todo:: This is a tran, json array and should be populated accordingly
                        $this->set_val($postData, $dataProperty, $this->bo);
                    }
                }
            }
        }
    }

    private function set_val($postData, $fld_id, $root) {
        $paths = explode(".", $fld_id);
        $obj = $postData;
        foreach ($paths as $path) {
            $obj = $obj->$path;
        }
        $target = $root;
        for ($i = 0; $i < count($paths); $i++) {
            $path = $paths[$i];
            if ($i == count($paths) - 1) {
                $target->$path = $obj;
                break;
            }
            $target = $target->$path;
            if ($target instanceof \app\cwf\vsla\data\JsonField) {
                $target = $target->Value();
            }
        }
    }

    private function get_propbypath($dataproperty) {
        $tval = $this->bo;
        $paths = explode(".", $dataproperty);
        foreach ($paths as $path) {
            $tval = $tval->$path;
            if ($tval instanceof \app\cwf\vsla\data\JsonField) {
                $tval = $tval->Value();
            }
        }
        return $tval;
    }

    public function reverseBindTran($sec, $tranTable, &$tableData) {
        if ($sec->editMode == "Add|Edit|Delete" or $sec->editMode == "Add|Edit") {
            $dataProperty = $sec->dataProperty;
            $targetTable = $tranTable;
            $pkField = $tranTable->getPKField();
            $tranData = &$tableData;

            // Step 1: Remove missing records
            for ($i = count($targetTable->Rows()) - 1; $i >= 0; $i--) {
                $found = false;
                for ($j = 0; $j < count($tranData); $j++) {
                    $rowData = $tranData[$j];
                    if ($targetTable->Rows()[$i][$pkField] == $rowData->$pkField) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $targetTable->removeRow($i);
                }
            }


            // Step 2: Update records where PKField value matches
            for ($i = 0; $i < count($targetTable->Rows()); $i++) {
                for ($j = 0; $j < count($tranData); $j++) {
                    $rowData = $tranData[$j];
                    if ($targetTable->Rows()[$i][$pkField] == $rowData->$pkField) {
                        foreach ($sec->fields as $fld) {
                            if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                $fld_id = $fld->dataProperty;
                                $this->reverseBindTran($fld
                                        , $targetTable->Rows()[$i][$fld_id]
                                        , $rowData->$fld_id);
                                continue;
                            }
                            $fld_id = $fld->id;
                            if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                continue;
                            }
                            // This is written in 3 lines to enable easier debugging 
                            if (property_exists($rowData, $fld_id)) {
                                $val = $rowData->$fld_id;
                            } else {
                                throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                            }
                            if (gettype($val) === "string") {
                                $targetTable->Rows()[$i][$fld_id] = trim($val);
                            } else {
                                $targetTable->Rows()[$i][$fld_id] = $val;
                            }
                        }
                        break;
                    }
                }
            }

            // Step 3: Insert records where PK Field value is empty
            for ($i = 0; $i < count($tranData); $i++) {
                $rowData = $tranData[$i];
                if ($rowData->$pkField == '' or $rowData->$pkField == -1) {
                    $newRow = $targetTable->NewRow();
                    foreach ($sec->fields as $fld) {
                        if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                            $fld_id = $fld->dataProperty;
                            $this->reverseBindTran($fld
                                    , $newRow[$fld_id]
                                    , $rowData->$fld_id);
                            continue;
                        }
                        $fld_id = $fld->id;
                        if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                            continue;
                        }
                        // This is written in 3 lines to enable easier debugging
                        if (property_exists($rowData, $fld_id)) {
                            $val = $rowData->$fld_id;
                        } else {
                            throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                        }
                        if (gettype($val) === "string") {
                            $newRow[$fld_id] = trim($val);
                        } else {
                            $newRow[$fld_id] = $val;
                        }
                    }
                    $targetTable->addRow($newRow);
                }
            }

            if (count($targetTable->Rows()) == 0) {
                for ($i = 0; $i < count($tranData); $i++) {
                    $rowData = $tranData[$i];
                    $newRow = $targetTable->NewRow();
                    foreach ($sec->fields as $fld) {
                        if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                            $fld_id = $fld->dataProperty;
                            $this->reverseBindTran($fld
                                    , $newRow[$fld_id]
                                    , $rowData->$fld_id);
                            continue;
                        }
                        $fld_id = $fld->id;
                        if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                            continue;
                        }
                        // This is written in 3 lines to enable easier debugging
                        if (property_exists($rowData, $fld_id)) {
                            $val = $rowData->$fld_id;
                        } else {
                            throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                        }
                        if (gettype($val) === "string") {
                            $newRow[$fld_id] = trim($val);
                        } else {
                            $newRow[$fld_id] = $val;
                        }
                    }
                    $targetTable->addRow($newRow);
                }
            }
        } else if ($sec->editMode == "Edit" || $sec->editMode == "Edit|Delete") {
            $dataProperty = $sec->dataProperty;
            $targetTable = $tranTable;
            $pkField = $tranTable->getPKField();
            $tranData = $tableData;

            // Update records where PKField value matches
            for ($i = 0; $i < count($targetTable->Rows()); $i++) {
                for ($j = 0; $j < count($tranData); $j++) {
                    $rowData = $tranData[$j];
                    if ($i == $j) {
                        foreach ($sec->fields as $fld) {
                            if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                                $fld_id = $fld->dataProperty;
                                $this->reverseBindTran($fld
                                        , $targetTable->Rows()[$i][$fld_id]
                                        , $rowData->$fld_id);
                                continue;
                            }
                            $fld_id = $fld->id;
                            if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                                continue;
                            }
                            // This is written in 3 lines to enable easier debugging
                            if (property_exists($rowData, $fld_id)) {
                                $val = $rowData->$fld_id;
                            } else {
                                throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                            }
                            if (gettype($val) === "string") {
                                $targetTable->Rows()[$i][$fld_id] = trim($val);
                            } else {
                                $targetTable->Rows()[$i][$fld_id] = $val;
                            }
                        }
                        break;
                    }
                }
            }
        } else if ($sec->editMode == "Auto") {
            $dataProperty = $sec->dataProperty;
            $targetTable = $tranTable;
            $pkField = $tranTable->getPKField();
            $tranData = $tableData;

            // Step 1: Remove missing records
            for ($i = count($targetTable->Rows()) - 1; $i >= 0; $i--) {
                $targetTable->removeRow($i);
            }
            if (!is_array($tranData) || count($tranData) == 0) {
                //break;
            }
            // Step 3: Insert records where PK Field value is empty
            for ($i = 0; $i < count($tranData); $i++) {
                $rowData = $tranData[$i];
                $newRow = $targetTable->NewRow();
                foreach ($sec->fields as $fld) {
                    if ($fld instanceof \app\cwf\vsla\ui\viewpartsection) {
                        $fld_id = $fld->dataProperty;
                        $this->reverseBindTran($fld
                                , $newRow[$fld_id]
                                , $rowData->$fld_id);
                        continue;
                    }
                    $fld_id = $fld->id;
                    if ($fld->inputType === 'blank' || $fld->inputType === 'nextRow' || $fld->inputType === 'sectionHeader' || $fld->inputType === 'cLabel' || $fld->inputType === 'cHTML' || $fld->inputType === 'cButton' || $fld->inputType === 'cLink' || $fld->isCustom === TRUE) {
                        continue;
                    }
                    // This is written in 3 lines to enable easier debugging
                    if (property_exists($rowData, $fld_id)) {
                        $val = $rowData->$fld_id;
                    } else {
                        throw new \Exception("Missing property/field [$dataProperty." . $fld_id . ']');
                    }
                    if (gettype($val) === "string") {
                        $newRow[$fld_id] = trim($val);
                    } else {
                        $newRow[$fld_id] = $val;
                    }
                }
                $targetTable->addRow($newRow);
            }
        }
    }

}
