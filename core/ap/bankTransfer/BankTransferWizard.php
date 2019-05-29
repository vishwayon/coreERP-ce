<?php

namespace app\core\ap\bankTransfer;

use YaLinqo\Enumerable;

class BankTransferWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectPayCycle':
                $this->setSelectPayCycle($data);
                break;
            case 'SelectVch':
                $this->setSelectVch($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectPayCycle($data) {        
        
        if (($data->SelectPayCycle->bank_account_id == -1) || ($data->SelectPayCycle->pay_cycle_id == -1)) {
            array_push($this->brokenrules, 'Please select Pay Cycle and Bank Account to proceed.');
        }
        
        $this->data['SelectPayCycle'] = array();
        $this->data['SelectPayCycle']['bank_account_id'] = $data->SelectPayCycle->bank_account_id;        
        $this->data['SelectPayCycle']['pay_cycle_id'] = $data->SelectPayCycle->pay_cycle_id;
    }
    
    private function setSelectVch($data) {
        $selectedRows = 0;
        $this->data['SelectVch'] = array();
//
//        $lst = Enumerable::from($data->SelectVch)->where('$a==>$a->selected==true')->distinct('$a==>$a->fc_type_id')->toList();
//        if (count($lst) > 1) {
//            array_push($this->brokenrules, 'Cannot select Invoices accross Txn Ccy.');
//        }

        foreach ($data->SelectVch as $supp) {
            if ((bool) $supp->selected === true) {
                $selectedRows = $selectedRows + 1;
                array_push($this->data['SelectVch'], $supp);
            }
        }
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select atleast one Bill to proceed.');
        }
    }

}
