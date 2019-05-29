<?php

namespace app\core\ar\custManualSet;

use YaLinqo\Enumerable;

class CustManualSetWizard extends \app\cwf\vsla\xmlbo\WizardBase {

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
        if ($data->SelectCustomer->account_id == -1) {
            array_push($this->brokenrules, 'Please select Customer to proceed.');
        }
        $this->data['SelectCustomer'] = array();
        $this->data['SelectCustomer']['account_id'] = $data->SelectCustomer->account_id;
    }


    private function setSelectVch($data) {
        $selectedRows = 0;
        //To check if no customer is selected or more than one customer is selected
        foreach ($data->SelectVch as $supp) {
            if ((bool) $supp->selected === true) {
                $selectedRows = $selectedRows + 1;
            }
        }
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select at least invoice to proceed.');
        } elseif ($selectedRows > 1) {
            array_push($this->brokenrules, 'Please select only one invoice to proceed.');
        }

        $this->data['SelectVch'] = array();

        foreach ($data->SelectVch as $cust) {
            if ((bool) $cust->selected === true) {
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectVch'], $cust);
            }
        }
    }
}
