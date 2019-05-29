<?php

namespace app\cwf\vsla\xmlbo;

use app\cwf\vsla\entity\EntityManager;
use app\cwf\vsla\entity\ActionScript;
use app\cwf\vsla\security\AccessManager;
use app\cwf\vsla\data\SqlParamType;
use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\data\DataAdapter;
use app\cwf\vsla\xmlbo\BoParser;
use app\cwf\vsla\xmlbo\BoBase;
use app\cwf\vsla\xmlbo\BoType;
use app\cwf\vsla\xmlbo\LogAction;

class XboBuilder {

    const POST = 5;
    const UNPOST = 0;

    private $boxml;

    /** @var BoParser * */
    public $boparser;
    public $access_level = -1;
    private $isnew = FALSE;
    private $connectionType = DataConnect::COMPANY_DB;
    private $boEventHandler;
    private $generatedKeys;
    public $logAction;

    function __construct($boxmlpath) {
        $cwframework = simplexml_load_file($boxmlpath);
        $this->boxml = $cwframework->businessObject;
        $this->boparser = new BoParser($this->boxml);
        $this->connectionType = $this->boparser->bometa->connectionType;
        $this->access_level = AccessManager::verifyAccess($this->boparser->bometa->id);
        $this->boEventHandler = NULL;
        if ($this->boxml->codeBehind) {
            $className = (string) $this->boxml->codeBehind->className;
            $this->boEventHandler = new $className();
        }
    }

    public function bindEventHandler(BoBase $bo) {
        if ($this->boEventHandler != NULL) {
            $this->boEventHandler->initialise($bo);
        }
    }

    public function buildBO($criteriaParam) {
        // If user does not have access to this master/document
        // return null
        if ($this->access_level == -1 || $this->access_level == 0) {
            return NULL;
        }
        // Put back the wizard cahced data (if exists)
        if (array_key_exists('wiz_cache_id', $criteriaParam)) {
            $wizFormData = \app\cwf\vsla\render\WizardHelper::getCachedWizData($criteriaParam['wiz_cache_id']);
            $criteriaParam['formData'] = $wizFormData;
        }
        // Build the MetaData information of the BO
        $boMetaBuilder = new XboMetaBuilder($this->boparser);
        $bo = $boMetaBuilder->buildBo();

        $criteriaParam = $boMetaBuilder->getExtnTableCriteria($criteriaParam, $this->boparser->bometa->controlTable);

        //Run custom BeforeFetch code (if any)
        $this->bindEventHandler($bo);
        if ($this->boEventHandler != NULL) {
            $this->boEventHandler->beforeFetch($criteriaParam);
        }

        // Always call FetchBO 
        $this->FetchBO($bo, $criteriaParam, $this->boparser->bometa->controlTable);

        //Run custom AfterFetch code (if any)
        if ($this->boEventHandler != NULL) {
            $this->boEventHandler->afterFetch($criteriaParam);
        }
        return $bo;
    }

    private function FetchBO(BoBase $bo, $criteriaparam, $metatable) {
        if ($metatable->fetchOrCreate->method == 'useEntity') {
            // Proceed only if Primary key exists in the criteria
            if (isset($criteriaparam[$this->boparser->bometa->controlTable->primaryKey])) {
                // Proceed only if Primary key != -1 (we are opening an existing document)
                if ($criteriaparam[$this->boparser->bometa->controlTable->primaryKey] != -1) {
                    // Build control properties
                    $as = EntityManager::getInstance()->getActionScripts($metatable->tableName, $this->connectionType);

                    $cmm = $as->getFetchCmm();
                    foreach (array_keys($cmm->getParams()) as $paramName) {
                        $prop = substr_replace($paramName, '', 0, 1);
                        $val = $criteriaparam[$prop];
                        $cmm->setParamValue($paramName, $val);
                    }
                    $dtnew = DataConnect::getData($cmm, $this->connectionType);

                    // Use the table column definition to ensure that there is proper data type casting
                    if (count($dtnew->Rows()) > 0) {
                        foreach ($as->getTableFieldCollection()->Rows() as $colDef) {
                            $val = $dtnew->Rows()[0][$colDef['column_name']];
                            if ($colDef['column_name'] == 'xf') {
                                if ($val != NULL) {
                                    $this->decodeXF($bo, $val);
                                }
                            } else {
                                if ($colDef['udt_name'] == 'json' || $colDef['udt_name'] == 'jsonb') {
                                    // This is a json field. mapp members to avoid breaking the structure
                                    // todo : mapping code
                                    XboDataAction::map_json_field($bo[$colDef['column_name']], $val);
                                } else {
                                    $bo[$colDef['column_name']] = DataAdapter::getDBtoPHPDataValue($colDef['udt_name'], $val, $colDef['column_name']);
                                }
                            }
                        }
                    }

                    // Fetch Elong Table data
                    foreach ($this->boparser->bometa->controlTable->tableElongs as $met) {
                        $this->FetchElong($bo, $met);
                    }
                } else if (is_array($criteriaparam)) {
                    // We set the criteria param values to Bo properties only for the new document
                    foreach ($criteriaparam as $cparamkey => $cparamval) {
                        if (array_key_exists($cparamkey, $bo->BOPropertyBag())) {
                            $bo->$cparamkey = $cparamval;
                        }
                    }
                }
            }
        } else if ($metatable->fetchOrCreate->method == 'useOnFetch') {
            // The custom event handler is expected to handle all scenarios
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onFetch($criteriaparam, $metatable->tableName);
            }
        }
        $this->GetES($bo);
        $bo['__doc_id'] = $bo[$this->boparser->bometa->controlTable->primaryKey];
        $bo['__editMode'] = FALSE;
        if ($bo['__doc_id'] == -1 || $bo['__doc_id'] == '') {
            // This is a new document/master. Set Edit to true
            $bo['__editMode'] = TRUE;
        }

        // Set Stage Information
        if ($this->boparser->bometa->type === BoType::DOCUMENT) {
            $instage = true;
            foreach ($bo->getDocStageInfo() as &$si) {
                if ($bo->doc_stage_id != '') {
                    $si['state'] = $instage;
                    if ($si['id'] == $bo->doc_stage_id) {
                        $instage = false;
                    }
                }
            }
        }

