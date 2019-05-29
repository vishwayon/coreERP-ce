<?php

namespace app\core\hr\payrollPayment;
use YaLinqo\Enumerable;

class PayrollPaymentWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectPayItems':
                $this->setSelectPayItems($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectPayItems($data){
        $selectedRows = 0;
        $this->data['SelectPayItems']=array();
        
        $selected_pay_items = '';
        foreach ($data->SelectPayItems as $supp) {
            if((bool)$supp->selected===true){
                $selectedRows = $selectedRows + 1;
                if ($selected_pay_items  == ''){
                    $selected_pay_items = "'" . $supp->payroll_tran_id . "'";
                }
                else{
                    $selected_pay_items =$selected_pay_items .", '".  $supp->payroll_tran_id. "'";
                }
            }
        }
        $this->data['SelectPayItems']['selected_pay_items'] = $selected_pay_items;
        if($selectedRows == 0){
            array_push($this->brokenrules, 'Please select atleast one Pay Item to proceed.');
        }
    }
}