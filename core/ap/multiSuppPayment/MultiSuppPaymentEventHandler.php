<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\multiSuppPayment;

/**
 * Description of MultiSuppEventHandler
 *
 * @author Priyanka
 */
class MultiSuppPaymentEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->pymt_tran->getColumn("dc")->default = "D";
        $this->bo->setTranColDefault('pymt_tran', 'dc', "D");

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->is_ac_payee = true;
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->pymt_type = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        } else {

            if ($this->bo->status == 5) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select a.rl_pl_id, a.voucher_id, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date
                    from ac.rl_pl a 
                    inner join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
                    where b.voucher_id = :pvoucher_id");
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_row) {
                    foreach ($resultTemplate->Rows() as $row) {
                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refpl_row['bill_no'] = $row['bill_no'];
                            $refpl_row['bill_date'] = $row['bill_date'];
                            $refpl_row['bill_id'] = $row['voucher_id'];
                        }
                    }
                }
            } else {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date, a.balance, a.balance_fc 
                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a");
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', 0);
                $cmm->addParam('paccount_id', -1);
                $cmm->addParam('pto_date', $this->bo->doc_date);
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $cmm->addParam('pdc', 'C');
                foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_row) {
                    $cmm->setParamValue('paccount_id', $refpl_row['account_id']);
                    $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
                    foreach ($resultTemplate->Rows() as $row) {
                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refpl_row['bill_no'] = $row['bill_no'];
                            $refpl_row['bill_date'] = $row['bill_date'];
                            $refpl_row['bill_id'] = $row['voucher_id'];
                            $refpl_row['balance'] = $row['balance'];
                            $refpl_row['balance_fc'] = $row['balance_fc'];
                        }
                    }
                }
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);

        // Fetch receivable ledger exch diff and calculate net amt
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select rl_pl_id, account_id, debit_exch_diff from ac.rl_pl_alloc
                                where voucher_id=:pvoucher_id');
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refdiff_row) {
            foreach ($resultTemplate->Rows() as $row) {
                if ($refdiff_row['rl_pl_id'] == $row['rl_pl_id']) {
                    $refdiff_row['debit_exch_diff'] = $row['debit_exch_diff'];
                    $refdiff_row['net_debit_amt'] = round($refdiff_row['debit_amt'], \app\cwf\vsla\Math::$amtScale) + (round($refdiff_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale)) + round($refdiff_row['debit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
    }

}
