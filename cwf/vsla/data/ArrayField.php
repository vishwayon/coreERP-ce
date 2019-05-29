<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\data;

/**
 * This class represents an array field
 * defined in postgresql
 * @author girish
 */
class ArrayField implements \JsonSerializable {

    //put your code here

    public $column = null;
    private $items = [];
    public $serializeAsArray = false;

    public function __construct($phpType = DataAdapter::PHPDATA_TYPE_STRING) {
        $this->column = new DataColumn('item_value', $phpType, DataAdapter::getPHPDataTypeDefault($phpType));
    }

    /**
     * Gets the items array
     * @return []
     */
    public function &Items() {
        return $this->items;
    }

    public function addItem($newItem) {
        array_push($this->items, $newItem);
    }

    public function removeItem($index) {
        unset($this->items[$index]);
    }

    public function resetItems($items) {
        $this->items = $items;
    }

    public function clearItems() {
        $this->items = [];
    }

    /**
     * Returns an array of anonymous class with key: item_value => value: actual value
     * @return []
     */
    public function jsonSerialize() {
        if ($this->serializeAsArray) {
            return $this->items;
        } else {
            $result = [];
            foreach ($this->items as $item) {
                $ic = new ArrayFieldItem();
                $ic->item_value = $item;
                $result[] = $ic;
            }
            return $result;
        }
    }

    /**
     * Constructs and returns a string that can be saved to database
     * { item1, item2, ...}
     */
    public function get_dbvalue() {
        return '{' . self::str_putcsv($this->items, ',', '"') . '}';
    }

    /**
     * parses a database array and returns an instance of ArrayField
     * @param string $fieldvalue
     */
    public static function parse_dbvalue($dbValue, $dbType) {
        $inst = new ArrayField(DataAdapter::getDBtoPHPDataType($dbType));
        if (strlen($dbValue) > 2) {
            $tvals = str_getcsv(substr($dbValue, 1, strlen($dbValue) - 2), ",", '"');
            foreach ($tvals as $val) {
                $inst->items[] = DataAdapter::getDBtoPHPDataValue($dbType, $val);
            }
        }
        return $inst;
    }

    public static function str_putcsv($input, $delimiter = ',', $enclosure = '"') {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);
        return $data;
    }

}

class ArrayFieldItem {

    public $item_value;

}
