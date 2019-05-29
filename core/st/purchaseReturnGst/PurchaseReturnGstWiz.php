<?php

namespace app\core\st\purchaseReturnGst;

use YaLinqo\Enumerable;

class PurchaseReturnGstWiz extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectSupplier':
                $this->setSelectSupplier($data);
                break;
            case 'SelectStockPurchase':
                $this->setSelectStockPurchase($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectSupplier($data) {
        if ($data->SelectSupplier->account_id == -1) {
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }
        if ($data->SelectSupplier->dcn_type == -1) {
            array_push($this->brokenrules, 'Please select Debit/Credit Note Type');
        }

        $this->data['SelectSupplier'] = array();
        if ($data->SelectSupplier->account_id != -1) {
            $this->data['SelectSupplier']['account_id'] = $data->SelectSupplier->account_id;
            $this->data['SelectSupplier']['from_date'] = $data->SelectSupplier->from_date;
            $this->data['SelectSupplier']['to_date'] = $data->SelectSupplier->to_date;
            $this->data['SelectSupplier']['dcn_type'] = $data->SelectSupplier->dcn_type;
        }
    }

    private function setSelectStockPurchase($data) {
        $this->data['SelectStockPurchase'] = array();

        $lst = Enumerable::from($data->SelectStockPurchase)->where('$a==>$a->selected==true')->toList();
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select atleast one Stock Purchase to proceed.');
        }
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Multiple Purchases not be selected in a single return/debit/credit note.');
        }

        foreach ($data->SelectStockPurchase as $sp) {
            if ((bool) $sp->selected === true) {
                $this->data['SelectStockPurchase']['reference_id'] = $sp->stock_id;
                break;
            }
        }
    }
}
