<?php

namespace app\cwf\vsla\xmlbo;

use app\cwf\vsla\xmlbo\BoMetaInfo;
use app\cwf\vsla\xmlbo\MetaTable;
use app\cwf\vsla\xmlbo\MetaTranTable;
use app\cwf\vsla\xmlbo\MetaElongateTable;
use app\cwf\vsla\xmlbo\SaveInfo;
use app\cwf\vsla\xmlbo\RelationInfo;

class BoParser {

    private $boxml;

    /** @var BoMetaInfo * */
    public $bometa;

    function __construct($boxml) {//$boxmlpath
        $this->boxml = $boxml; //simplexml_load_file($boxmlpath);
        $this->init();
    }

    private function init() {
        $this->bometa = new BoMetaInfo();
        $this->bometa->id = (string) $this->boxml['id'];
        $this->bometa->type = (string) $this->boxml['type'];
        $this->bometa->connectionType = \app\cwf\vsla\data\DataConnect::COMPANY_DB; // connection always defaults to companydb
        if ($this->boxml->connectionType) {
            if ($this->boxml->connectionType->mainDB) {
                $this->bometa->connectionType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
            }
        }
        if (isset($this->boxml["wfType"])) {
            $this->bometa->wfType = (string) $this->boxml["wfType"];
            if ($this->bometa->wfType == WfType::WF_MULTI_STAGE) {
                if (!isset($this->boxml->docStageInfo)) {
                    throw new \Exception('Multi Stage document requires DocStageInfo. Information missing in bo-xml');
                }
                foreach ($this->boxml->docStageInfo->stage as $stage) {
                    $this->bometa->docStageInfo[] = ['id' => (string) $stage["id"], 'desc' => (string) $stage["desc"], 'state' => FALSE];
                }
            }
        } else {
            $this->bometa->wfType = WfType::WF_SINGLE_STAGE;
        }
        $this->bometa->controlTable = $this->resolveExtends($this->boxml);
        $this->bometa->docDelete = $this->getdocumentdeleteinfo($this->boxml, NULL);
    }

    private function resolveExtends($boxml) {
        // First create stack of inheritance hierarchy
        $stack = new \SplStack();
        $stack->push($boxml);
        while (true) {
            $cboxml = $stack->top();
            if (isset($cboxml['extends'])) {
                $cwFramework = simplexml_load_file((string) $cboxml['extends'] . '.xml');
                $stack->push($cwFramework->businessObject);
            } else {
                break;
            }
        }
        // start from top, this would be the base/root bo
        $cbo = $stack->pop();
        $mtable = $this->getmetainfo($cbo, null);
        $parentTable = $mtable;
        While ($stack->count() > 0) {
            $cbo = $stack->pop();
            $parentTable = $this->getmetainfo($cbo, $parentTable);
        }
        return $mtable;
    }

    private function getmetainfo($boxml, $parentTable) {
        if ($parentTable == null) {
            $mtable = $this->gettableinfo($boxml->controlTable);
        } else {
            $parentTable->isExtended = true;
            $parentTable->extendPath = (string) $boxml['extends'] . '.xml';
            $parentTable->extnTable = $this->gettableinfo($boxml->controlTable);
            $mtable = $parentTable->extnTable;
        }
        $mtable->extnColumns = $this->getextncols();
        return $mtable;
    }

//    private function getmetainfo($boxml){
//        $mtable=new MetaTable();
//        $temptable=  $this->gettableinfo($boxml->controlTable);
//        if(isset($boxml['extends'])){
//            $cwFramework=simplexml_load_file((string)$boxml['extends'].'.xml');
//            $mtable=$this->getmetainfo($cwFramework->businessObject);
//            $mtable->isExtended=TRUE;
//            $mtable->extendPath=(string)$boxml['extends'].'.xml';
//            $mtable->extnTable= $temptable;
//        }else{
//            $mtable=$temptable;
//        }
//        return $mtable;
//    }

