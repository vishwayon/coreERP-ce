<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\multiCustReceipt;

use YaLinqo\Enumerable;

/**
 * Description of MultCustReceiptEventHandler
 *
 * @author Priyanka
 */
class MultiCustReceiptEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->trigger_id = 'core';
        $this->InitialiseReceipt($criteriaparam);
    }

    protected function InitialiseReceipt($criteriaparam) {
//        $this->createInvTranTemp();


        $this->bo->rcpt_tran->getColumn("dc")->default = "C";
        $this->bo->setTranColDefault('rcpt_tran', 'dc', "C");
        
        $this->bo->rcpt_adv_tran->getColumn("branch_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $this->bo->setTranColDefault('rcpt_adv_tran', 'branch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->fc_type_id = 0;

            $this->bo->status = 0;
            $this->bo->rcpt_type = 0;
            $this->bo->en_rcpt_action = 0;
            $this->bo->exch_rate = 1;

        } else {
            if ($this->bo->status == 5) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.is_opbl
                 from ac.rl_pl a
                 inner join ac.rl_pl_alloc b on a.rl_pl_id=b.rl_pl_id
                 where b.voucher_id = :pvoucher_id');
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $refrl = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrl_row) {
                    foreach ($refrl->Rows() as $row) {
                        if ($refrl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refrl_row['invoice_id'] = $row['voucher_id'];
                            $refrl_row['invoice_date'] = $row['doc_date'];
                            $refrl_row['is_opbl'] = $row['is_opbl'];
                        }
                    }
                }
            } else {


//                $lst = Enumerable::from($this->bo->receivable_ledger_alloc_tran->Rows())->groupBy('$a==>$a->account_id')->toList();
//                $cmm = new \app\cwf\vsla\data\SqlCommand();
//                $cmm->setCommandText('select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.balance, a.balance_fc 
//                 from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a');
//                $cmm->addParam('pcompany_id', $this->bo->company_id);
//                $cmm->addParam('pbranch_id', 0); // Always get data for all branches
//                $cmm->addParam('paccount_id', -1);
//                $cmm->addParam('pto_date', $this->bo->doc_date);
//                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
//                $cmm->addParam('pdc', 'D');
//                foreach ($lst as $itm) {
//                    $cmm->setParamValue('paccount_id', $itm['account_id']);
//                    $refrl = \app\cwf\vsla\data\DataConnect::getData($cmm);
//                    foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrl_row) {
//                        foreach ($refrl->Rows() as $row) {
//                            if ($refrl_row['rl_pl_id'] == $row['rl_pl_id']) {
//                                $refrl_row['invoice_id'] = $row['voucher_id'];
//                                $refrl_row['invoice_date'] = $row['doc_date'];
//                                $refrl_row['balance'] = $row['balance'];
//                                $refrl_row['balance_fc'] = $row['balance_fc'];
//                            }
//                        }
//                    }
//                }

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.balance, a.balance_fc, a.is_opbl 
                 from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a');
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', 0); // Always get data for all branches
                $cmm->addParam('paccount_id', -1);
                $cmm->addParam('pto_date', $this->bo->doc_date);
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $cmm->addParam('pdc', 'D');
                foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrl_row) {
                    $cmm->setParamValue('paccount_id', $refrl_row['account_id']);
                    $refrl = \app\cwf\vsla\data\DataConnect::getData($cmm);
                    foreach ($refrl->Rows() as $row) {
                        if ($refrl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refrl_row['invoice_id'] = $row['voucher_id'];
                            $refrl_row['invoice_date'] = $row['doc_date'];
                            $refrl_row['balance'] = $row['balance'];
                            $refrl_row['balance_fc'] = $row['balance_fc'];
                            $refrl_row['is_opbl'] = $row['is_opbl'];
                        }
                    }
                }
            }
//
//            if ($this->bo->status == 5) {
//                $cmm = new \app\cwf\vsla\data\SqlCommand();
//                $cmm->setCommandText("select a.rl_pl_id, a.voucher_id, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date
//                    from ac.rl_pl a 
//                    inner join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
//                    where b.voucher_id = :pvoucher_id");
//                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
//                $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
//                foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_row) {
//                    foreach ($resultTemplate->Rows() as $row) {
//                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
//                            $refpl_row['bill_no'] = $row['bill_no'];
//                            $refpl_row['bill_date'] = $row['bill_date'];
//                            $refpl_row['bill_id'] = $row['voucher_id'];
//                        }
//                    }
//                }
//            } else {
//                $cmm = new \app\cwf\vsla\data\SqlCommand();
//                $cmm->setCommandText("select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date, a.balance, a.balance_fc 
//                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a");
//                $cmm->addParam('pcompany_id', $this->bo->company_id);
//                $cmm->addParam('pbranch_id', 0);
//                $cmm->addParam('paccount_id', $this->bo->account_id);
//                $cmm->addParam('pto_date', $this->bo->doc_date);
//                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
//                $cmm->addParam('pdc', 'C');
//                $refpl = \app\cwf\vsla\data\DataConnect::getData($cmm);
//                foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_row) {
//                    foreach ($refpl->Rows() as $row) {
//                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
//                            $refpl_row['bill_no'] = $row['bill_no'];
//                            $refpl_row['bill_date'] = $row['bill_date'];
//                            $refpl_row['bill_id'] = $row['voucher_id'];
//                            $refpl_row['balance'] = $row['balance'];
//                            $refpl_row['balance_fc'] = $row['balance_fc'];
//                        }
//                    }
//                }
//            }
//            $this->bo->customer = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->customer_account_id);
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
                    $refdiff_row['net_credit_amt'] = round($refdiff_row['credit_amt'], \app\cwf\vsla\Math::$amtScale) 
                            + (round($refdiff_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) 
                            + round($refdiff_row['tds_amt'], \app\cwf\vsla\Math::$amtScale) 
                            + round($refdiff_row['gst_tds_amt'], \app\cwf\vsla\Math::$amtScale) 
                            + round($refdiff_row['other_exp'], \app\cwf\vsla\Math::$amtScale)) 
                            + round($refdiff_row['credit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
    }

}
