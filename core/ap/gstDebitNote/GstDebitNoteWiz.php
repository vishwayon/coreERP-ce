<?php

namespace app\core\ap\gstDebitNote;

use YaLinqo\Enumerable;

class GstDebitNoteWiz extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectSupplier':
                $this->setSelectSupplier($data);
                break;
            case 'SelectBill':
                $this->setSelectBill($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectSupplier($data) {
        if ($data->SelectSupplier->supplier_id == -1) {
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }

        $this->data['SelectSupplier'] = array();
        if ($data->SelectSupplier->supplier_id != -1) {
            $this->data['SelectSupplier']['supplier_id'] = $data->SelectSupplier->supplier_id;
            $this->data['SelectSupplier']['from_date'] = $data->SelectSupplier->from_date;
            $this->data['SelectSupplier']['to_date'] = $data->SelectSupplier->to_date;
            $this->data['SelectSupplier']['dcn_type'] = $data->SelectSupplier->dcn_type;
        }
    }

    private function setSelectBill($data) {
        $this->data['SelectBill'] = array();

        $lst = Enumerable::from($data->SelectBill)->where('$a==>$a->selected==true')->toList();
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select atleast one Invoice to proceed.');
        }
        
        $lst = Enumerable::from($data->SelectBill)->where('$a==>$a->selected==true')
                ->groupBy('$a==>$a->bill_id')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Multiple Bills cannot be selected in a single Debit Note.');
        }
        foreach ($data->SelectBill as $m) {
            if ((bool) $m->selected === true) {
                array_push($this->data['SelectBill'], $m);
            }
        }
    }

}
