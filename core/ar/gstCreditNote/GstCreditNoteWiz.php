<?php

namespace app\core\ar\gstCreditNote;

use YaLinqo\Enumerable;

class GstCreditNoteWiz extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectCustomer':
                $this->setSelectCustomer($data);
                break;
            case 'SelectInvoice':
                $this->setSelectInvoice($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectCustomer($data) {
        if ($data->SelectCustomer->customer_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        if ($data->SelectCustomer->dcn_type == -1) {
            array_push($this->brokenrules, 'Please select Debit/Credit Note Type');
        }

        $this->data['SelectCustomer'] = array();
        if ($data->SelectCustomer->customer_id != -1) {
            $this->data['SelectCustomer']['customer_id'] = $data->SelectCustomer->customer_id;
            $this->data['SelectCustomer']['from_date'] = $data->SelectCustomer->from_date;
            $this->data['SelectCustomer']['dcn_type'] = $data->SelectCustomer->dcn_type;
            $this->data['SelectCustomer']['to_date'] = $data->SelectCustomer->to_date;
        }
    }

    private function setSelectInvoice($data) {
        $this->data['SelectInvoice'] = array();

        $lst = Enumerable::from($data->SelectInvoice)->where('$a==>$a->selected==true')->toList();
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select atleast one Invoice to proceed.');
        }
        
        $lst = Enumerable::from($data->SelectInvoice)->where('$a==>$a->selected==true')
                ->groupBy('$a==>$a->invoice_id')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Multiple Invoices cannot be selected in a single Credit Note.');
        }
        foreach ($data->SelectInvoice as $m) {
            if ((bool) $m->selected === true) {
                array_push($this->data['SelectInvoice'], $m);
            }
        }
    }

}
