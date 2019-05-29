<?php

namespace app\core\ap\suppManualSet;

use YaLinqo\Enumerable;

class SuppManualSetWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectSupplier':
                $this->setSelectSupplier($data);
                break;
            case 'SelectVch':
                $this->setSelectVch($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    

    private function setSelectSupplier($data) {
        if ($data->SelectSupplier->account_id == -1) {
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }
        $this->data['SelectSupplier'] = array();
        $this->data['SelectSupplier']['account_id'] = $data->SelectSupplier->account_id;
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
            array_push($this->brokenrules, 'Please select at least bill to proceed.');
        } elseif ($selectedRows > 1) {
            array_push($this->brokenrules, 'Please select only one bill to proceed.');
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
