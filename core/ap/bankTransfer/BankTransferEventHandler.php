<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\bankTransfer;

/**
 * Description of BankTranferEventHandler
 *
 * @author Valli
 */
class BankTransferEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            
            $this->bo->is_ac_payee = true;
            $this->bo->voucher_id = ""; 
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
                        
            $this->bo->annex_info->Value()->pay_cycle_id = $criteriaparam['formData']['SelectPayCycle']['pay_cycle_id'];
            $this->bo->annex_info->Value()->bank_account_id = $criteriaparam['formData']['SelectPayCycle']['bank_account_id'];
                        
            // Fill Bank Transfer Tran
            $sl_no = 1;
            foreach ($criteriaparam['formData']['SelectVch'] as $bttran) {
                $newRow = $this->bo->pymt_tran->newRow();
                $newRow['sl_no'] = $sl_no;
                $newRow['vch_tran_id'] = $sl_no;
                $newRow['reference_id'] = $bttran['voucher_id'];
                $newRow['account_id'] = $bttran['supplier_account_id'];
                $newRow['vch_date'] = $bttran['doc_date'];
                $newRow['debit_amt'] = $bttran['credit_amt'];
                $this->bo->pymt_tran->AddRow($newRow);
                $sl_no = $sl_no + 1;
            }          
        }
    }

}
