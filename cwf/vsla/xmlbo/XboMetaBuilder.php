<?php

/**
 * This class is used for building all the Bo Properties
 * based on the meta information provided in the xml
 */

namespace app\cwf\vsla\xmlbo;

use app\cwf\vsla\entity\EntityManager;
use app\cwf\vsla\entity\ActionScript;
use app\cwf\vsla\data\DataAdapter;
use app\cwf\vsla\data\DataTable;

class XboMetaBuilder {

    /** @var BoParser */
    private $boParser;

    public function __construct(BoParser $boParser) {
        $this->boParser = $boParser;
    }

    public function buildBo(): BoBase {
        $bo = null;
        if ($this->boParser->bometa->type === BoType::DOCUMENT) {
            $bo = new DocBo();
        } else {
            $bo = new MastBo();
        }

        // Set Connection Type        
        $bo->connectionType = $this->boParser->bometa->connectionType;

        // Set System Properties
        $bo['__bo'] = $this->boParser->bometa->id;
        $bo['__doc_id'] = '-1'; // data would be loaded by Fetch
        // Set the instance id for the BO
        $bo['__instanceid'] = uniqid();

        // Build Stage Info
        if ($bo instanceof DocBo) {
            $bo->setDocStageInfo($this->boParser->bometa->docStageInfo);
        }

        // Build control properties
        $this->buildBoFromMeta($bo, $this->boParser->bometa->controlTable);

        // Set the stage id
        if ($bo instanceof DocBo && count($bo->getDocStageInfo()) > 0) {
            $bo['doc_stage_id'] = $bo->getDocStageInfo()[0]['id'];
        }

        // Return bo
        return $bo;
    }

    public function getExtnTableCriteria($criteriaParam, MetaTable $metatable) {
        if ($metatable->isExtended) {
            if (isset($criteriaParam[$metatable->extnTable->primaryKey])) {
                if (!isset($criteriaParam[$metatable->extnTable->basePrimaryKey])) {
                    $criteriaParam[$metatable->extnTable->basePrimaryKey] = $criteriaParam[$metatable->extnTable->primaryKey];
                    return $criteriaParam;
                }
            } else {
                $criteriaParam = $this->getExtnTableCriteria($criteriaParam, $metatable->extnTable);
            }
        }
        return $criteriaParam;
    }

    private function buildBoFromMeta(BoBase $bo, MetaTable $metaTable) {
        $ac = EntityManager::getInstance()->getActionScripts($metaTable->tableName, $bo->connectionType);
        $this->buildProperties($bo, $ac, $metaTable);

        // Build Custom Properties for Control
        $this->buildCustomProperties($bo, $metaTable);
        $this->buildExtendedProperties($bo, $metaTable);
        $this->buildJsonFieldProperties($bo, $metaTable);
        foreach ($metaTable->tableElongs as $met) {
            $this->buildElongateFieldProperties($bo, $met);
        }

        // Build tran properties
        if (isset($metaTable->tranTable)) {
            foreach ($metaTable->tranTable as $tranname => $tranMeta) {
                $bo[$tranname] = $this->buildTranProperties($bo, $tranname, $tranMeta);
            }
        }
        if ($metaTable->isExtended) {
            $this->buildBoFromMeta($bo, $metaTable->extnTable);
        }
    }

