<?php

namespace app\core\st\saleReturnGst;

use YaLinqo\Enumerable;

class SaleReturnGstWiz extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectCustomer':
                $this->setSelectCustomer($data);
                break;
            case 'SelectStockInvoice':
                $this->setSelectStockInvoice($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectCustomer($data) {
        if ($data->SelectCustomer->account_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        if ($data->SelectCustomer->dcn_type == -1) {
            array_push($this->brokenrules, 'Please select Debit/Credit Note Type');
        }

        $this->data['SelectCustomer'] = array();
        if ($data->SelectCustomer->account_id != -1) {
            $this->data['SelectCustomer']['account_id'] = $data->SelectCustomer->account_id;
            $this->data['SelectCustomer']['dcn_type'] = $data->SelectCustomer->dcn_type;
            $this->data['SelectCustomer']['from_date'] = $data->SelectCustomer->from_date;
            $this->data['SelectCustomer']['to_date'] = $data->SelectCustomer->to_date;
        }
    }

    private function setSelectStockInvoice($data) {
        $this->data['SelectStockInvoice'] = array();

        $lst = Enumerable::from($data->SelectStockInvoice)->where('$a==>$a->selected==true')->toList();
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select atleast one Stock Item to proceed.');
        }
        
        $lst = Enumerable::from($data->SelectStockInvoice)->where('$a==>$a->selected==true')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Multiple Invoices cannot be selected in a single return/debit/credit note.');
        }

        foreach ($data->SelectStockInvoice as $m) {
            if ((bool) $m->selected === true) {
                $this->data['SelectStockInvoice']['reference_id'] = $m->stock_id;
                break;
            }
        }
    }

}
