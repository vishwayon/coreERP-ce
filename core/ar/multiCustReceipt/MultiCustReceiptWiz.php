<?php

namespace app\core\ar\multiCustReceipt;

use YaLinqo\Enumerable;

class MultiCustReceiptWiz extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectDate':
                $this->setSelectDate($data);
                break;
            case 'SelectCust':
                $this->setSelectCust($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectCust($data) {
        $selectedRows = 0;
        $selected_custs = '';
        $this->data['SelectCust'] = array();

        foreach ($data->SelectCust as $cust) {
            if ((bool) $cust->is_select === true) {
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectCust'], $cust);
            }
        }
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select at least one Customer to proceed.');
        }
    }

    private function setSelectDate($data) {
        if ($data->SelectDate->ib_type == -1) {
            array_push($this->brokenrules, 'Please select Branch Type to proceed.');
        }
        $this->data['SelectDate'] = array();
        $this->data['SelectDate']['is_inter_branch'] = $data->SelectDate->ib_type == "0" ? false : true;
        $this->data['SelectDate']['to_date'] = $data->SelectDate->to_date;
    }
}
