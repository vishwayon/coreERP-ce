<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\data;

/**
 * This class represents an json, jsonb field
 * defined in postgresql
 * @author girish
 */
class JsonField implements \JsonSerializable {
    //put your code here
    
    /**
     * Represents the column name in the database
     * This would also be used as prefix for all inner object names in 
     * the Json field
     * @var string
     */
    public $colName = '';
    private $value;
    /** @var \app\cwf\vsla\xmlbo\JsonFieldMeta */
    private $metaInfo;
    
    public function __construct($colName) {
        $this->colName = $colName;
        $this->value = json_decode('{}');
    }

    /**
     * Gets the json object as a well defined class
     * @return {}
     */
    public function &Value() {
        return $this->value;
    } 
    
    /**
     * Set the json instance value
     * Should be used only while constructing the field
     * It should be of a class type with public properties
     * @param mixed $value
     */
    public function set_value($value) {
        $this->value = $value;
    }
    
    /**
     * Set the meta info for the field. 
     * This is mentioned as part of xmlBo
     * @param \app\cwf\vsla\xmlbo\JsonFieldMeta $minfo
     */
    public function set_metaInfo($minfo) {
        $this->metaInfo = $minfo;
    }  
    
    /**
     * Get the Array Field meta info
     * @return \app\cwf\vsla\xmlbo\JsonFieldMeta
     */
    public function get_metaInfo() {
        return $this->metaInfo;
    }

    /**
     * Returns the field value as a json serialised object
     * @return {}
     */
    public function jsonSerialize() {
        return $this->value;
    }
        
    /**
     * Constructs and returns a string that can be saved to database
     * { item1, item2, ...}
     */
    public function get_dbvalue() {
        return json_encode($this->value);
    }
    
    /**
     * parses the database Json value and returns instance of JsonField
     * @param string $fieldName
     * @param string $fieldValue
     */
    public static function parse_dbvalue($fieldName, $fieldValue) {
        $inst = new JsonField($fieldName);
        $inst->set_value(json_decode($fieldValue));        
        return $inst;
    }
}