    /**
     * Builds properties of the control table and extended tables (if any)
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\xmlbo\ActionScript $as
     * @throws \Exception
     */
    private function buildProperties(BoBase $bo, ActionScript $as, MetaTable $metaTable) {
        $tableDef = $as->getTableFieldCollection();
        if (count($tableDef->Rows()) == 0) {
            // missing object throw exception
            throw new \Exception('Failed to retreive table[' . $as->getTableName() . '] definition. Perhaps it is not available in the connected db');
        }
        foreach ($tableDef->Rows() as $row) {
            // get meta data information
            $colName = $row['column_name'];
            if ($bo->BOPropertyBag() !== NULL && array_key_exists($colName, $bo->BOPropertyBag())) {
                continue;
            }
            $phpType = DataAdapter::getDBtoPHPDataType($row['udt_name']);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = isset($row['character_maximum_length']) ? $row['character_maximum_length'] : 0;
            $scale = $row['numeric_scale'];
            $isUnique = $row['is_primary'];
            // Ensure that the pkField in schema is same as that found in the database
            if ($row['is_primary']) {
                if($row['column_name'] != $metaTable->primaryKey) {
                    throw new \Exception("Primary key mismatch in BO Meta Data: $metaTable->tableName[$metaTable->primaryKey] does not match primary key[".$row['column_name']."] in table");
                }
            }

            // add metadata and field information to the BO
            $bo->setFieldMetaData(new \app\cwf\vsla\data\DataColumn($colName, $phpType, $default, $length, $scale, $isUnique));
            if ($colName == 'doc_date') {
                $bo[$colName] = \app\cwf\vsla\utils\FormatHelper::GetValidDate();
            } elseif ($phpType == DataAdapter::PHPDATA_TYPE_JSON) {
                $bo[$colName] = new \app\cwf\vsla\data\JsonField($colName);
            } else {
                $bo[$colName] = $default;
            }
        }

        foreach ($bo->FieldMetaData() as $col) {
            $cols = [];
            if ($col->phpDataType == DataAdapter::PHPDATA_TYPE_ARRAY) {
                $cols[] = ['columnName' => 'item_value', 'default' => ''];
                $bo->setTranMetaData($col->columnName, $cols);
            }
        }

        if ($this->boParser->bometa->type === BoType::DOCUMENT) {
            $bo['entered_by'] = '';
            $bo['posted_by'] = '';
        }
    }