        // Second Fetch every tran      
        if (isset($metatable->tranTable)) {
            foreach ($metatable->tranTable as $tranName => $tranMeta) {
                if ($tranMeta->fetchOrCreate->method == 'useEntity') {
                    $parentPkVal = $bo[$metatable->primaryKey];
                    $this->FetchTran($bo, $tranName, $tranMeta, $parentPkVal, $bo);
                } else if ($tranMeta->fetchOrCreate->method == 'useOnFetch') {
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->onFetch($criteriaparam, $tranMeta->tableName);
                    }
                }
            }
        }

        if ($metatable->isExtended) {
            $this->FetchBO($bo, $criteriaparam, $metatable->extnTable);
        }
    }

    private function FetchTran($parent, $tranName, MetaTranTable $tranMeta, $parentPkVal, $bo) {
        if ($tranMeta->isReverseTran) {
            // Fetch Reverse Tran            
            $tName = $tranMeta->tableName;
            $fKey = $tranMeta->relation->foreignKey;
            $rootFKey = $tranMeta->relation->rootForeignKey;

            $pKey = $tranMeta->primaryKey;
            $fkid = $parent[$tranMeta->relation->foreignKey];

            $ac = EntityManager::getInstance()->getActionScripts($tName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fKey, $rootFKey);

            $cmmTranFetch = $ac->getFetchCmm();
            $cmmTranFetch->setParamValue(SqlParamType::PARAM_PREFIX . $pKey, $fkid);
            $dtTran = DataConnect::getData($cmmTranFetch, $this->connectionType);

            foreach ($dtTran->Rows() as $row) {
                $newRow = $parent[$tranName]->NewRow();
                // Use the table column definition to ensure that there is proper data type casting
                foreach ($ac->getTableFieldCollection()->Rows() as $colDef) {
                    $val = $row[$colDef['column_name']];
                    $newRow[$colDef['column_name']] = DataAdapter::getDBtoPHPDataValue($colDef['udt_name'], $val);
                }
                $parent[$tranName]->AddRow($newRow);
            }
        } else {
            // Fetch Tran            
            $tName = $tranMeta->tableName;
            $fkey = $tranMeta->relation->foreignKey;
            $rootFKey = $tranMeta->relation->rootForeignKey;
            $tranGroup = null;
            if (isset($tranMeta->tranGroup)) {
                $tranGroup = $tranMeta->tranGroup;
            }

            $ac = EntityManager::getInstance()->getActionScripts($tName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fkey, $rootFKey, $tranGroup);

            $cmmTranFetch = $ac->getFetchCmm($tranMeta->fetchOrCreate->orderby);
            $cmmTranFetch->setParamValue(SqlParamType::PARAM_PREFIX . $fkey, $parentPkVal);
            if (isset($tranMeta->tranGroup)) {
                $cmmTranFetch->setParamValue(SqlParamType::PARAM_PREFIX . $tranGroup, $tranMeta->tableID);
            }
            $dtTran = DataConnect::getData($cmmTranFetch, $this->connectionType);

            foreach ($dtTran->Rows() as $row) {
                $newRow = $parent[$tranName]->NewRow();
                // Use the table column definition to ensure that there is proper data type casting
                foreach ($ac->getTableFieldCollection()->Rows() as $colDef) {
                    $val = $row[$colDef['column_name']];
                    $newRow[$colDef['column_name']] = DataAdapter::getDBtoPHPDataValue($colDef['udt_name'], $val);
                }
                // If the tran has child/nested trans, populate their defintions and create column references
                if (isset($tranMeta->tranTable)) {
                    foreach ($tranMeta->tranTable as $childTranName => $childTranMeta) {
                        $selfPkVal = $newRow[$tranMeta->primaryKey];
                        $this->FetchTran($newRow, $childTranName, $childTranMeta, $selfPkVal, $bo);
                    }
                }
                // finally add row to parent
                $parent[$tranName]->AddRow($newRow);
            }

            foreach ($tranMeta->tableElongs as $met) {
                $this->FetchElongTran($bo, $tranMeta, $parent[$tranName], $met);
            }
        }
    }

    private function FetchElong(BoBase $bo, MetaElongateTable $met) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $this->connectionType);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From ' . $met->tableName
                . ' Where ' . $met->tranGroup . '=:ptran_group And '
                . $met->primaryKey . '=:pvch_id');
        $cmm->addParam('ptran_group', $this->boparser->bometa->controlTable->tableName);
        $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        $dtElong = DataConnect::getData($cmm, $this->connectionType);
        if (count($dtElong->Rows()) == 1) {
            $dr = $dtElong->Rows()[0];
            foreach ($as->getTableFieldCollection()->Rows() as $colDef) {
                $val = $dr[$colDef['column_name']];
                $targetCol = $met->id . '_' . $colDef['column_name'];
                if ($colDef['udt_name'] == 'json' || $colDef['udt_name'] == 'jsonb') {
                    // This is a json field. mapp members to avoid breaking the structure
                    // todo : mapping code
                    XboDataAction::map_json_field($bo[$targetCol], $val);
                } else {
                    $bo[$targetCol] = DataAdapter::getDBtoPHPDataValue($colDef['udt_name'], $val, $targetCol);
                }
            }
        }
    }

    private function FetchElongTran(BoBase $bo, MetaTranTable $tranMeta, \app\cwf\vsla\data\DataTable $parentTran, MetaElongateTable $met) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $this->connectionType);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From ' . $met->tableName
                . ' Where ' . $met->tranGroup . '=:ptran_group And '
                . $met->foreignKey . '=:pvch_id');
        if ($met->useTableID) {// Use parent table id as trangroup if mentioned in boxml 
            $cmm->addParam('ptran_group', $tranMeta->tableID);
        } else {
            $cmm->addParam('ptran_group', $tranMeta->tableName);
        }
        $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        $dtElong = DataConnect::getData($cmm, $this->connectionType);
        foreach ($parentTran->Rows() as &$ptRow) {
            $drElong = $dtElong->findRow($met->primaryKey, $ptRow[$tranMeta->primaryKey]);
            if (count($drElong) > 0) {
                foreach ($as->getTableFieldCollection()->Rows() as $colDef) {
                    $val = $drElong[$colDef['column_name']];
                    $targetCol = $met->id . '_' . $colDef['column_name'];
                    if ($colDef['udt_name'] == 'json' || $colDef['udt_name'] == 'jsonb') {
                        // This is a json field. mapp members to avoid breaking the structure
                        // todo : mapping code
                        XboDataAction::map_json_field($bo[$targetCol], $val);
                    } else {
                        $ptRow[$targetCol] = DataAdapter::getDBtoPHPDataValue($colDef['udt_name'], $val, $targetCol);
                    }
                }
            }
        }
    }

    public function saveBO(BoBase $bo, \app\cwf\vsla\workflow\WfOption $wfOption = null) {
        if ($this->access_level == -1 || $this->access_level == 0) {
            throw new \Exception('Access Level prohibits modifications to this document!');
        }
        if ($bo instanceof DocBo && !$this->matchTimestamp($bo, $wfOption)) {
            throw new \Exception('Timestamp/Status mismatch. Kindly reopen the document and view changes');
        }

        $cn = NULL;
        try {
            $cn = DataConnect::getCn($this->connectionType);
            $cn->beginTransaction();

            $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];
            $newStatus = DocBo::STATUS_NEW;
            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                switch ($wfOption->doc_action) {
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_SEND:
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_APPROVE:
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_REJECT:
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_ASSIGN:
                        $newStatus = DocBo::STATUS_IN_WORKFLOW;
                        break;
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_POST:
                        $newStatus = DocBo::STATUS_POSTED;
                        break;
                    case \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST:
                        $newStatus = DocBo::STATUS_IN_WORKFLOW;
                        break;
                    default :
                        if ($bo->status == DocBo::STATUS_NEW) {
                            $newStatus = DocBo::STATUS_CREATED;
                        } else {
                            // No change in status required
                            $newStatus = $bo->status;
                        }
                }
            }

            //Run custom Before Save code (if any)
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->beforeSave($cn);
            }

            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                $cstatus = $bo->status;
            }

            // Test if we are required to unpost the document
            if ($this->boparser->bometa->type === BoType::DOCUMENT && $bo->status == DocBo::STATUS_POSTED && $newStatus != DocBo::STATUS_POSTED) {
                // Unpost the document
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                if ($this->boparser->bometa->wfType == WfType::WF_SINGLE_STAGE) {
                    $sql = 'Update ' . $this->boparser->bometa->controlTable->tableName .
                            ' Set status= :pnewStatus, last_updated=current_timestamp(0) ' .
                            ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id';
                } else {
                    $sql = 'Update ' . $this->boparser->bometa->controlTable->tableName .
                            ' Set status= :pnewStatus, last_updated=current_timestamp(0),
                              doc_stage_id = :pdoc_stage_id
                          Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id';
                    $cmm->addParam('pdoc_stage_id', $this->getLastDocStage());
                }
                $cmm->setCommandText($sql);
                $cmm->addParam('pnewStatus', $newStatus);
                $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                DataConnect::exeCmm($cmm, $cn);

                // Make Workflow entries if required
                if ($wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                    $wfOption->user_id_from = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
                    $wfOption->user_id_to = $wfOption->user_id_from;
                    $wfOption->doc_action = \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST;
                    $this->setDocWorkflow($bo, $cn, $wfOption);
                }

                // Get new Status (ensuring that unpost was successful)
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                if ($this->boparser->bometa->wfType == WfType::WF_SINGLE_STAGE) {
                    $cmm->setCommandText('Select status, last_updated From ' . $this->boparser->bometa->controlTable->tableName .
                            ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                } else {
                    $cmm->setCommandText('Select status, last_updated, doc_stage_id From ' . $this->boparser->bometa->controlTable->tableName .
                            ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                }
                $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                $dtStatus = DataConnect::getData($cmm, $this->connectionType, $cn);
                $bo->status = DataAdapter::getDBtoPHPDataValue('int2', $dtStatus->Rows()[0]['status']);
                $bo->last_updated = DataAdapter::getDBtoPHPDataValue('timestamp', $dtStatus->Rows()[0]['last_updated']);
                if (array_key_exists('doc_stage_id', $dtStatus->Rows()[0])) {
                    $bo->doc_stage_id = $dtStatus->Rows()[0]['doc_stage_id'];
                }
            } else {
                // Avoid accidental save of posted document
                if ($this->boparser->bometa->type === BoType::DOCUMENT && $bo->status == DocBo::STATUS_POSTED) {
                    throw new \Exception('Trying to save Posted document. Save prohibited');
                }
                // Save the Master/Document in the normal course
                if ($this->boparser->bometa->controlTable->save->method == 'useEntity') {
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->beforeEntitySave($cn, $this->boparser->bometa->controlTable->tableName);
                    }
                    $ac = EntityManager::getInstance()->getActionScripts($this->boparser->bometa->controlTable->tableName, $this->connectionType);
                    $this->save($bo, $ac, $cn);
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->afterEntitySave($cn, $this->boparser->bometa->controlTable->tableName);
                    }
                } else if ($this->boparser->bometa->controlTable->save->method == 'useOnSave') {
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->onSave($cn, $this->boparser->bometa->controlTable->tableName);
                    }

                    // step 2: save every tran
                    if (isset($this->boparser->bometa->controlTable->tranTable)) {
                        $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];
                        foreach ($this->boparser->bometa->controlTable->tranTable as $tranname => $tranTable) {
                            // Save the Trans
                            \yii::beginProfile('save-tran');
                            $this->saveTran($tranname, $tranTable, $cn, $bo, $bo, $pkid);
                            \yii::endProfile('save-tran');
                        }
                    }
                }
                // Set the doc_id after control is saved
                $bo['__doc_id'] = $bo[$this->boparser->bometa->controlTable->primaryKey];
                if ($this->boparser->bometa->controlTable->isExtended) {
                    $this->saveExtended($bo, $cn, $this->boparser->bometa->controlTable->extnTable);
                }

                //Run custom After Save code (if any)
                if ($this->boEventHandler != NULL) {
                    $this->boEventHandler->afterSave($cn);
                }

                if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                    // Always toggle document status
                    $cmm = new \app\cwf\vsla\data\SqlCommand();
                    if ($this->boparser->bometa->wfType == WfType::WF_SINGLE_STAGE) {
                        $cmm->setCommandText('Update ' . $this->boparser->bometa->controlTable->tableName .
                                ' Set status = :pnewStatus
                              Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                    } else {
                        if ($wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_SEND ||
                                $wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_APPROVE ||
                                $wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_POST) {
                            $doc_stage_status_sql = 'doc_stage_status = doc_stage_status + 1';
                        } elseif ($wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_REJECT ||
                                $wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                            $doc_stage_status_sql = 'doc_stage_status = doc_stage_status - 1';
                        } else {
                            $doc_stage_status_sql = 'doc_stage_status = doc_stage_status';
                        }
                        $cmm->setCommandText('Update ' . $this->boparser->bometa->controlTable->tableName .
                                ' Set status = :pnewStatus, 
                                  doc_stage_id = :pdoc_stage_id, ' . $doc_stage_status_sql .
                                ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                        if ($wfOption->next_stage_id != '') {
                            $cmm->addParam('pdoc_stage_id', $wfOption->next_stage_id);
                        } else {
                            $cmm->addParam('pdoc_stage_id', $bo->doc_stage_id);
                        }
                    }
                    $cmm->addParam('pnewStatus', $newStatus);
                    $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                    DataConnect::exeCmm($cmm, $cn);

                    // Make Workflow entries if required
                    if ($wfOption->doc_action === \app\cwf\vsla\workflow\DocWorkflow::WF_ASSIGN) {
                        $wfOption->next_stage_id = $bo->doc_stage_id;
                        $this->setDocWorkflow($bo, $cn, $wfOption);
                    } else if ($wfOption->doc_action != '') {
                        $wfOption->doc_stage_id_from = $bo->doc_stage_id;
                        $this->setDocWorkflow($bo, $cn, $wfOption);
                    }
                    // Required for menu count display
                    if ($bo->status == DocBo::STATUS_NEW || $bo->status == DocBo::STATUS_CREATED) {
                        $this->setDocCreated($bo, $cn, $bo[$this->boparser->bometa->controlTable->primaryKey], $newStatus);
                    }

                    // Get new Status (ensuring that status toggle was successful)
                    $cmm = new \app\cwf\vsla\data\SqlCommand();
                    if ($this->boparser->bometa->wfType == WfType::WF_SINGLE_STAGE) {
                        $cmm->setCommandText('Select status, last_updated From ' . $this->boparser->bometa->controlTable->tableName .
                                ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                    } else {
                        $cmm->setCommandText('Select status, doc_stage_id, doc_stage_status, last_updated From ' . $this->boparser->bometa->controlTable->tableName .
                                ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :pvch_id');
                    }
                    $cmm->addParam('pvch_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                    $dtStatus = DataConnect::getData($cmm, $this->connectionType, $cn);
                    $bo->status = DataAdapter::getDBtoPHPDataValue('int2', $dtStatus->Rows()[0]['status']);
                    if (array_key_exists('doc_stage_id', $dtStatus->Rows()[0])) {
                        $bo->doc_stage_id = $dtStatus->Rows()[0]['doc_stage_id'];
                        $bo->doc_stage_status = intval($dtStatus->Rows()[0]['doc_stage_status']);
                    }
                    $bo->last_updated = DataAdapter::getDBtoPHPDataValue('timestamp', $dtStatus->Rows()[0]['last_updated']);
                } else {
                    // This is a master. Therefore, pickup last_updated
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->resetLastUpdated($cn, $this->boparser->bometa->controlTable->tableName, $this->boparser->bometa->controlTable->primaryKey);
                    } else {
                        $cmm = new \app\cwf\vsla\data\SqlCommand();
                        $cmm->setCommandText('Select last_updated From ' . $this->boparser->bometa->controlTable->tableName .
                                ' Where ' . $this->boparser->bometa->controlTable->primaryKey . ' = :ppk_id');
                        $cmm->addParam('ppk_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                        $dtStatus = DataConnect::getData($cmm, $this->connectionType, $cn);
                        $bo->last_updated = DataAdapter::getDBtoPHPDataValue('timestamp', $dtStatus->Rows()[0]['last_updated']);
                    }
                }
            }


            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                // Update ES table
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from sys.sp_status_update_es(:pvoucher_id, :pcurrent_status, :pnew_status, :pfull_user_name, :puser_name)');
                $cmm->addParam('pvoucher_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
                $cmm->addParam('pcurrent_status', $cstatus);
                $cmm->addParam('pnew_status', $newStatus);
                $cmm->addParam('pfull_user_name', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName());
                $cmm->addParam('puser_name', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUserName());
                DataConnect::exeCmm($cmm, $cn);
            }

            // Make log entry in audit trail
            if ($pkid == -1 || $pkid == '') {
                $this->logAction = LogAction::CREATED;
            } else {
                $this->logAction = LogAction::SAVEDOREDITED;
            }
            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                if ($cstatus != DocBo::STATUS_POSTED && $newStatus == DocBo::STATUS_POSTED) {
                    $this->logAction = LogAction::POSTED;
                }
                if ($cstatus == DocBo::STATUS_POSTED && $newStatus != DocBo::STATUS_POSTED) {
                    $this->logAction = LogAction::UNPOSTED;
                }
            }


            $cn->commit();
            $cn = null;
            // Fetch ES  After commit
            $this->GetES($bo);
            // Set Stage Information
            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                $instage = true;
                foreach ($bo->getDocStageInfo() as &$si) {
                    if ($bo->doc_stage_id != '') {
                        $si['state'] = $instage;
                        if ($si['id'] == $bo->doc_stage_id) {
                            $instage = false;
                        }
                    }
                }
            }


            //Run custom After Commit code (if any)
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterCommit($this->generatedKeys);
            }
            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                if ($cstatus != DocBo::STATUS_POSTED && $newStatus == DocBo::STATUS_POSTED) {
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->afterPost($this->generatedKeys);
                    }
                }
                if ($cstatus == DocBo::STATUS_POSTED && $newStatus != DocBo::STATUS_POSTED) {
                    if ($this->boEventHandler != NULL) {
                        $this->boEventHandler->afterUnPost($this->generatedKeys);
                    }
                }
            }

            // Set the master as dirty
            if ($bo instanceof MastBo) {
                LookupCache::markDirty($this->boparser->bometa->id);
            }
        } catch (\Exception $ex) {
            if ($cn != null && $cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
            }
            throw $ex;
        }
    }

    private function saveExtended(BoBase $bo, \PDO $cn, $metatable) {
        $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];
        // Step 1 : Save Reverse Tran
        if (isset($metatable->tranTable)) {
            foreach ($metatable->tranTable as $tranname => $tranTable) {
                if ($tranTable->isReverseTran) {
                    $this->saveTran($tranname, $tranTable, $cn, $bo, $bo, $pkid);
                }
            }
        }

        // Step 2: Save Control
        $bo[$metatable->primaryKey] = $bo[$this->boparser->bometa->controlTable->primaryKey];
        $this->generatedKeys[$metatable->primaryKey] = $bo[$metatable->primaryKey];
        if ($metatable->save->method == 'useEntity') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->beforeEntitySave($cn, $metatable->tableName);
            }
            $ac = EntityManager::getInstance()->getActionScripts($metatable->tableName, $this->connectionType);
            if ($this->isnew) {
                $cmm = $ac->getInsertCmm();
            } else {
                $cmm = $ac->getUpdateCmm();
            }
            foreach (array_keys($cmm->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if (gettype($bo[$prop]) == 'boolean') {
                    $val = (int) $bo[$prop];
                } else {
                    $val = $bo[$prop];
                }
                $cmm->setParamValue($paramName, $val);
            }
            $stmt = $cn->prepare($cmm->getCommandText());
            $stmt->execute($cmm->getParamsForBind());
            $stmt = null;
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterEntitySave($cn, $metatable->tableName);
            }
            if ($metatable->isExtended) {
                $this->saveExtended($bo, $cn, $metatable->extnTable);
            }
        } else if ($metatable->save->method == 'useOnSave') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onSave($cn, $metatable->tableName);
            }
        }
        // Step 3 : Save Tran
        if (isset($metatable->tranTable)) {
            foreach ($metatable->tranTable as $tranname => $tranTable) {
                if (!$tranTable->isReverseTran) {
                    $this->saveTran($tranname, $tranTable, $cn, $bo, $bo, $pkid);
                }
            }
        }
    }

    private function save(BoBase $bo, $ac, \PDO $cn) {
        // step 1: save control
        $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];
        $v_id = -1;
        if ($pkid == -1 || $pkid === '') {
            $this->isnew = TRUE;

            // Save Reverse Tran if any            
            if (isset($this->boparser->bometa->controlTable->tranTable)) {
                foreach ($this->boparser->bometa->controlTable->tranTable as $tranname => $tranTable) {
                    if ($tranTable->isReverseTran) {
                        $this->saveTran($tranname, $tranTable, $cn, $bo, $bo, $pkid);
                    }
                }
            }

            // This is insert record
            $companyid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            $bo->company_id = $companyid;
            if ($this->boEventHandler != NULL && $this->boEventHandler instanceof ISequence) {
                $pkid = $this->boEventHandler->generateNewSeqID($cn);
            } elseif ($bo instanceof MastBo) {
                $pkid = EntityManager::getMastSeqID($companyid, $this->boparser->bometa->controlTable->tableName, $cn);
            } else {
                $bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
                $pkid = EntityManager::getDocSeqID($bo->doc_type, $bo->branch_id, $bo->finyear, $this->boparser->bometa->controlTable->tableName, $cn, $v_id);
            }
            $bo[$this->boparser->bometa->controlTable->primaryKey] = $pkid;
            if (array_key_exists('xf', $bo->BOPropertyBag())) {
                $bo['xf'] = $this->encodeXF($bo);
            }
            $cmm = $ac->getInsertCmm();
            foreach (array_keys($cmm->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if (gettype($bo[$prop]) == 'boolean') {
                    $val = (int) $bo[$prop];
                } else {
                    $val = $bo[$prop];
                }
                $cmm->setParamValue($paramName, $val);
                // temp cod eto enable save document. This will override the v_id value to pkid
                if ($prop === "v_id") {
                    $cmm->setParamValue($paramName, $v_id);
                }
            }
            $stmt = $cn->prepare($cmm->getCommandText());
            $stmt->execute($cmm->getParamsForBind());
            $stmt = null;
        } else {
            // This is update record
            if (array_key_exists('xf', $bo->BOPropertyBag())) {
                $bo['xf'] = $this->encodeXF($bo);
            }
            $cmm = $ac->getUpdateCmm();
            foreach (array_keys($cmm->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if (gettype($bo[$prop]) == 'boolean') {
                    $val = (int) $bo[$prop];
                } else {
                    $val = $bo[$prop];
                }
                $cmm->setParamValue($paramName, $val);
            }
            $stmt = $cn->prepare($cmm->getCommandText());
            $stmt->execute($cmm->getParamsForBind());
            $stmt = null;
        }
        $this->generatedKeys[$this->boparser->bometa->controlTable->primaryKey] = $pkid;

        // Step 2: Save Elongated Tables
        foreach ($this->boparser->bometa->controlTable->tableElongs as $met) {
            $this->saveElongatedTable($met, $cn, $bo, $pkid);
        }

        // step 2: save every tran
        if (isset($this->boparser->bometa->controlTable->tranTable)) {
            foreach ($this->boparser->bometa->controlTable->tranTable as $tranname => $tranTable) {
                // Save the Trans
                \yii::beginProfile('save-tran');
                $this->saveTran($tranname, $tranTable, $cn, $bo, $bo, $pkid);
                \yii::endProfile('save-tran');
            }
        }
    }

    private function saveTran($tranname, $tranTable, \PDO $cn, $bo, $parent, $parentPkValue) {
        if ($tranTable->save->method == 'useEntity') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->beforeEntitySave($cn, $tranTable->tableName);
            }
            if ($tranTable->isReverseTran) {
                $tName = $tranTable->tableName;
                $fKey = $tranTable->relation->foreignKey;
                $tranPKField = $tranTable->primaryKey;

                $ac = EntityManager::getInstance()->getActionScripts($tranTable->tableName, $this->connectionType);
                $tran = $bo[$tranname];
                $fkid = $bo[$fKey];
                if ($fkid == -1 or $fkid == '') {// For new
                    $companyid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();

                    $cmmSaveReverseTran = $ac->getInsertCmm();

                    for ($rowIndex = 0; $rowIndex < count($tran->Rows()); $rowIndex++) {
                        $pkid = EntityManager::getMastSeqID($companyid, $tranTable->tableName, $cn);
                        $tran->Rows()[$rowIndex][$tranPKField] = $pkid;
                        foreach (array_keys($cmmSaveReverseTran->getParams()) as $paramName) {
                            $prop = substr_replace($paramName, '', 0, 1);
                            $val = $tran->Rows()[$rowIndex][$prop];

                            $cmmSaveReverseTran->setParamValue($paramName, $val);
                        }
                        $stmt = $cn->prepare($cmmSaveReverseTran->getCommandText());
                        $stmt->execute($cmmSaveReverseTran->getParamsForBind());
                        $stmt = null;
                    }
                    $this->generatedKeys[$fKey] = $pkid;

                    $bo[$fKey] = $pkid;
                } else {// For Edit
                    $cmm = $ac->getUpdateCmm();
                    for ($rowIndex = 0; $rowIndex < count($tran->Rows()); $rowIndex++) {
                        foreach (array_keys($cmm->getParams()) as $paramName) {
                            $prop = substr_replace($paramName, '', 0, 1);
                            if (gettype($tran->Rows()[$rowIndex][$prop]) == 'boolean') {
                                $val = (int) $tran->Rows()[$rowIndex][$prop];
                            } else {
                                $val = $tran->Rows()[$rowIndex][$prop];
                            }
                            $cmm->setParamValue($paramName, $val);
                        }
                        $stmt = $cn->prepare($cmm->getCommandText());
                        $stmt->execute($cmm->getParamsForBind());
                        $stmt = null;
                    }
                }
            } else {
                $tName = $tranTable->tableName;
                $fKey = $tranTable->relation->foreignKey;
                $rfKey = '';
                $tranPKField = $tranTable->primaryKey;
                $rootPkValue = '';
                if (isset($tranTable->relation->rootForeignKey)) {
                    $rfKey = $tranTable->relation->rootForeignKey;
                    $rootPkValue = $bo[$this->boparser->bometa->controlTable->primaryKey];
                    $pkid = $parentPkValue;
                } else {
                    $rootPkValue = $bo[$tranTable->relation->foreignKey];
                    $pkid = $parentPkValue;
                }
                $tranGroup = null;
                if (isset($tranTable->tranGroup)) {
                    $tranGroup = $tranTable->tranGroup;
                }
                $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts($tName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fKey, '', $tranGroup);
                $tran = $parent[$tranname];
//                // First Delete all Transactions based on fkey
//                $cmmDelete = $ac->getDeleteCmm();
//                $cmmDelete->setParamValue(SqlParamType::PARAM_PREFIX.$fKey, $pkid);
//                DataConnect::exeCmm($cmmDelete, $cn);
//                
                // First Delete all Transactions based on fkey
                $this->EntitySaveDeleteTran($cn, $bo, $tName, $tranTable, $pkid);

                // loop and save records
                $tranPkPattern = $tranTable->save->primaryKeyPattern;
                $tranPkPatternType = $tranTable->save->primaryKeyPatternType;
                $tranfieldPattern = $tranTable->save->fieldPattern;
                $tranfieldPatternField = $tranTable->save->fieldPatternField;
                if ($tranfieldPatternField == null) {
                    $tranfieldPatternField = '';
                }
                $tranPkPattern = str_replace("{" . $fKey . "}", $pkid, $tranPkPattern);
                $cmmSaveTran = $ac->getInsertCmm();
                $stmt = $cn->prepare($cmmSaveTran->getCommandText());
                for ($rowIndex = 0; $rowIndex < count($tran->Rows()); $rowIndex++) {
                    foreach (array_keys($cmmSaveTran->getParams()) as $paramName) {
                        $prop = substr_replace($paramName, '', 0, 1);
                        $val = null;
                        if ($prop == $fKey) {
                            $val = $pkid;
                            $tran->Rows()[$rowIndex][$prop] = $val; // Update field value generated on server in BO.
                        } elseif ($prop == $rfKey) {
                            $val = $rootPkValue;
                            $tran->Rows()[$rowIndex][$prop] = $val; // Update field value generated on server in BO.
                        } elseif ($prop === $tranPKField) {
                            $val = $this->replacePattern($tranPkPattern, $rowIndex + 1, $tran->Rows()[$rowIndex], $tranPkPatternType);
                            $tran->Rows()[$rowIndex][$prop] = $val; // Update field value generated on server in BO.
                        } elseif ($prop == $tranfieldPatternField) {
                            $val = $this->replacePattern($tranfieldPattern, $rowIndex + 1, $tran->Rows()[$rowIndex], null);
                            $tran->Rows()[$rowIndex][$prop] = $val; // Update field value generated on server in BO.
                        } elseif ($prop == $tranTable->tranGroup) {
                            $val = $tranname; // Use tran table id (as mentioned in the xmlBO)
                        } else {
                            $val = $tran->Rows()[$rowIndex][$prop];
                        }

                        $cmmSaveTran->setParamValue($paramName, $val);
                    }
                    $stmt->execute($cmmSaveTran->getParamsForBind());

                    // Recursively call save on all child/nested trans
                    if (isset($tranTable->tranTable)) {
                        foreach ($tranTable->tranTable as $childTranName => $childTranMeta) {
                            $this->saveTran($childTranName, $childTranMeta, $cn, $bo, $tran->Rows()[$rowIndex], $tran->Rows()[$rowIndex][$tranPKField]);
                        }
                    }
                }
                $stmt = null;

                // Call elong tables
                foreach ($tranTable->tableElongs as $met) {
                    $this->saveElongatedTranTable($met, $cn, $bo, $tranTable, $tran);
                }
            }
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterEntitySave($cn, $tranTable->tableName);
            }
        } else if ($tranTable->save->method == 'useOnSave') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onSave($cn, $tranTable->tableName);
            }
        } else if ($tranTable->save->method == 'useNamedMethod') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onNamedMethod($cn, $tranTable);
            }
        }
    }

    private function saveElongatedTable(MetaElongateTable $met, \PDO $cn, BoBase $bo, string $pkid) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $this->connectionType);
        $pkfield = $met->id . '_' . $met->primaryKey;
        $fkfield = $met->id . '_' . $met->foreignKey;
        if ($bo[$pkfield] == -1 || $bo[$pkfield] == '') {
            // Insert Record
            $cmm = $as->getInsertCmm();
            foreach (array_keys($cmm->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if ($prop == $met->foreignKey || $prop == $met->primaryKey) {
                    $val = $this->generatedKeys[$this->boparser->bometa->controlTable->primaryKey];
                    $bo[$pkfield] = $val;
                    $bo[$fkfield] = $val;
                } else if ($prop == $met->tranGroup) {
                    $val = $this->boparser->bometa->controlTable->tableName;
                    $bo[$met->id . '_' . $met->tranGroup] = $val;
                } else {
                    if (gettype($bo[$met->id . '_' . $prop]) == 'boolean') {
                        $val = (int) $bo[$met->id . '_' . $prop];
                    } else {
                        $val = $bo[$met->id . '_' . $prop];
                    }
                }
                $cmm->setParamValue($paramName, $val);
            }
            $stmt = $cn->prepare($cmm->getCommandText());
            $stmt->execute($cmm->getParamsForBind());
        } else {
            // Update Record
            $cmm = $as->getUpdateCmm();
            foreach (array_keys($cmm->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if (gettype($bo[$met->id . '_' . $prop]) == 'boolean') {
                    $val = (int) $bo[$met->id . '_' . $prop];
                } else {
                    $val = $bo[$met->id . '_' . $prop];
                }
                $cmm->setParamValue($paramName, $val);
            }
            $stmt = $cn->prepare($cmm->getCommandText());
            $stmt->execute($cmm->getParamsForBind());
        }
    }

    private function saveElongatedTranTable(MetaElongateTable $met, \PDO $cn, BoBase $bo, MetaTranTable $parentMeta, \app\cwf\vsla\data\DataTable $parentTran) {
        $as = EntityManager::getInstance()->getActionScripts($met->tableName, $this->connectionType);
        // First delete all records based on root primary key => foreign key
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From ' . $met->tableName
                . ' Where ' . $met->tranGroup . '=:ptran_group And '
                . $met->foreignKey . '=:pfk_key_id');
        if ($met->useTableID) {// Use parent table id as trangroup if mentioned in boxml 
            $cmm->addParam('ptran_group', $parentMeta->tableID);
        } else {
            $cmm->addParam('ptran_group', $parentMeta->tableName);
        }
        $cmm->addParam('pfk_key_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        DataConnect::exeCmm($cmm, $cn);

        // Loop and save records - We would only insert records as they have been deleted in previous step
        $cmmIn = $as->getInsertCmm();
        $stmt = $cn->prepare($cmmIn->getCommandText());

        foreach ($parentTran->Rows() as $tranRow) {
            foreach (array_keys($cmmIn->getParams()) as $paramName) {
                $prop = substr_replace($paramName, '', 0, 1);
                if ($prop == $met->foreignKey) {
                    $val = $bo[$this->boparser->bometa->controlTable->primaryKey];
                } elseif ($prop == $met->primaryKey) {
                    $val = $tranRow[$parentMeta->primaryKey];
                } elseif ($prop == $met->tranGroup) {
                    if ($met->useTableID) {// Use parent table id as trangroup if mentioned in boxml 
                        $val = $parentMeta->tableID;
                    } else {
                        $val = $parentMeta->tableName;
                    }
                } else {
                    if (gettype($tranRow[$met->id . '_' . $prop]) == 'boolean') {
                        $val = (int) $tranRow[$met->id . '_' . $prop];
                    } else {
                        $val = $tranRow[$met->id . '_' . $prop];
                    }
                }
                $cmmIn->setParamValue($paramName, $val);
            }
            $stmt->execute($cmmIn->getParamsForBind());
        }
    }

    private function EntitySaveDeleteTran($cn, $parent, $tranName, $tranMeta, $rootPkValue) {
        $fkey = '';
        $tran_group = null;
        if (isset($tranMeta->relation->rootForeignKey)) {
            $fkey = $tranMeta->relation->rootForeignKey;
        } else {
            $fkey = $tranMeta->relation->foreignKey;
        }
        if (isset($tranMeta->tranGroup)) {
            $tran_group = $tranMeta->tranGroup;
        }
        $as = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts($tranMeta->tableName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fkey, '', $tran_group);
        $cmmDel = $as->getDeleteCmm();
        if (isset($tranMeta->tranTable)) {
            foreach ($tranMeta->tranTable as $childTranName => $childTranMeta) {
                $this->EntitySaveDeleteTran($cn, $parent, $childTranName, $childTranMeta, $rootPkValue);
            }
        }
        $cmmDel->setParamValue(SqlParamType::PARAM_PREFIX . $fkey, $rootPkValue);
        if (isset($tranMeta->tranGroup)) {
            $cmmDel->setParamValue(SqlParamType::PARAM_PREFIX . $tran_group, $tranMeta->tableID);
        }
        DataConnect::exeCmm($cmmDel, $cn);
    }

    private function replacePattern($tranPkPattern, $rowid, $rowData, $tranPkPatternType) {
        $result = $tranPkPattern;
        $matchCount = preg_match_all("/\{([^}]+)\}/", $tranPkPattern, $matches);
        if ($matchCount > 0) {
            // First look for generated keys
            for ($i = 0; $i < $matchCount; $i++) {
                if (array_key_exists($matches[1][$i], $this->generatedKeys)) {
                    $result = str_replace($matches[0][$i], $this->generatedKeys[$matches[1][$i]], $result);
                }
            }
            // Next look for "{RowID}"
            $result = str_replace("{RowID}", $rowid, $result);
        }

        $matchCount = preg_match_all("/\{([^}]+)\}/", $result, $matches);
        // run the next set to find field values in the current row if matches still exist
        if ($matchCount > 0) {
            // First look for generated keys
            for ($i = 0; $i < $matchCount; $i++) {
                if (array_key_exists($matches[1][$i], $rowData)) {
                    $result = str_replace($matches[0][$i], $rowData[$matches[1][$i]], $result);
                }
            }
        }

        if ($tranPkPatternType != null) {
            if ($tranPkPatternType == "md5") {
                $result = md5($result);
            }
        }
        return $result;
    }

    public function Delete(BoBase $bo) {
        if ($this->access_level == -1 || $this->access_level == 0) {
            throw new \Exception('Item not accessible.');
        }
        $cn = NULL;
        try {
            $cn = DataConnect::getCn($this->connectionType);
            $cn->beginTransaction();

            // step 1: Get primary key
            $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];

            // step 2: First delete records from every tran
            if (isset($this->boparser->bometa->controlTable->tranTable)) {
                foreach ($this->boparser->bometa->controlTable->tranTable as $tranname => $tranTable) {
                    if (!$this->boparser->bometa->controlTable->tranTable[$tranname]->isReverseTran) {
                        $this->deleteTran($tranname, $bo, $cn, $this->boparser->bometa->controlTable, $pkid);
                    }
                }
            }

            // Step 3: Delete from ES table if BOtype is document
            if ($this->boparser->bometa->type === BoType::DOCUMENT) {
                // 
                $cmmes = new \app\cwf\vsla\data\SqlCommand();
                $cmmes->setCommandText('Delete from sys.doc_es where voucher_id=:pvoucher_id');
                $cmmes->addParam('pvoucher_id', $pkid);
                DataConnect::exeCmm($cmmes, $cn);
            }

            // Step 4: Delete from control table
            if ($this->boparser->bometa->controlTable->delete->method == 'useEntity') {
                if ($this->boEventHandler != NULL) {
                    $this->boEventHandler->beforeEntityDelete($cn, $this->boparser->bometa->controlTable->tableName);
                }
                $ac = EntityManager::getInstance()->getActionScripts($this->boparser->bometa->controlTable->tableName, $this->connectionType);
                $cmmcontrol = $ac->getDeleteCmm();
                $cmmcontrol->addParam('p' . $this->boparser->bometa->controlTable->primaryKey, $pkid);
                DataConnect::exeCmm($cmmcontrol, $cn);

                if ($this->boEventHandler != NULL) {
                    $this->boEventHandler->afterEntityDelete($cn, $this->boparser->bometa->controlTable->tableName);
                }
            } elseif ($this->boparser->bometa->controlTable->delete->method == 'useOnDelete') {
                if ($this->boEventHandler != NULL) {
                    $this->boEventHandler->onDelete($cn, $this->boparser->bometa->controlTable->tableName);
                }
            }

            if ($this->boparser->bometa->controlTable->isExtended) {
                $this->deleteExtended($bo, $cn, $this->boparser->bometa->controlTable->extnTable);
            }

            // step 5: Delete records from Reverse Tran Tables
            if (isset($this->boparser->bometa->controlTable->tranTable)) {
                foreach ($this->boparser->bometa->controlTable->tranTable as $tranname => $tranTable) {
                    if ($this->boparser->bometa->controlTable->tranTable[$tranname]->isReverseTran) {
                        $this->deleteTran($tranname, $bo, $cn, $this->boparser->bometa->controlTable, $pkid);
                    }
                }
            }
            // Step 6: Delete from Elongated Control Tables
            foreach ($this->boparser->bometa->controlTable->tableElongs as $met) {
                $this->deleteElongatedTable($met, $cn, $bo, $this->boparser->bometa->controlTable->tableName);
            }
            if ($this->connectionType == DataConnect::COMPANY_DB) {
                // step 6: Delete records from Doc created table
                $this->deleteFromDocCreated($cn, $pkid);

                // step 7: Delete records from Doc workflow table
                $this->deleteFromDocWF($cn, $pkid);
            }
            // Make log entry in audit trail            
            $this->logAction = LogAction::DELETED;

            $cn->commit();
            $cn = null;

            //Run custom After Commit code (if any)
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterDeleteCommit();
            }

            return TRUE;
        } catch (\Exception $ex) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
                return false;
            }
            throw $ex;
        }
    }

    private function deleteTran($tranname, $bo, \PDO $cn, $metatable, $parentPkValue) {
        if (isset($metatable->tranTable[$tranname]->tranTable)) {
            foreach ($metatable->tranTable[$tranname]->tranTable as $childtranname => $tranTable) {
                if (!$metatable->tranTable[$tranname]->tranTable[$childtranname]->isReverseTran) {
                    $this->deleteTran($childtranname, $bo, $cn, $metatable->tranTable[$tranname], $parentPkValue);
                }
            }
        }
        if ($metatable->tranTable[$tranname]->delete->method == 'useEntity') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->beforeEntityDelete($cn, $metatable->tranTable[$tranname]->tableName);
            }
            if ($metatable->tranTable[$tranname]->isReverseTran) {
                // Delete Tran       
                $pKey = $metatable->tranTable[$tranname]->primaryKey;
                $tName = $metatable->tranTable[$tranname]->tableName;
                $fKey = $metatable->tranTable[$tranname]->relation->foreignKey;
                $pkid = $bo[$fKey];
                $ac = EntityManager::getInstance()->getActionScripts($tName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_CONTROL, $fKey);

                $cmmTranDelete = $ac->getDeleteCmm();
                $cmmTranDelete->setParamValue(SqlParamType::PARAM_PREFIX . $pKey, $pkid);
                DataConnect::exeCmm($cmmTranDelete, $cn, $this->connectionType);
            } else {
                // Delete from elong tables
                foreach ($metatable->tranTable[$tranname]->tableElongs as $met) {
                    $this->deleteElongatedTable($met, $cn, $bo, $metatable->tranTable[$tranname]->tableName);
                }
                // Delete Tran            
                $tName = $metatable->tranTable[$tranname]->tableName;
                $fKey = $metatable->tranTable[$tranname]->relation->foreignKey;
                if (isset($metatable->tranTable[$tranname]->relation->rootForeignKey)) {
                    $fKey = $metatable->tranTable[$tranname]->relation->rootForeignKey;
                }
                $tranGroup = null;
                if (isset($metatable->tranTable[$tranname]->tranGroup)) {
                    $tranGroup = $metatable->tranTable[$tranname]->tranGroup;
                }
                $pkid = $parentPkValue;
                $ac = EntityManager::getInstance()->getActionScripts($tName, $this->connectionType, ActionScript::TABLE_TYPE_MASTER_TRAN, $fKey, '', $tranGroup);

                $cmmTranDelete = $ac->getDeleteCmm();
                $cmmTranDelete->setParamValue(SqlParamType::PARAM_PREFIX . $fKey, $pkid);
                if (isset($metatable->tranTable[$tranname]->tranGroup)) {
                    $cmmTranDelete->setParamValue(SqlParamType::PARAM_PREFIX . $tranGroup, $metatable->tranTable[$tranname]->tableID);
                }
                DataConnect::exeCmm($cmmTranDelete, $cn, $this->connectionType);
            }
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterEntityDelete($cn, $metatable->tranTable[$tranname]->tableName);
            }
        } else if ($metatable->tranTable[$tranname]->delete->method == 'useOnDelete') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onDelete($cn, $metatable->tranTable[$tranname]->tableName);
            }
        }
    }

    private function deleteElongatedTable(MetaElongateTable $met, \PDO $cn, BoBase $bo, $tran_group) {
        // First delete all records based on root primary key => foreign key
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From ' . $met->tableName
                . ' Where ' . $met->tranGroup . '=:ptran_group And '
                . $met->foreignKey . '=:pfk_key_id');
        $cmm->addParam('ptran_group', $tran_group);
        $cmm->addParam('pfk_key_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        DataConnect::exeCmm($cmm, $cn);
    }

    private function deleteExtended($bo, \PDO $cn, $metatable) {
        // step 1: Get primary key
        $pkid = $bo[$metatable->primaryKey];

        // step 2: First delete records from every tran
        if (isset($metatable->tranTable)) {
            foreach ($metatable->tranTable as $tranname => $tranTable) {
                if (!$metatable->tranTable[$tranname]->isReverseTran) {
                    $this->deleteTran($tranname, $bo, $cn, $metatable, $pkid);
                }
            }
        }

        // Step 3: Delete from ES table if BOtype is document
        if ($this->boparser->bometa->type === BoType::DOCUMENT) {
            // 
            $cmmes = new \app\cwf\vsla\data\SqlCommand();
            $cmmes->setCommandText('Delete from sys.doc_es where voucher_id=:pvoucher_id');
            $cmmes->addParam('pvoucher_id', $pkid);
            DataConnect::exeCmm($cmmes, $cn);
        }

        if ($metatable->delete->method == 'useEntity') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->beforeEntityDelete($cn, $metatable->tableName);
            }
            // Step 4: Delete from control table
            $ac = EntityManager::getInstance()->getActionScripts($metatable->tableName, $this->connectionType);
            $cmmcontrol = $ac->getDeleteCmm();
            $cmmcontrol->addParam('p' . $metatable->primaryKey, $pkid);
            DataConnect::exeCmm($cmmcontrol, $cn);
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->afterEntityDelete($cn, $metatable->tableName);
            }
        } elseif ($metatable->delete->method == 'useOnDelete') {
            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onDelete($cn, $metatable->tableName);
            }
        }

        // step 4: Delete records from Reverse Tran Tables
        if (isset($metatable->tranTable)) {
            foreach ($metatable->tranTable as $tranname => $tranTable) {
                if ($metatable->tranTable[$tranname]->isReverseTran) {
                    $this->deleteTran($tranname, $bo, $cn, $metatable, $pkid);
                }
            }
        }

        if ($metatable->isExtended) {
            $this->deleteExtended($bo, $cn, $metatable->extnTable);
        }
    }

    public function Archive(BoBase $bo, $helperOption, $action, $msg) {
        if ($this->access_level == -1 || $this->access_level == 0) {
            throw new \Exception('Item not accessible.');
        }
        if ($action != 'C' && $action != 'O') {
            return FALSE;
        }
        $cn = NULL;
        try {
            $cn = DataConnect::getCn($this->connectionType);
            $cn->beginTransaction();

            if ($this->boEventHandler != NULL) {
                $this->boEventHandler->onArchive($cn, $action);
            }

            if ($msg == '') {
                if ($action == 'C') {
                    $msg = 'Document archived';
                } elseif ($action == 'O') {
                    $msg = 'Document unarchived';
                }
            }

            $cmm = new\app\cwf\vsla\data\SqlCommand();
            $cmmtext = 'select * from sys.sp_doc_wf_archive(:pdoc_id, :pbranch_id, :pbo_id, :pedit_view, :pdoc_name, :pdoc_sender_comment, :puser_id_from, 
                            :pdoc_action, :puser_id_to, :pdoc_stage_id_from, :pdoc_stage_id);';
            $cmm->setCommandText($cmmtext);
            $cmm->addParam('pdoc_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
            $cmm->addParam('pbranch_id', $bo->branch_id);
            $cmm->addParam('pbo_id', $this->boparser->bometa->id);
            $cmm->addParam('pedit_view', $helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->formName . '.xml');
            $cmm->addParam('pdoc_name', $this->boparser->bometa->id);
            $cmm->addParam('pdoc_sender_comment', $msg);
            $cmm->addParam('puser_id_from', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $cmm->addParam('pdoc_action', $action);
            $cmm->addParam('puser_id_to', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $cmm->addParam('pdoc_stage_id_from', '');
            $cmm->addParam('pdoc_stage_id', '');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

            // Make log entry in audit trail   
            if ($action == 'C') {
                $this->logAction = LogAction::ARCHIVED;
            } elseif ($action == 'O') {
                $this->logAction = LogAction::UNARCHIVED;
            }

            $cn->commit();
            $cn = null;

            return TRUE;
        } catch (\Exception $ex) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
                return false;
            }
            throw $ex;
        }
    }

    private function GetES(BoBase $bo) {
        // Fetch ES if  BoType is Document   
        if ($this->boparser->bometa->type === BoType::DOCUMENT) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from sys.doc_es where voucher_id=:pvoucher_id');
            $cmm->addParam('pvoucher_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
            $dtes = DataConnect::getData($cmm);
            if (count($dtes->Rows()) > 0) {
                if ($dtes->Rows()[0]['entered_on'] != NULL) {
                    $bo['entered_by'] = $dtes->Rows()[0]['entered_by'] . ' On ' . \app\cwf\vsla\utils\FormatHelper::FormatDateWithTimeForDisplay($dtes->Rows()[0]['entered_on']);
                } else {
                    $bo['entered_by'] = '';
                }
                if ($dtes->Rows()[0]['posted_on'] != NULL) {
                    $bo['posted_by'] = $dtes->Rows()[0]['posted_by'] . ' On ' . \app\cwf\vsla\utils\FormatHelper::FormatDateWithTimeForDisplay($dtes->Rows()[0]['posted_on']);
                } else {
                    $bo['posted_by'] = '';
                }
            }
        }
    }

    public function CreateLogEntry(BoBase $bo, $logAction, $json_log) {
        if ($this->access_level == -1 || $this->access_level == 0) {
            throw new \Exception('Item not accessible.');
        }
        $cn = DataConnect::getCnAuditDB($this->connectionType);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.sp_aud_log_add(:pmaster_type, :pmaster_id, :pvoucher_id, :pen_log_action, :puser_name, :pjson_log, :pmachine_name, :pcustom_action_desc)');
        $cmm->addParam('pmaster_type', strtolower($this->boparser->bometa->id));
        if ($this->boparser->bometa->type === BoType::DOCUMENT) {
            $cmm->addParam('pvoucher_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
            $cmm->addParam('pmaster_id', -1);
        } else {
            $cmm->addParam('pvoucher_id', '');
            $cmm->addParam('pmaster_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        }
        $cmm->addParam('pen_log_action', $logAction);
        $cmm->addParam('puser_name', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUserName());
        $cmm->addParam('pjson_log', $json_log);
        $cmm->addParam('pmachine_name', gethostname());
        $cmm->addParam('pcustom_action_desc', $this->getCustomActionDesc($logAction));
        DataConnect::exeCmm($cmm, $cn);
    }

    public function CreateWarningEntry(BoBase $bo, $logAction, $json_log) {
        if ($this->access_level == -1 || $this->access_level == 0) {
            throw new \Exception('Item not accessible.');
        }

        $log_id = EntityManager::getMastSeqID(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id')
                        , 'sys.doc_warning', DataConnect::getCn(DataConnect::COMPANY_DB));

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('insert into sys.doc_warning(log_id, document_type, voucher_id, en_log_action, user_name, 
                                    machine_name, json_log, warning_desc, custom_action_desc)
                                values(:plog_id,:pdocument_type, :pvoucher_id, :pen_log_action, :puser_name, 
                                    :pmachine_name, :pjson_log, :pwarning_desc, :pcustom_action_desc)');
        $cmm->addParam('plog_id', $log_id);
        $cmm->addParam('pdocument_type', strtolower($this->boparser->bometa->id));
        $cmm->addParam('pvoucher_id', $bo[$this->boparser->bometa->controlTable->primaryKey]);
        $cmm->addParam('pen_log_action', $logAction);
        $cmm->addParam('puser_name', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUserName());
        $cmm->addParam('pjson_log', $json_log);
        $cmm->addParam('pmachine_name', gethostname());
        $cmm->addParam('pcustom_action_desc', $this->getCustomActionDesc($logAction));
        $cmm->addParam('pwarning_desc', implode(' ; ', $bo->getWarnings()));
        DataConnect::exeCmm($cmm);
    }

    private function getCustomActionDesc($logAction) {
        if ($logAction == LogAction::CREATED) {
            return 'Created';
        }
        if ($logAction == LogAction::DELETED) {
            return 'Deleted';
        }
        if ($logAction == LogAction::POSTED) {
            return 'Posted';
        }
        if ($logAction == LogAction::SAVEDOREDITED) {
            return 'SavedOrEdited';
        }
        if ($logAction == LogAction::UNPOSTED) {
            return 'Unposted';
        }
        if ($logAction == LogAction::ARCHIVED) {
            return 'Archived';
        }
        if ($logAction == LogAction::UNARCHIVED) {
            return 'Unarchived';
        }
        return '';
    }

    private function setDocWorkflow(DocBo $bo, \PDO $cn, \app\cwf\vsla\workflow\WfOption $wfOption) {
        // Fill the balance option properties
        $wfOption->branch_id = $bo->branch_id;
        $wfOption->bo_id = $this->boparser->bometa->id;
        $wfOption->doc_id = $bo[$this->boparser->bometa->controlTable->primaryKey];
        $wfOption->doc_name = $this->boparser->bometa->id;
        $wfOption->doc_sent_on = date('Y-m-d H:i:s');
        $wfOption->user_id_from = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
        $docwf = new \app\cwf\vsla\workflow\DocWorkflow();
        $wfresult = $docwf->moveDoc($wfOption, $cn);
        if ($wfresult->status != 'OK') {
            throw new \Exception($wfresult->message);
        }
    }

    private function setDocCreated(DocBo $bo, \PDO $cn, string $doc_id, int $newStatus) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_doc_created(:pdoc_id, :pbranch_id, :pbo_id, :puser_id_created, :pdoc_status)');
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('pbranch_id', $bo->branch_id);
        $cmm->addParam('pbo_id', $bo['__bo']);
        $cmm->addParam('puser_id_created', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pdoc_status', $newStatus);
        DataConnect::exeCmm($cmm, $cn);
    }

    private function deleteFromDocCreated(\PDO $cn, string $doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From sys.doc_created Where doc_id=:pdoc_id');
        $cmm->addParam('pdoc_id', $doc_id);
        DataConnect::exeCmm($cmm, $cn);
    }

    private function deleteFromDocWF(\PDO $cn, string $doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete From sys.doc_wf Where doc_id=:pdoc_id');
        $cmm->addParam('pdoc_id', $doc_id);
        DataConnect::exeCmm($cmm, $cn);
    }

    private function decodeXF(BoBase $bo, $xf) {
        $xfvals = json_decode($xf, TRUE);
        foreach ($xfvals as $key => $value) {
            $bo[$key] = DataAdapter::getDBtoPHPDataValue('string', $value);
        }
    }

    private function encodeXF(BoBase $bo) {
        $xfval = [];
        foreach ($bo->BOPropertyBag() as $key => $value) {
            if (strpos($key, 'xf_') !== FALSE) {
                $xfval[$key] = $value;
            }
        }
        return $xfval;
    }

    private function getLastDocStage(): string {
        $docStages = $this->boparser->bometa->docStageInfo;
        $last_stage = end($docStages);
        if ($last_stage) {
            $last_but_one = prev($docStages);
            if ($last_but_one) {
                return $last_but_one['id'];
            }
        }
        return '';
    }

    public function callEventHandlerMethod(string $methodName) {
        $this->boEventHandler->$methodName();
    }

    public function matchTimestamp(DocBo $bo, \app\cwf\vsla\workflow\WfOption $wfOption) {
        if ($bo[$this->boparser->bometa->controlTable->primaryKey] == '' || $bo[$this->boparser->bometa->controlTable->primaryKey] == -1) {
            return true;
        }
        $pkfield = $this->boparser->bometa->controlTable->primaryKey;
        $ctable = $this->boparser->bometa->controlTable->tableName;
        $pkid = $bo[$this->boparser->bometa->controlTable->primaryKey];
        $sql = "Select $pkfield, status From $ctable Where $pkfield = :pkid And status = :pstatus And last_updated = :plu";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam("pkid", $pkid);
        $cmm->addParam("pstatus", $bo->status);
        $cmm->addParam("plu", $bo->last_updated);
        $dt = DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            // In the case of a posted document, the only action allowed is unpost
            if ($dt->Rows()[0]['status'] == DocBo::STATUS_POSTED && $wfOption->doc_action != \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                return false;
            }
            if ($bo->status == DocBo::STATUS_POSTED && $wfOption->doc_action != \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function isNewDocument($bo) {
        if ($bo[$this->boparser->bometa->controlTable->primaryKey] == '' || $bo[$this->boparser->bometa->controlTable->primaryKey] == -1) {
            return true;
        }
        return false;
    }

}
