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

            $this->bo->is_inter_branch = $criteriaparam['formData']['SelectDate']['is_inter_branch'] == 'false' ? false : true;
            $this->bo->annex_info->Value()->to_date = $criteriaparam['formData']['SelectDate']['to_date'];
            $this->bo->doc_date = $criteriaparam['formData']['SelectDate']['to_date'];

            // Fill Receivable Alloc Tran
            $cust_ids = '';
            foreach ($criteriaparam['formData']['SelectCust'] as $cust) {
                if ($cust_ids == '') {
                    $cust_ids = $cust['account_id'];
                } else {
                    $cust_ids = $cust_ids . ", " . $cust['account_id'];
                }
            }
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("
                                select a.branch_id, a.rl_pl_id, a.account_id, b.account_head, a.voucher_id, a.doc_date, c.branch_name, 
                                        case when a.due_date <= :pto_date then a.balance else 0 end as over_due, case when a.due_date <= :pto_date then a.balance_fc else 0 end as over_due_fc, 
                                                    case when a.due_date > :pto_date then a.balance else 0 end as not_due, case when a.due_date > :pto_date then a.balance_fc else 0 end as not_due_fc,
                                        a.fc_type_id, a.fc_type, a.due_date, a.balance, a.balance_fc
                                from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                inner join ac.account_head b on a.account_id = b.account_id
                                inner join sys.branch c on a.branch_id = c.branch_id
                                where a.fc_type_id = :pfc_type_id 
                                        And a.account_id = Any(:pselected_cust)
                                order by b.account_head, a.doc_date, c.branch_name, a.voucher_id");
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', $this->bo->is_inter_branch ? 0 : \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('paccount_id', 0);
            $cmm->addParam('pselected_cust', '{' . $cust_ids . '}');
            $cmm->addParam('pto_date', $criteriaparam['formData']['SelectDate']['to_date']);
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmm->addParam('pdc', 'D');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtInvBal = \app\cwf\vsla\data\DataConnect::getData($cmm);


            // Fill supp temp with distinct supp
            $dt_cust_group = Enumerable::from($dtInvBal->Rows())->groupBy('$a==>$a["account_id"]')->toArray();

            $sl_no = 1;
            $adv_sl_no = 1;
            $cust_net_receivable = 0;
            $cust_adv = 0;
            foreach ($dt_cust_group as $groupKey => $cust_itm) {
                foreach ($criteriaparam['formData']['SelectCust'] as $cust_row) {
                    if ($cust_row['account_id'] == $groupKey) {
                        $cust_net_receivable = $cust_row['credit_amt'];
                    }
                }
                $newRow = $this->bo->mcr_summary_tran->newRow();
                $newRow['sl_no'] = $sl_no;
                $newRow['vch_tran_id'] = $sl_no;
                $newRow['account_id'] = $groupKey;
                $newRow['payable_amt'] = 0;
                $newRow['balance'] = round(Enumerable::from($cust_itm)->sum('$a==>$a["balance"]'), \app\cwf\vsla\Math::$amtScale);
                $cust_adv = $cust_net_receivable - $newRow['balance'];
                $newRow['receivable_amt'] = ($cust_net_receivable > $newRow['balance'] ? $newRow['balance'] : $cust_net_receivable);
                $newRow['adv_amt'] = 0;
                $newRow['net_payable_amt'] = 0;
                $this->bo->mcr_summary_tran->AddRow($newRow);
                $sl_no = $sl_no + 1;
                // Fill advance if net received is greater than balance
                if ($cust_adv > 0) {
                    $newAdvRow = $this->bo->rcpt_adv_tran->newRow();
                    $newAdvRow['sl_no'] = $sl_no;
                    $newAdvRow['vch_tran_id'] = $sl_no;
                    $newAdvRow['account_id'] = $groupKey;
                    $newAdvRow['adv_amt'] = $cust_adv;
                    $newAdvRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                    $this->bo->rcpt_adv_tran->AddRow($newAdvRow);
                    $adv_sl_no = $adv_sl_no + 1;
                }
            }

            $sl_no = 1;
            $cust_bal = 0;
            foreach ($this->bo->mcr_summary_tran->Rows() as $mcr_row) {
                $cust_net_receivable = $mcr_row['receivable_amt'];
                foreach ($dtInvBal->Rows() as $rltran) {
                    $credit_amt = 0;
                    if ($rltran['account_id'] == $mcr_row['account_id']) {
                        if ($cust_net_receivable > 0) {
                            if ($rltran['balance'] < $cust_net_receivable) {
                                $credit_amt = $rltran['balance'];
                                $cust_net_receivable = $cust_net_receivable - $rltran['balance'];
                            } else {
                                $credit_amt = $cust_net_receivable;
                                $cust_net_receivable = 0;
                            }
                        }
                        if ($credit_amt != 0) {
                            $newRow = $this->bo->receivable_ledger_alloc_tran->newRow();
                            $newRow['branch_id'] = $rltran['branch_id'];
                            $newRow['invoice_id'] = $rltran['voucher_id'];
                            $newRow['invoice_date'] = $rltran['doc_date'];
                            $newRow['doc_date'] = $rltran['doc_date'];
                            $newRow['account_id'] = $rltran['account_id'];
                            $newRow['balance'] = $rltran['balance'];
                            $newRow['balance_fc'] = $rltran['balance_fc'];
                            $newRow['credit_amt'] = $credit_amt;
                            $newRow['credit_amt_fc'] = 0;
                            $newRow['net_credit_amt'] = $credit_amt;
                            $newRow['net_credit_amt_fc'] = 0;
                            $newRow['write_off_amt'] = 0;
                            $newRow['write_off_amt_fc'] = 0;
                            $newRow['rl_pl_id'] = $rltran['rl_pl_id'];
                            $newRow['rl_pl_alloc_id'] = $sl_no;
                            $this->bo->receivable_ledger_alloc_tran->AddRow($newRow);
                            $sl_no = $sl_no + 1;
                        }
                    }
                }
            }
        } else {
            if ($this->bo->status != 5) {
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
                foreach ($this->bo->mcr_summary_tran->Rows() as &$mcr_row) {
                    $mcr_row['balance'] = round(Enumerable::from($this->bo->receivable_ledger_alloc_tran->Rows())->where('$a==>$a["account_id"] == ' . $mcr_row['account_id'])->sum('$a==>$a["balance"]'), \app\cwf\vsla\Math::$amtScale);
                }
            }

            $this->bo->credit_amt_total = round(Enumerable::from($this->bo->receivable_ledger_alloc_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
            $this->bo->credit_amt_total_fc = round(Enumerable::from($this->bo->receivable_ledger_alloc_tran->Rows())->sum('$a==>$a["credit_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);
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
                    $refdiff_row['net_credit_amt'] = round($refdiff_row['credit_amt'], \app\cwf\vsla\Math::$amtScale) + (round($refdiff_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) + round($refdiff_row['tds_amt'], \app\cwf\vsla\Math::$amtScale) + round($refdiff_row['gst_tds_amt'], \app\cwf\vsla\Math::$amtScale) + round($refdiff_row['other_exp'], \app\cwf\vsla\Math::$amtScale)) + round($refdiff_row['credit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
    }

}
