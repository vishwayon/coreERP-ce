<?php

namespace app\core\ap\supplierPayment;

use YaLinqo\Enumerable;

class SupplierPaymentWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData == null ? [] : $oldStepData;
        switch ($step) {
            case 'SelectSupplierAll':
                $this->setSelectSupplierAll($data);
                break;
            case 'SelectSupplierIB':
                $this->setSelectSupplierIB($data);
                break;
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
        $selectedRows = 0;
        //To check if no supplier is selected or more than one supplier is selected
        foreach ($data->SelectSupplier as $supp) {
            if ((bool) $supp->selected === true) {
                $selectedRows = $selectedRows + 1;
            }
        }        
        if ($selectedRows == 0) {
            array_push($this->brokenrules, 'Please select at least one Supplier to proceed.');
        } elseif ($selectedRows > 1) {
            array_push($this->brokenrules, 'Please select only one Supplier to proceed.');
        }

        $this->data['SelectSupplier'] = array();

        foreach ($data->SelectSupplier as $supp) {
            if ((bool) $supp->selected === true) {
                $this->data['SelectSupplier']['account_id'] = $supp->account_id;
                $this->data['SelectSupplier']['account_head'] = $supp->account_head;
                $this->data['SelectSupplier']['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $this->data['SelectSupplier']['is_inter_branch'] = 0;
                break;
            }
        }
    }

    private function setSelectSupplierIB($data) {
        if ($data->SelectSupplier->supplier_id == -1) {
            array_push($this->brokenrules, 'Please select Supplier to proceed.');
        }
        $this->data['SelectSupplier'] = array();
        $this->data['SelectSupplier']['account_id'] = $data->SelectSupplier->supplier_id;
        $this->data['SelectSupplier']['account_head'] = '';
        $this->data['SelectSupplier']['branch_id'] = 0;
        $this->data['SelectSupplier']['is_inter_branch'] = 1;
    }

    private function setSelectSupplierAll($data) {
        if (($data->SelectSupplierAll->account_id == -1) && ($data->SelectSupplierAll->pay_cycle_id == -1)) {
            array_push($this->brokenrules, 'Please select either Supplier or Pay Cycle to proceed.');
        }
        if (($data->SelectSupplierAll->account_id > 0) && ($data->SelectSupplierAll->pay_cycle_id >0)) {
            array_push($this->brokenrules, 'Please select either Supplier or Pay Cycle to proceed.');
        }
        $this->data['SelectSupplierAll'] = array();
        $this->data['SelectSupplierAll']['account_id'] = $data->SelectSupplierAll->account_id;
        $this->data['SelectSupplierAll']['account_head'] = '';
        $this->data['SelectSupplierAll']['branch_id'] = 0;
        $this->data['SelectSupplierAll']['is_inter_branch'] = 1;
        if (($data->SelectSupplierAll->pay_cycle_id == -1)) {
            $this->data['SelectSupplierAll']['pay_cycle_id'] = 0;}
        else{
            $this->data['SelectSupplierAll']['pay_cycle_id'] = $data->SelectSupplierAll->pay_cycle_id;  
            $this->data['SelectSupplierAll']['account_id'] = 0;
        }       
    }
    
    private function setSelectVch($data) {
        $selectedRows = 0;
        $this->data['SelectVch'] = array();

        $lst = Enumerable::from($data->SelectVch)->where('$a==>$a->selected==true')->distinct('$a==>$a->fc_type_id')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Cannot select Invoices accross Txn Ccy.');
        }

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
