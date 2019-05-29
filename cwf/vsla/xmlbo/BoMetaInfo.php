<?php

namespace app\cwf\vsla\xmlbo {

    class BoMetaInfo {
        public $id;
        /** @var BoType **/
        public $type;
        public $connectionType;
        public $wfType;
        /** @var MetaTable **/
        public $controlTable;
        public $docDelete;
        public $docStageInfo = [];
    }

    class MetaTable {
        public $primaryKey;
        public $basePrimaryKey;
        public $tableName;
        public $tableID;
        public $tranGroup;
        public $extendPath;
        public $isExtended = FALSE;
        /** @var MetaTable **/
        public $extnTable;
        /** @var SaveInfo **/
        public $fetchOrCreate;
        /** @var SaveInfo **/
        public $save;
        /** @var DocDeleteInfo **/
        public $delete;
        
        /****/
        public $unpost;       
        
         /** @var CustomColumns[] **/
        public $customColumns;
        
        /** @var JsonFieldMeta[] */
        public $jsonFields;
        
        /** @var CustomColumns[] **/
        public $extnColumns;
        
        /** @var MetaTranTable[] **/
        public $tranTable;
        
        /** @var ElongateTable[] */
        public $tableElongs;
    }
    
    class MetaTranTable extends MetaTable {
        /** @var RelationInfo **/
        public $relation;
        public $isReverseTran = FALSE;
        /** @var JsonFieldMeta[] */
        public $jsonFields;
    }
    
    class MetaElongateTable {
        public $id;
        public $tableName;
        public $primaryKey;
        public $foreignKey;
        public $tranGroup;
    }
    
    class BoType {
        const MASTER="Master";
        const DOCUMENT="Document";
    }
    
    class WfType {
        const WF_SINGLE_STAGE="singleStage";
        const WF_MULTI_STAGE="multiStage";
    }
    
    class SaveInfo {
        /** @var MethodInfo **/
        public $method;
        public $primaryKeyPattern; 
        public $primaryKeyPatternType;        
        public $fieldPattern;
        public $fieldPatternField;
        public $code;
        public $orderby;
    }
    class DocDeleteInfo {        
        public $excludeTables;
        public $sarrogateFields;
        public $dependantTables;
    }
    
    class MethodInfo {
        const USEENTITY = "UseEntity";
        const USECODE = "UseCode";
    }
    
    class RelationInfo {
        /** @var LinkType **/
        public $linkType;
        public $foreignKey;
        public $rootForeignKey;
    }
    
    class LinkType {
        const ONETOONE = "OneToOne";
        const ONETOMANY = "OneToMany";
        const MANYTOMANY = "ManyToMany";
        const MANYTOONE = "ManyToOne";
    }
    
    class LogAction {
        const CREATED = 0;
        const SAVEDOREDITED = 1;
        const POSTED = 2;
        const UNPOSTED = 3;
        const DELETED = 4;
        const UNARCHIVED = 8;
        const ARCHIVED = 9;
    }
    
    class CustomColumn {
        public $name;
        public $type;
        public $length;
        public $scale;
    }
    
    class JsonFieldMeta {
        public $id;
        /** @var JFieldMeta[] */
        public $jfields = [];
        /** @var JObjectMeta[] */
        public $jobjects = [];
    }
    
    class JFieldMeta {
        const STRING_TYPE = 'string';
        const DECIMAL_TYPE = 'decimal';
        const INTEGER_TYPE = 'int';
        const DATE_TYPE = 'date';
        const ARRAY_TYPE = 'array'; 
        const BOOL_TYPE = 'bool';
        
        public $name;
        public $type;
        public $length;
        public $scale;
    }    
    
    class JObjectMeta {
        const SIMPLE_TYPE = 'simple';
        const ARRAY_TYPE = 'array';  
        
        public $name;
        public $type;
        
        /** @var JFieldMeta[] */
        public $jfields = [];
        
        /** @var JObjectMeta[] */
        public $jobjects = [];        
    }
}