<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierReceipt;

/**
 * Description of SupplierReceiptEventHandler
 *
 * @author Priyanka
 */
class SupplierReceiptEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            //$this->bo->cheque_number="0"; 
            $this->bo->status = 0;
            $this->bo->pymt_type = 0;
        } else {
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->voucher_id);
            $this->bo->net_settled = $this->bo->credit_amt;
        }
    }

}
