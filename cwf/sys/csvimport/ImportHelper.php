<?php

namespace app\cwf\sys\csvimport;

use app\cwf\vsla\data\DataAdapter;

class ImportHelper {

    public static function getFieldList(ImportItem $masterInfo) {
        $fieldList = [];
        $viewFields = self::getFieldsInView($masterInfo);
        $tableFields = [];
        $tablerInfo = self::getTableInfo($masterInfo);
        self::getFieldsInTable($tablerInfo['table'], $tableFields, $tablerInfo['connectiontype']);

        foreach ($tableFields as $tableField) {
            foreach ($viewFields as $viewField) {
                if (property_exists($viewField, 'id') && property_exists($viewField, 'control')) {
                    if ($viewField->control == \app\cwf\vsla\design\ControlType::HIDDEN || $viewField->control == \app\cwf\vsla\design\ControlType::PASSWORD) {
                        continue;
                    }
                    if ($tableField->columnName == $viewField->id) {
                        $tableField->isOptional = $viewField->isOptional;
                        $tableField->cname = $viewField->label;
                        $tableField->lookup = self::getLookupdata($viewField->lookup);
                        $tableField->options = self::getOptions($viewField->options);
                        $fieldList[] = $tableField;
                        break;
                    }
                }
            }
        }
        return $fieldList;
    }

    private static function getTableInfo(ImportItem $masterInfo) {
        $cwframework = simplexml_load_file('../' . $masterInfo->module . '/' . $masterInfo->boPath . '.xml');
        $boxml = $cwframework->businessObject;
        $boparser = new \app\cwf\vsla\xmlbo\BoParser($boxml);
        $connectionType = \app\cwf\vsla\data\DataConnect::COMPANY_DB;
        if ($boxml->connectionType) {
            if ($boxml->connectionType->mainDB) {
                $connectionType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
            }
        }
        return ['table' => $boparser->bometa->controlTable, 'connectiontype' => $connectionType];
    }

    private static function getFieldsInView(ImportItem $masterInfo) {
        $formview = \app\cwf\vsla\xml\CwfXmlLoader::loadFile('', '../' . $masterInfo->module . '/' . $masterInfo->editView . '.xml');
        return $formview->controlSection->dataBinding->items;
    }

    private static function getFieldsInTable($metaTable, &$flds, $connectionType) {
        $as = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts($metaTable->tableName, $connectionType);
        $flds = self::buildProperties($as);
        array_merge($flds, self::buildCustomProperties($metaTable));
        if ($metaTable->isExtended) {
            self::getFieldsInTable($metaTable->extnTable, $flds, $connectionType);
        }
    }

    private static function buildCustomProperties($metatable) {
        $fields = [];
        if ($metatable->customColumns != null) {
            foreach ($metatable->customColumns as $colname => $custColumn) {
                $colName = $custColumn->name;
                if (array_key_exists($colName, $fields)) {
                    continue;
                }
                $colName = $custColumn->name;
                $phpType = DataAdapter::getDBtoPHPDataType($custColumn->type);
                $default = DataAdapter::getPHPDataTypeDefault($phpType);
                $length = $custColumn->length;
                $scale = $custColumn->scale;

                // add metadata and field information to the BO
                $fields[] = new \app\cwf\vsla\data\DataColumn($colName, $phpType, $default, $length, $scale, false);
            }
        }
        return $fields;
    }

    private static function buildProperties(\app\cwf\vsla\entity\ActionScript $as) {
        $tableDef = $as->getTableFieldCollection();
        $fields = [];
        if (count($tableDef->Rows()) == 0) {
            // missing object throw exception
            throw new \Exception('Failed to retreive table[' . $as->getTableName() . '] definition. Perhaps it is not available in the connected db');
        }
        foreach ($tableDef->Rows() as $row) {
            // get meta data information
            $colName = $row['column_name'];
            if (array_key_exists($colName, $fields)) {
                continue;
            }
            $phpType = DataAdapter::getDBtoPHPDataType($row['udt_name']);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = isset($row['character_maximum_length']) ? $row['character_maximum_length'] : 0;
            $scale = $row['numeric_scale'];
            $isUnique = $row['is_primary'];
            $defaultValue = $default;

            if ($colName == 'doc_date') {
                $defaultValue = \app\cwf\vsla\utils\FormatHelper::GetValidDate();
            } else {
                $defaultValue = $default;
            }
            $fields[] = new \app\cwf\vsla\data\DataColumn($colName, $phpType, $defaultValue, $length, $scale, $isUnique);
        }
        return $fields;
    }