    /**
     * Builds a tran in the parent object
     * @param type $bo
     * @param string $tranName
     * @param MetaTranTable $tranMeta
     */
    private function buildTranProperties(BOBase $bo, string $tranName, MetaTranTable $tranMeta) {
        $tranDefName = $tranMeta->tableName;
        $fkey = $tranMeta->relation->foreignKey;
        $rootFKey = $tranMeta->relation->rootForeignKey;
        $tranGroup = null;

        if (isset($tranMeta->tranGroup)) {
            $tranGroup = $tranMeta->tranGroup;
        }

        $as = EntityManager::getInstance()->getActionScripts($tranDefName, $this->boParser->bometa->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fkey, $rootFKey, $tranGroup);
        $dt = new DataTable();
        $tableDef = $as->getTableFieldCollection();
        if (count($tableDef->Rows()) == 0) {
            // missing object throw exception
            throw new \Exception('Failed to retreive table[' . $as->getTableName() . '] definition. Perhaps it is not available in the connected db');
        }
        foreach ($tableDef->Rows() as $row) {
            $colName = $row['column_name'];
            $phpType = DataAdapter::getDBtoPHPDataType($row['udt_name']);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = isset($row['character_maximum_length']) ? $row['character_maximum_length'] : 0;
            $scale = $row['numeric_scale'];
            $isUnique = $row['is_primary'];
            // Ensure that the pkField in schema is same as that found in the database
            if ($row['is_primary']) {
                if($row['column_name'] != $tranMeta->primaryKey) {
                    throw new \Exception("Primary key mismatch in BO Meta Data: $tranMeta->tableName[$tranMeta->primaryKey] does not match primary key[".$row['column_name']."] in table");
                }
            }

            if ($row['is_primary']) {
                $dt->setPKField($colName);
            }
            $dt->addColumn($colName, $phpType, $default, $length, $scale, $isUnique);
        }

        // Build Custom tran properties
        if ($tranMeta->customColumns != null) {
            foreach ($tranMeta->customColumns as $colname => $custColumn) {
                $colName = $custColumn->name;
                $phpType = DataAdapter::getPHPtoDBDataType($custColumn->type);
                $default = DataAdapter::getPHPDataTypeDefault($phpType);
                $length = $custColumn->length;
                $scale = $custColumn->scale;
                $dt->addColumn($colName, $phpType, $default, $length, $scale, false);
            }
        }

        // Build Elongated Table properties
        foreach ($tranMeta->tableElongs as $met) {
            $this->buildElongatedTranFieldProperties($bo, $dt, $tranMeta, $met);
        }


        // If the tran has child/nested trans, populate their defintions and create column references
        if (isset($tranMeta->tranTable)) {
            foreach ($tranMeta->tranTable as $childTranName => $childTranMeta) {
                $childdt = $this->buildTranProperties($bo, $childTranName, $childTranMeta);
                $nt = $dt->addNTDef($childTranMeta->tableName);
                $nt->cloneColumns($childdt);
                $dt->addColumn($childTranName, DataAdapter::PHPDATA_TYPE_DATATABLE, $childdt, 0, 0, false, $childTranMeta->tableName);
            }
        }

        foreach ($dt->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $bo->setTranMetaData($tranName, $cols);
        return $dt;
    }

    /**
     * Builds Custom Columns mentioned as part of BoXml
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param type $metaTable
     */
    private function buildCustomProperties(BoBase $bo, MetaTable $metaTable) {
        if ($metaTable->customColumns != null) {
            foreach ($metaTable->customColumns as $custCol => $custColumn) {
                $colName = $custColumn->name;
                if ($bo->BOPropertyBag() !== NULL && array_key_exists($colName, $bo->BOPropertyBag())) {
                    continue;
                }
                $phpType = DataAdapter::getDBtoPHPDataType($custColumn->type);
                $default = DataAdapter::getPHPDataTypeDefault($phpType);
                $length = $custColumn->length;
                $scale = $custColumn->scale;

                // add metadata and field information to the BO
                $bo->setFieldMetaData(new \app\cwf\vsla\data\DataColumn($colName, $phpType, $default, $length, $scale, false));
                $bo[$colName] = $default;
            }
        }
    }

    /**
     * Builds user-defined json field extended properties. These are currently only available 
     * at the control table level (Jsonb fields created with prefix ex_)
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param type $metaTable
     */
    private function buildExtendedProperties(BoBase $bo, MetaTable $metaTable) {
        foreach ($metaTable->extnColumns as $extnCol) {
            if ($bo->BOPropertyBag() !== NULL && array_key_exists($extnCol->name, $bo->BOPropertyBag())) {
                continue;
            }
            $colName = $extnCol->name;
            $phpType = DataAdapter::getEXTNtoPHPDataType($extnCol->type);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = $extnCol->length;
            $scale = $extnCol->scale;
            $bo->setFieldMetaData(new \app\cwf\vsla\data\DataColumn($colName, $phpType, $default, $length, $scale, false));
            $bo[$colName] = $default;
        }
    }

    /**
     * Builds the field definition based on JsonField
     * as mentioned in boXml
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\xmlbo\MetaTable $metaTable
     */
    private function buildJsonFieldProperties(BoBase $bo, MetaTable $metaTable) {
        if (isset($metaTable->jsonFields)) {
            foreach ($metaTable->jsonFields as $jsonField) {
                $field_id = $jsonField->id;
                /* @var app\cwf\vsla\xmlbo\JsonField */
                $jfref = $bo->$field_id;
                $jfref->set_metaInfo($jsonField);
                if ($jfref instanceof \app\cwf\vsla\data\JsonField) {
                    foreach ($jsonField->jfields as $jf) {
                        $this->build_jfield($jfref->Value(), $jf);
                    }
                    foreach ($jsonField->jobjects as $jo) {
                        $this->build_jobject($jfref->Value(), $jo, $bo, $field_id . "." . $jo->name);
                    }
                }
            }
        }
    }

    private function build_jfield($parentMemb, JFieldMeta $jfield) {
        $membName = $jfield->name;
        $parentMemb->$membName = DataAdapter::getPHPDataTypeDefault($jfield->type);
    }

    private function build_jobject($parentMemb, JObjectMeta $jobject, BoBase $bo, string $objpath) {
        $membName = $jobject->name;
        if ($jobject->type == JObjectMeta::SIMPLE_TYPE) {
            $parentMemb->$membName = new \stdClass();
            foreach ($jobject->jfields as $cfield) {
                $this->build_jfield($parentMemb->$membName, $cfield);
            }
            foreach ($jobject->jobjects as $cobject) {
                $this->build_jobject($parentMemb->$membName, $cobject, $bo, $objpath . "." . $cobject->name);
            }
        } elseif ($jobject->type == JObjectMeta::ARRAY_TYPE) {
            $parentMemb->$membName = [];
            $item = new \stdClass();
            foreach ($jobject->jfields as $cfield) {
                $this->build_jfield($item, $cfield);
            }
            foreach ($jobject->jobjects as $cobject) {
                $this->build_jobject($item, $cobject, $bo, $objpath . "." . $cobject->name);
            }
            $typ_info = "__type__" . $membName;
            $parentMemb->$typ_info = $item;
            // populate column defs for tran
            foreach ($jobject->jfields as $col) {
                $cols[] = ['columnName' => $col->name, 'default' => DataAdapter::getPHPDataTypeDefault($col->type)];
            }
            foreach ($jobject->jobjects as $colobj) {
                if ($colobj->type == JObjectMeta::ARRAY_TYPE) {
                    $cols[] = ['columnName' => $colobj->name, 'default' => []];
                } else {
                    $cols[] = ['columnName' => $colobj->name, 'default' => new \stdClass()];
                }
            }
            $bo->setTranMetaData($objpath, $cols);
        }
    }

    /*  Creates elongated fields to the BO.
     *  All elongated table fields are prefixed with id_
     *  This would prevent overlapping fields
     */

    private function buildElongateFieldProperties(BoBase $bo, MetaElongateTable $met) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $bo->connectionType);
        $tableDef = $as->getTableFieldCollection();
        if (count($tableDef->Rows()) == 0) {
            // missing object throw exception
            throw new \Exception('Failed to retreive table[' . $as->getTableName() . '] definition. Perhaps it is not available in the connected db');
        }
        foreach ($tableDef->Rows() as $row) {
            // get meta data information
            $colName = $met->id . '_' . $row['column_name'];
            if (array_key_exists($colName, $bo->BOPropertyBag())) {
                continue;
            }
            $phpType = DataAdapter::getDBtoPHPDataType($row['udt_name']);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = isset($row['character_maximum_length']) ? $row['character_maximum_length'] : 0;
            $scale = $row['numeric_scale'];
            $isUnique = $row['is_primary'];
            // Ensure that the pkField in schema is same as that found in the database
            if ($row['is_primary']) {
                if($row['column_name'] != $met->primaryKey) {
                    throw new \Exception("Primary key mismatch in BO Meta Data <tableElong>: $met->tableName[$met->primaryKey] does not match primary key[".$row['column_name']."] in table");
                }
            }

            // add metadata and field information to the BO
            $bo->setFieldMetaData(new \app\cwf\vsla\data\DataColumn($colName, $phpType, $default, $length, $scale, $isUnique));
            if ($phpType == DataAdapter::PHPDATA_TYPE_JSON) {
                $bo[$colName] = new \app\cwf\vsla\data\JsonField($colName);
            } else {
                $bo[$colName] = $default;
            }

            // set def for array
            if ($phpType == DataAdapter::PHPDATA_TYPE_ARRAY) {
                $cols[] = ['columnName' => 'item_value', 'default' => ''];
                $bo->setTranMetaData($colName, $cols);
            }
        }
    }

    private function buildElongatedTranFieldProperties(BoBase $bo, DataTable $parentTran, MetaTranTable $tranMeta, MetaElongateTable $met) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $this->boParser->bometa->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $met->primaryKey, $met->foreignKey);
        $tableDef = $as->getTableFieldCollection();
        if (count($tableDef->Rows()) == 0) {
            // missing object throw exception
            throw new \Exception('Failed to retreive table[' . $as->getTableName() . '] definition. Perhaps it is not available in the connected db');
        }
        foreach ($tableDef->Rows() as $row) {
            $colName = $met->id . '_' . $row['column_name'];
            $phpType = DataAdapter::getDBtoPHPDataType($row['udt_name']);
            $default = DataAdapter::getPHPDataTypeDefault($phpType);
            $length = isset($row['character_maximum_length']) ? $row['character_maximum_length'] : 0;
            $scale = $row['numeric_scale'];
            $isUnique = $row['is_primary'];
            // Ensure that the pkField in schema is same as that found in the database
            if ($row['is_primary']) {
                if($row['column_name'] != $met->primaryKey) {
                    throw new \Exception("Primary key mismatch in BO Meta Data <tableElong>: $met->tableName[$met->primaryKey] does not match primary key[".$row['column_name']."] in table");
                }
            }

            $parentTran->addColumn($colName, $phpType, $default, $length, $scale, $isUnique);
        }
    }

}
