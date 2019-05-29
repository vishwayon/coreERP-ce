<?php

namespace app\core\ar\customerRefund;

use YaLinqo\Enumerable;

class CustomerRefundWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectCustomer':
                $this->setSelectCustomer($data);
                break;
            case 'SelectVch':
                $this->setSelectVch($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectCustomer($data) {
        if ($data->SelectCustomer->customer_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        $this->data['SelectCustomer'] = array();
        $this->data['SelectCustomer']['account_id'] = $data->SelectCustomer->customer_id;
        $this->data['SelectCustomer']['account_head'] = '';
        $this->data['SelectCustomer']['branch_id'] = 0;
        $this->data['SelectCustomer']['is_inter_branch'] = 1;
    }
    
    private function setSelectVch($data) {
        $selectedRows = 0;
        $this->data['SelectVch'] = array();

        $lst = Enumerable::from($data->SelectVch)->where('$a==>$a->selected==true')->distinct('$a==>$a->fc_type_id')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Cannot select Invoices accross Txn Ccy.');
        }

        foreach ($data->SelectVch as $cust) {
            if ((bool) $cust->selected === true) {
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectVch'], $cust);
            }
        }
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select at least one Invoice to proceed.');
        }
    }
}
