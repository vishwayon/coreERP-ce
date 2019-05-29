<?php

namespace app\core\st\purchaseReturnGst;

use YaLinqo\Enumerable;

class SpgForPrvWizard extends \app\cwf\vsla\xmlbo\WizardBase {

    public function setData($step, $data, $oldStepData) {
        $this->data = $oldStepData;
        switch ($step) {
            case 'SelectStockPurchase':
                $this->setSelectStockPurchase($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }

    private function setSelectStockPurchase($data) {
        $this->data['SelectSpgForPrv'] = array();

        $lst = Enumerable::from($data->SelectStockPurchase)->where('$a==>$a->selected==true')->distinct('$a==>$a->stock_id')->toList();
        if (count($lst) > 1) {
            array_push($this->brokenrules, 'Cannot select Stock Items from different Stock Purchase.');
        }
        if (count($lst) == 0) {
            array_push($this->brokenrules, 'Please select Stock Purchase Items.');
        }
        
        $this->data['SelectSupplier'] = array();
        $this->data['SelectSupplier']['dcn_type'] = 0;
        
        
        $this->data['SelectStockPurchase'] = array();
        $selected_spgs = '';
        foreach ($data->SelectStockPurchase as $spg) {
            if ((bool) $spg->selected === true) {                
                $this->data['SelectSupplier']['account_id'] = $spg->account_id;
                $this->data['SelectStockPurchase']['reference_id'] = $spg->stock_id;
                if ($selected_spgs == '') {
                    $selected_spgs = "'" . $spg->stock_tran_id . "'";
                } else {
                    $selected_spgs = $selected_spgs . ", '" . $spg->stock_tran_id . "'";
                }
            }
        }

        $this->data['SelectSpgForPrv']['selected_spgs'] = $selected_spgs;
    }
}