    private function getdocumentdeleteinfo($boxml, $di) {
        if ($di == null) {
            $di = new DocDeleteInfo();
        }
        if ($boxml->validateBeforeDelete) {
            if ($boxml->validateBeforeDelete->excludeTables) {
                if ($di->excludeTables == '') {
                    $di->excludeTables = (string) $boxml->validateBeforeDelete->excludeTables;
                } else {
                    $di->excludeTables = $di->excludeTables . ',' . (string) $boxml->validateBeforeDelete->excludeTables;
                }
            }
            if ($boxml->validateBeforeDelete->sarrogateFields) {
                if ($di->sarrogateFields == '') {
                    $di->sarrogateFields = (string) $boxml->validateBeforeDelete->sarrogateFields;
                } else {
                    $di->sarrogateFields = $di->sarrogateFields . ',' . (string) $boxml->validateBeforeDelete->sarrogateFields;
                }
            }
            if ($boxml->validateBeforeDelete->dependantTables) {
                if ($di->dependantTables == '') {
                    $di->dependantTables = (string) $boxml->validateBeforeDelete->dependantTables;
                } else {
                    $di->dependantTables = $di->dependantTables . ',' . (string) $boxml->validateBeforeDelete->dependantTables;
                }
            }
        }
        if (isset($boxml['extends'])) {
            $cwFramework = simplexml_load_file((string) $boxml['extends'] . '.xml');
            $di = $this->getdocumentdeleteinfo($cwFramework->businessObject, $di);
        }
        return $di;
    }

    private function gettableinfo($table) {
        if ($table->getName() === 'controlTable') {
            $mtable = new MetaTable();
        } else if ($table->getName() === 'tranTable' || $table->getName() === 'reverseTranTable') {
            $mtable = new MetaTranTable();
        }
        $mtable->tableName = (string) $table->tableName;
        if((string) $table->attributes()->id){
            $mtable->tableID = (string) $table->attributes()->id;
        }
        if(isset($table->attributes()->tranGroup)){
            $mtable->tranGroup = (string) $table->attributes()->tranGroup;
        }
        $mtable->primaryKey = (string) $table->primaryKey;
        if (isset($table->basePrimaryKey) && $table->basePrimaryKey !== null && (string) $table->basePrimaryKey !== '') {
            $mtable->basePrimaryKey = (string) $table->basePrimaryKey;
        }
        $mtable->fetchOrCreate = $this->getmethodinfo($table->fetchOrCreate);
        if (($mtable instanceof MetaTranTable) &&
                ($mtable->fetchOrCreate->orderby === NULL || $mtable->fetchOrCreate->orderby === '')) {
            //$mtable->fetchOrCreate->orderby = $mtable->primaryKey;
//            throw new \Exception('Order by attribute is not set in fetchOrCreate for ' . $mtable->tableName);
        }
        $mtable->save = $this->getmethodinfo($table->save);
        if (isset($table->delete)) {
            $mtable->delete = $this->getmethodinfo($table->delete);
        }
        if (isset($table->relation)) {
            $mtable->relation = $this->getrelation($table->relation);
        }
        $mtable->tableElongs = [];
        foreach ($table->tableElongate as $elongTable) {
            $et = new MetaElongateTable();
            $et->id = (string) $elongTable['id'];
            $et->tableName = (string) $elongTable['tableName'];
            $et->primaryKey = (string) $elongTable['primaryKey'];
            $et->foreignKey = (string) $elongTable['foreignKey'];
            $et->tranGroup = (string) $elongTable['tranGroup'];
            $et->useTableID = (bool) $elongTable['useTableID'];
            $mtable->tableElongs[$et->id] = $et;
        }
        foreach ($table->tranTable as $tranTable) {
            $mtable->tranTable[(string) $tranTable['id']] = $this->gettableinfo($tranTable);
//            if($mtable instanceof MetaTranTable){
//                $mtable->customColumns[(string)$tranTable['id']]=  $this->gettrancol((string)$tranTable['id']);
//            }
        }
        foreach ($table->reverseTranTable as $tranTable) {
            $mtable->tranTable[(string) $tranTable['id']] = $this->gettableinfo($tranTable);
            $mtable->tranTable[(string) $tranTable['id']]->isReverseTran = TRUE;
//            $mtable->customColumns[(string)$tranTable['id']]=  $this->gettrancol((string)$tranTable['id']);
        }

        if (isset($table->customColumns)) {
            foreach ($table->customColumns->customColumn as $customColumn) {
                $var = $this->getcustomcol($customColumn);
                $mtable->customColumns[(string) $customColumn['name']] = $var;
            }
        }

        foreach ($table->jsonField as $xjsonField) {
            $jf = $this->load_JsonField($xjsonField);
            $mtable->jsonFields[$jf->id] = $jf;
        }

        return $mtable;
    }

