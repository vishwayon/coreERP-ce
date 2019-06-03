<?php

namespace app\cwf\vsla\xmlbo;

abstract class WizardBase {

    protected $data;
    public $brokenrules = array();
    public $status = 'OK';

    public function __construct() {
        $this->data = [];
    }

    public function setData($step, $data, $oldStepData) {
        if (count($this->brokenrules) > 0) {
            $this->status = 'ERROR';
        }
    }

    public function getParamValue(\app\cwf\vsla\ui\wizSqlParam $param) {
        $parent = $this->data[$param->step];
        $temp = $param->property;
        $res = $parent->$temp;
        return $res;
    }

    public function getData() {
        return $this->data;
    }

}
