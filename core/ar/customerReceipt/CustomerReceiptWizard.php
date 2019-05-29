<?php

namespace app\core\ar\customerReceipt;

use YaLinqo\Enumerable;

class CustomerReceiptWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectCustomerIB':
                $this->setSelectCustomerIB($data);
                break;
            case 'SelectCustomerAll':
                $this->setSelectCustomerAll($data);
                break;
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
        $selectedRows = 0;
        //To check if no customer is selected or more than one customer is selected
        foreach ($data->SelectCustomer as $supp) {
            if ((bool) $supp->selected === true) {
                $selectedRows = $selectedRows + 1;
            }
        }
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select at least one Customer to proceed.');
        } elseif ($selectedRows > 1) {
            array_push($this->brokenrules, 'Please select only one Customer to proceed.');
        }

        $this->data['SelectCustomer'] = array();

        foreach ($data->SelectCustomer as $cust) {
            if ((bool) $cust->selected === true) {
                $this->data['SelectCustomer']['account_id'] = $cust->account_id;
                $this->data['SelectCustomer']['account_head'] = $cust->account_head;
                $this->data['SelectCustomer']['credit_amt'] = $cust->credit_amt;
                break;
            }
        }
    }

    private function setSelectCustomerIB($data) {
        if ($data->SelectCustomer->customer_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        $this->data['SelectCustomer'] = array();
        $this->data['SelectCustomer']['account_id'] = $data->SelectCustomer->customer_id;
        $this->data['SelectCustomer']['account_head'] = '';
        $this->data['SelectCustomer']['branch_id'] = 0;
        $this->data['SelectCustomer']['is_inter_branch'] = 1;
    }

    private function setSelectCustomerAll($data) {
        if ($data->SelectCustomerAll->account_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        $this->data['SelectCustomerAll'] = array();
        $this->data['SelectCustomerAll']['account_id'] = $data->SelectCustomerAll->account_id;
        $this->data['SelectCustomerAll']['to_date'] = $data->SelectCustomerAll->to_date;
        $this->data['SelectCustomerAll']['is_inter_branch'] = $data->SelectCustomerAll->ib_type == "0" ? false : true;
        $this->data['SelectCustomerAll']['account_head'] = '';
        $this->data['SelectCustomerAll']['branch_id'] = 0;
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
