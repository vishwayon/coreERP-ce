<?php

namespace app\core\ap\advanceSupplierPayment;
use YaLinqo\Enumerable;

class AdvanceSupplierPaymentWizard
    extends \app\cwf\vsla\xmlbo\WizardBase{
    
    public function setData($step,$data,$oldStepData){
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectPO':
                $this->setSelectPO($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectPO($data){
        $this->data['SelectPO'] = array();

        $lst = Enumerable::from($data->SelectPO)->where('$a==>$a->selected==true')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Cannot select multiple PO.');
        }
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select atleast one PO to proceed.');
        }

        foreach ($data->SelectPO as $supp) {
            if ((bool) $supp->selected === true) {
                array_push($this->data['SelectPO'], $supp);
            }
        }
    }
}