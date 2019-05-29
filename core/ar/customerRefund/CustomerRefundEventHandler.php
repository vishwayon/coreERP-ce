<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerRefund;

/**
 * Description of CustomerRefundEventHandler
 *
 * @author Priyanka
 */
class CustomerRefundEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->trigger_id = 'core';
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');

            //$this->bo->cheque_number="0"; 
            $this->bo->status = 0;
            $this->bo->rcpt_type = 0;
            $this->bo->en_rcpt_action = 0;

        } else {
            
            \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->voucher_id);
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);

        // Fetch receivable ledger exch diff and calculate net amt
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select rl_pl_id, account_id, credit_exch_diff from ac.rl_pl_alloc
                                where voucher_id=:pvoucher_id');
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refdiff_row) {
            foreach ($resultTemplate->Rows() as $row) {
                if ($refdiff_row['rl_pl_id'] == $row['rl_pl_id']) {
                    $refdiff_row['credit_exch_diff'] = $row['credit_exch_diff'];
                    $refdiff_row['net_credit_amt'] = round($refdiff_row['credit_amt'], \app\cwf\vsla\Math::$amtScale) + (round($refdiff_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) + round($refdiff_row['tds_amt'], \app\cwf\vsla\Math::$amtScale) + round($refdiff_row['other_exp'], \app\cwf\vsla\Math::$amtScale)) + round($refdiff_row['credit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
    }
}