    private function getmethodinfo($operation) {
        $sv = new SaveInfo();
        $sv->orderby = '';
        if (isset($operation->children()[0]['orderby'])) {
            $sv->orderby = (string) $operation->children()[0]['orderby'];
        }
        $sv->method = $operation->children()[0]->getName();
        if (isset($operation->primaryKeyPattern)) {
            if (isset($operation->primaryKeyPattern['type'])) {
                $sv->primaryKeyPatternType = (string) $operation->primaryKeyPattern['type'];
            }
            $sv->primaryKeyPattern = (string) $operation->primaryKeyPattern;
        }
        if (isset($operation->fieldPattern)) {
            if (isset($operation->fieldPattern['field'])) {
                $sv->fieldPatternField = (string) $operation->fieldPattern['field'];
            }
            $sv->fieldPattern = (string) $operation->fieldPattern;
        }
        if ($sv->method === MethodInfo::USECODE) {
            $sv->code = $operation->code;
        }
        return $sv;
    }

    private function getrelation($xreln) {
        $rel = new RelationInfo();
        $rel->linkType = (string) $xreln['linkType'];
        $rel->foreignKey = (string) $xreln->foreignKey;
        if (isset($xreln->rootForeignKey)) {
            $rel->rootForeignKey = (string) $xreln->rootForeignKey;
        }
        return $rel;
    }

    private function getcustomcol($customcolumns) {
        $cc = new CustomColumn();
        $cc->name = (string) $customcolumns['name'];
        $cc->type = (string) $customcolumns['type'];
        if (isset($customcolumns['length'])) {
            $cc->length = (int) $customcolumns['length'];
        } else {
            $cc->length = 0;
        }

        if (isset($customcolumns['scale'])) {
            $cc->scale = (int) $customcolumns['scale'];
        } else {
            $cc->scale = 0;
        }
        return $cc;
    }

    private function getextncols() {
        $extn_cols = [];
        $cn = \app\cwf\vsla\security\SessionManager::getSessionVariable('companyDB');
        if (isset($cn) && $cn != '') {
            $boid = md5($this->bometa->id);
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText('Select * from sys.entity_extn where bo_id = :pbo_id::uuid');
            $cmd->addParam('pbo_id', $boid);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmd);
            if (count($dt->Rows()) == 1) {
                $xextn_fields = $dt->Rows()[0]['extn_info'];
                $extn_fields = simplexml_load_string($xextn_fields);
                foreach ($extn_fields->field as $field) {
                    $ec = new CustomColumn();
                    $ec->name = (string) $field->attributes()['id'];
                    $ec->type = (string) $field->attributes()['type'];
                    if (isset($customcolumns['length'])) {
                        $ec->length = (int) $customcolumns['length'];
                    } else {
                        $ec->length = 0;
                    }

                    if (isset($customcolumns['scale'])) {
                        $ec->scale = (int) $customcolumns['scale'];
                    } else {
                        $ec->scale = 0;
                    }

                    $extn_cols[] = $ec;
                }
            }
        }
        return $extn_cols;
    }

    private function gettrancol($tran_id) {
        $cc = new CustomColumn();
        $cc->name = $tran_id;
        $cc->type = 'carray';
        $cc->length = 6;
        $cc->scale = 0;
        return $cc;
    }

    private function load_JsonField(\SimpleXMLElement $xjsonField): JsonFieldMeta {
        $JF = new JsonFieldMeta();
        $JF->id = (string) $xjsonField['id'];
        foreach ($xjsonField->jfield as $xjfield) {
            $j = $this->load_JField($xjfield);
            $JF->jfields[$j->name] = $j;
        }
        foreach ($xjsonField->jobject as $xjobject) {
            $jo = $this->load_JObject($xjobject);
            $JF->jobjects[$jo->name] = $jo;
        }
        return $JF;
    }

    private function load_JField(\SimpleXMLElement $xjfield): JFieldMeta {
        $j = new JFieldMeta();
        $j->name = (string) $xjfield['name'];
        $j->type = (string) $xjfield['type'];
        if (isset($xjfield['length'])) {
            $j->length = (int) $xjfield['length'];
        }
        if (isset($xjfield['scale'])) {
            $j->scale = (int) $xjfield['scale'];
        }
        return $j;
    }

    private function load_JObject(\SimpleXMLElement $xjobject): JObjectMeta {
        $jo = new JObjectMeta();
        $jo->name = (string) $xjobject['name'];
        $jo->type = (string) $xjobject['type'];
        foreach ($xjobject->jfield as $xjfield) {
            $j = $this->load_JField($xjfield);
            $jo->jfields[$j->name] = $j;
        }
        foreach ($xjobject->jobject as $xjochild) {
            $jochild = $this->load_JObject($xjochild);
            $jo->jobjects[$jochild->name] = $jochild;
        }
        return $jo;
    }

}