    private static function getOptions($option) {
        if ($option == NULL)
            return NULL;
        $opts = [];
        foreach ($option->choices as $key => $value) {
            if ($key != -1) {
                $opts[$key] = $value;
            }
        }
        return $opts;
    }

    private static function getLookupdata($lookup) {
        if ($lookup == NULL)
            return NULL;
        $dt = [];
        $selection = new \app\cwf\vsla\xmlbo\LookupInfo($lookup->namedLookup, $lookup->displayMember, $lookup->valueMember);
        foreach ($selection->Items as $key => $value) {
            if ($key != -1) {
                $dt[$key] = $value;
            }
        }
        return $dt;
    }

    public static function getFileData($file, $delimiter = ',') {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $tblHeader = [];
            $lineArray = fgetcsv($handle, 4000, $delimiter);
            if ($lineArray !== FALSE) {
                for ($j = 0; $j < count($lineArray); $j++) {
                    $tblHeader[$j] = $lineArray[$j];
                }
            }
            $tblHeader[] = 'Is Valid';
            $tblHeader[] = 'Action';
            $i = 0;
            $data2DArray = [];
            while (($lineArray = fgetcsv($handle, 4000, $delimiter)) !== FALSE) {
                /*               $has_spe_chr=FALSE;
                  //                for ($j=0; $j<count($lineArray); $j++) {
                  //                    if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $lineArray[$j])){
                  //                        $has_spe_chr = TRUE;
                  //                    }
                  //                }
                  //                $ip = $has_spe_chr == FALSE ? 'valid':'invalid';
                  $lineArray[] = $ip;
                  $data2DArray[$i] = $lineArray; */
                for ($j = 0; $j < count($lineArray); $j++) {
                    $data2DArray[$i][$tblHeader[$j]] = $lineArray[$j];
                }
                $data2DArray[$i]['Is Valid'] = FALSE;
                $i++;
            }
            fclose($handle);
        }
        return ['data' => $data2DArray, 'headers' => $tblHeader];
    }

    public static function getImportData($mastername, $file, $branch_id) {
        $masterInfo = ImportParser::getMasterInfo($mastername);
        $fieldList = self::getFieldList($masterInfo);
        $importData = self::getFileData($file);
        $tableData = [];
        $dataValid = [];
        $cnt = 1;
        $matchedfields = 0;
        foreach ($importData['data'] as &$dataRow) {
            $tableRow = [];
            $dataRow['Is Valid'] = TRUE;
            $mismatch = TRUE;
            foreach ($dataRow as $fld => $val) {
                if ($fld == 'Is Valid') {
                    continue;
                }
                foreach ($fieldList as $field) {
                    if ($fld == $field->cname) {
                        if ($field->options != NULL) {
                            foreach ($field->options as $key => $value) {
                                if ($val === $value) {
                                    $tableRow[$field->columnName] = $key;
                                    $mismatch = FALSE;
                                    break;
                                }
                            }
                            if ($mismatch) {
                                $dataRow['Is Valid'] = FALSE;
                                $dataValid[] = 'Row#' . $cnt . ': ' . $fld . ' has invalid value ' . $val;
                            }
                        } elseif ($field->lookup != NULL) {
                            foreach ($field->lookup as $key => $value) {
                                if ($val === $value) {
                                    $tableRow[$field->columnName] = $key;
                                    $mismatch = FALSE;
                                    break;
                                }
                            }
                            if ($mismatch) {
                                $dataRow['Is Valid'] = FALSE;
                                $dataValid[] = 'Row#' . $cnt . ': ' . $fld . ' has invalid value ' . $val;
                            }
                        } else {
                            switch ($field->phpDataType) {
                                case 'decimal':
                                    $scale = 0;
                                    if ($field->scale != NULL && $field->scale != '') {
                                        $scale = $field->scale;
                                    }
                                    $tableRow[$field->columnName] = round((float) $val, $scale);
                                    break;
                                case 'bool':
                                    $bval = FALSE;
                                    if (strtolower($val) == 'true') {
                                        $bval = TRUE;
                                    }
                                    $tableRow[$field->columnName] = $bval;
                                    break;
                                default:
                                    $tableRow[$field->columnName] = $val;
                                    break;
                            }
                            $mismatch = FALSE;
                        }
                        if ($masterInfo->crudKey == $field->columnName) {
                            $masterInfo->crudKeyName = $field->cname;
                        }
                        $matchedfields++;
                        break;
                    }
                }
            }
            if ($matchedfields > 0 && array_key_exists($masterInfo->crudKeyName, $dataRow)) {
                $dataRow['Action'] = self::validateRecord($masterInfo->crudKey, $dataRow[$masterInfo->crudKeyName], self::getTableInfo($masterInfo), $branch_id);
                $tableRow[$masterInfo->primaryKey] = $dataRow['Action'];
                if (!$mismatch) {
                    $tableData[] = $tableRow;
                }
            } else {
                $dataRow['Is Valid'] = FALSE;
                $dataValid[] = 'Row#' . $cnt . ': has invalid values.';
                $dataRow['Action'] = -99;
            }
            $cnt++;
        }
        return ['tableData' => $tableData, 'importData' => $importData, 'dataValid' => $dataValid];
    }

    private static function validateRecord($crudkey, $crudval, $table, $branch_id = -1) {
        $cmd = new \app\cwf\vsla\data\SqlCommand();
        $cmdtext = 'Select ' . $table['table']->primaryKey . ' From ' . $table['table']->tableName . ' Where ' . $crudkey . ' = :pval';
        if ($branch_id != -1) {
            $cmdtext .= ' and branch_id=:pbranch_id';
        }
        $cmd->setCommandText($cmdtext);
        $cmd->addParam('pval', $crudval);
        if ($branch_id != -1) {
            $cmd->addParam('pbranch_id', $branch_id);
        }
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmd, $table['connectiontype']);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0][$table['table']->primaryKey];
        }
        return -1;
    }

    public static function importData($mastername, $csvdata, $branch_id) {
        $masterInfo = ImportParser::getMasterInfo($mastername);
        $recordCount = 0;
        $recordErrors = [];
        $recordSuccess = 0;
        $loginBranch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('branch_id', $branch_id);
        foreach ($csvdata['tableData'] as $importRow) {
            $fullbopath = $masterInfo->module . '/' . $masterInfo->boPath;
            $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
            $helperOption->bo_id = $masterInfo->boPath;
            $helperOption->modulePath = '@app/' . $masterInfo->module;
            $helperOption->moduleNamespace = 'app\\' . str_replace('/', '\\', $masterInfo->module);
            $helperOption->inParam = [($masterInfo->primaryKey) => ((string) $importRow[$masterInfo->primaryKey]), 'doc_type' => ''];
            $helperOption->formName = $masterInfo->editView;

            $helper = new \app\cwf\vsla\base\RestBoHelper();
            $result = $helper->actionFetch($helperOption);
            if($mastername === 'User'){
                $importRow['user_pass'] = self::random_str(12);
                $importRow['user_pass_confirm'] = $importRow['user_pass'];
                $importRow['is_active'] = FALSE;
            }
            $res = \yii\helpers\BaseArrayHelper::merge($result['boData'], $importRow);

            if ($masterInfo->forBranch) {
                $res['branch_id'] = $branch_id;
            }

            $helperOption->postData = json_decode(json_encode($res), FALSE);
            $helper = new \app\cwf\vsla\base\RestBoHelper();
            $result = $helper->actionSave($helperOption);
            if ($result->SaveStatus != 'OK') {
                $recordErrors[$recordCount] = $result->BrokenRules;
            } else {
                $recordSuccess++;
            }
            $recordCount++;
        }
        \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('branch_id', $loginBranch_id);
        return json_encode(['total' => $recordCount, 'saved' => $recordSuccess, 'issues' => $recordErrors]);
    }

    private static function random_str($length) {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ#$@&';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

}
