<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerReceipt;

use YaLinqo\Enumerable;

/**
 * Description of CustomerReceiptEventHandler
 *
 * @author Priyanka
 */
class CustomerReceiptEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->trigger_id = 'core';
        $this->InitialiseReceipt($criteriaparam);
    }

    protected function InitialiseReceipt($criteriaparam) {
//        $this->createInvTranTemp();

        $this->bo->rcpt_adv_tran->getColumn("branch_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $this->bo->setTranColDefault('rcpt_adv_tran', 'branch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));

        $this->bo->rcpt_tran->getColumn("dc")->default = "C";
        $this->bo->setTranColDefault('rcpt_tran', 'dc', "C");

        $this->bo->rcpt_sel_acc_tran->getColumn("dc")->default = "D";
        $this->bo->setTranColDefault('rcpt_sel_acc_tran', 'dc', "D");

        $this->bo->hsn_sc_id = -1;
        $this->bo->adv_gst_hsn_info = "";

        // Check if default Sales Account for connected branch is available in settings 
        if (\app\cwf\vsla\utils\SettingsHelper::HasKey('ar_adv_hsn_' . \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'))) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select hsn_sc_id From tx.hsn_sc
                    Where hsn_sc_id = (Select value::bigint from sys.settings where key = :psettings_key);");
            $cmm->addParam('psettings_key', 'ar_adv_hsn_' . \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $dtsa = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtsa->Rows()) == 1) {
                $this->bo->hsn_sc_id = $dtsa->Rows()[0]['hsn_sc_id'];
            }
        }
        if ($this->bo->hsn_sc_id != -1) {
            $cmm_hsn = new \app\cwf\vsla\data\SqlCommand();
            $cmm_hsn->setCommandText("With row_data
                    As
                    (	Select a.hsn_sc_code, a.hsn_sc_type, c.*
                            From tx.hsn_sc a
                            Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                            Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                            Where a.hsn_sc_id = :phsn_sc_id
                     )
                     Select row_to_json(r) as gst_hsn_info
                     From row_data r;");
            $cmm_hsn->addParam('phsn_sc_id', $this->bo->hsn_sc_id);
            $dt_hsn = \app\cwf\vsla\data\DataConnect::getData($cmm_hsn);

            if (count($dt_hsn->Rows()) == 1) {
                $this->bo->adv_gst_hsn_info = $dt_hsn->Rows()[0]['gst_hsn_info'];
            }
        }

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->customer_account_id = $criteriaparam['formData']['SelectCustomer']['account_id'];
            $this->bo->is_inter_branch = $criteriaparam['formData']['SelectCustomerAll']['is_inter_branch'] == 'false' ? false : true;
            $this->bo->received_from = $this->bo->customer;
            $this->bo->voucher_id = "";
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');

            $this->bo->annex_info->Value()->to_date = $criteriaparam['formData']['SelectCustomerAll']['to_date'];
            $this->bo->doc_date = $criteriaparam['formData']['SelectCustomerAll']['to_date'];
            $this->bo->net_settled = $criteriaparam['formData']['SelectCustomer']['credit_amt'];
//            
//            if (count($criteriaparam['formData']['SelectVch']) > 0) {
//                $this->bo->fc_type_id = $criteriaparam['formData']['SelectVch'][0]['fc_type_id'];
//            } else {
//                $this->bo->fc_type_id = 0;
//            }

            $this->bo->status = 0;
            $this->bo->rcpt_type = 0;
            $this->bo->en_rcpt_action = 0;
            $this->bo->fc_type_id = 0;


            // Fetch exch rate for selected fc type
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select exch_rate from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $this->bo->exch_rate = $dtfc->Rows()[0]['exch_rate'];
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
                                order by a.doc_date, a.voucher_id");
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', $this->bo->is_inter_branch ? 0 : \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('paccount_id', $this->bo->customer_account_id);
            $cmm->addParam('pto_date', $criteriaparam['formData']['SelectCustomerAll']['to_date']);
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmm->addParam('pdc', 'D');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtInvBal = \app\cwf\vsla\data\DataConnect::getData($cmm);

            $adv_sl_no = 1;
            $cust_adv = 0;
            $balance = round(Enumerable::from($dtInvBal->Rows())->sum('$a==>$a["balance"]'), \app\cwf\vsla\Math::$amtScale);
            $cust_adv = $this->bo->net_settled - $balance;
            // Fill advance if net received is greater than balance
            if ($cust_adv > 0) {
                $newAdvRow = $this->bo->rcpt_adv_tran->newRow();
                $newAdvRow['sl_no'] = $adv_sl_no;
                $newAdvRow['vch_tran_id'] = $adv_sl_no;
                $newAdvRow['account_id'] = $this->bo->customer_account_id;
                $newAdvRow['adv_amt'] = $cust_adv;
                $newAdvRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $this->bo->rcpt_adv_tran->AddRow($newAdvRow);
                $adv_sl_no = $adv_sl_no + 1;
            }


            // Fill Receivable Alloc Tran based on FIFO
            $sl_no = 1;
            $cust_net_receivable = $this->bo->net_settled;
            foreach ($dtInvBal->Rows() as $rltran) {
                $credit_amt = 0;
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
            $this->bo->customer = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->customer_account_id);
            $this->bo->received_from = $this->bo->customer;
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
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.balance, a.balance_fc, a.is_opbl 
                 from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a');
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', 0); // Always get data for all branches
                $cmm->addParam('paccount_id', $this->bo->customer_account_id);
                $cmm->addParam('pto_date', $this->bo->doc_date);
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $cmm->addParam('pdc', 'D');
                $refrl = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrl_row) {
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
                $cmm->addParam('paccount_id', $this->bo->account_id);
                $cmm->addParam('pto_date', $this->bo->doc_date);
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $cmm->addParam('pdc', 'C');
                $refpl = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refpl_row) {
                    foreach ($refpl->Rows() as $row) {
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
            $this->bo->customer = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->customer_account_id);

            foreach ($this->bo->rl_head_tran->Rows() as $tran_row) {
                if ($tran_row['vch_tran_id'] == $this->bo->voucher_id) {
                    $this->bo->ref_no = $tran_row['ref_no'];
                    $this->bo->ref_desc = $tran_row['ref_desc'];
                    $this->bo->ref_ledger_id = $tran_row['ref_ledger_id'];
                    if (count($this->bo->rla_head_tran->Rows()) > 0) {
                        $this->bo->is_create_ref = false;
                        $this->bo->is_alloc_ref = true;
                    } else {
                        $this->bo->is_create_ref = true;
                        $this->bo->is_alloc_ref = false;
                    }
                    break;
                }
            }
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

    public function onNamedMethod($cn, $trantable) {

        if ($trantable->tableID == 'shl_head_tran') {

            // Delete Sub Head ledger records 
            $cmmDel = new \app\cwf\vsla\data\SqlCommand();
            $cmmDel->setCommandText("Delete from ac.sub_head_ledger where voucher_id = :pvoucher_id");
            $cmmDel->addParam('pvoucher_id', $this->bo->voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmmDel, $cn);


            $row_no = 0;
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            foreach ($this->bo->shl_head_tran->Rows() as &$shl_tran_row) {
                $row_no = $row_no + 1;
                // save all allocations
                $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.sub_head_ledger', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN);

                $cmm = $ac->getInsertCmm();
                $detailpkid = md5($this->bo->voucher_id . ':0:' . $row_no);
                $cmm->setParamValue('psub_head_ledger_id', $detailpkid);
                $cmm->setParamValue('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                $cmm->setParamValue('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
                $cmm->setParamValue('pbranch_id', $shl_tran_row['branch_id']);
                $cmm->setParamValue('pvoucher_id', $this->bo->voucher_id);
                $cmm->setParamValue('pvch_tran_id', $this->bo->voucher_id);
                $cmm->setParamValue('pdoc_date', $this->bo->doc_date);
                $cmm->setParamValue('paccount_id', $shl_tran_row['account_id']);
                $cmm->setParamValue('psub_head_id', $shl_tran_row['sub_head_id']);
                $cmm->setParamValue('pfc_type_id', $this->bo->fc_type_id);
                $cmm->setParamValue('pexch_rate', $this->bo->exch_rate);
                $cmm->setParamValue('pdebit_amt', $shl_tran_row['debit_amt']);
                $cmm->setParamValue('pdebit_amt_fc', $shl_tran_row['debit_amt_fc']);
                $cmm->setParamValue('pcredit_amt', $shl_tran_row['credit_amt']);
                $cmm->setParamValue('pcredit_amt_fc', $shl_tran_row['credit_amt_fc']);
                $cmm->setParamValue('pnarration', $shl_tran_row['narration']);
                $cmm->setParamValue('pstatus', 0);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $shl_tran_row['sub_head_ledger_id'] = $detailpkid;
            }
        }
        if ($trantable->tableID == 'rla_head_tran') {

            // Delete Sub Head ledger records 
            $cmmDel = new \app\cwf\vsla\data\SqlCommand();
            $cmmDel->setCommandText("Delete from ac.ref_ledger_alloc where affect_voucher_id = :pvoucher_id");
            $cmmDel->addParam('pvoucher_id', $this->bo->voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmmDel, $cn);

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $row_no = 0;
            foreach ($this->bo->rla_head_tran->Rows() as &$rla_tran_row) {
                $row_no = $row_no + 1;
                // save all allocations
                $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.ref_ledger_alloc', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN);
                $cmm = $ac->getInsertCmm();
                $detailpkid = md5($this->bo->voucher_id . ':0:' . $row_no);
                $cmm->setParamValue('pref_ledger_alloc_id', $detailpkid);
                $cmm->setParamValue('pref_ledger_id', $rla_tran_row['ref_ledger_id']);
                $cmm->setParamValue('pbranch_id', $rla_tran_row['branch_id']);
                $cmm->setParamValue('paffect_voucher_id', $this->bo->voucher_id);
                $cmm->setParamValue('paffect_vch_tran_id', $this->bo->voucher_id);
                $cmm->setParamValue('paffect_doc_date', $this->bo->doc_date);
                $cmm->setParamValue('paccount_id', $rla_tran_row['account_id']);
                $cmm->setParamValue('pnet_debit_amt', $rla_tran_row['net_debit_amt']);
                $cmm->setParamValue('pnet_credit_amt', $rla_tran_row['net_credit_amt']);
                $cmm->setParamValue('pstatus', 0);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $rla_tran_row['ref_ledger_alloc_id'] = $detailpkid;
            }
        }
        if ($trantable->tableID == 'rl_head_tran') {
            if ($this->bo->ref_no != '') {
                $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.ref_ledger', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);

                if ($this->bo->ref_ledger_id == '') {
                    $cmm = $ac->getInsertCmm();
                    $detailpkid = md5($this->bo->voucher_id . ':0:' . $this->bo->branch_id . ':' . $this->bo->account_id);
                } else {
                    $cmm = $ac->getUpdateCmm();
                    $detailpkid = $this->bo->ref_ledger_id;
                }
                $cmm->setParamValue('pref_ledger_id', $detailpkid);
                $cmm->setParamValue('pvoucher_id', $this->bo->voucher_id);
                $cmm->setParamValue('pvch_tran_id', $this->bo->voucher_id);
                $cmm->setParamValue('pdoc_date', $this->bo->doc_date);
                $cmm->setParamValue('paccount_id', $this->bo->account_id);
                $cmm->setParamValue('pbranch_id', $this->bo->branch_id);
                $cmm->setParamValue('pref_no', $this->bo->ref_no);
                $cmm->setParamValue('pref_desc', $this->bo->ref_desc);
                $cmm->setParamValue('pdebit_amt', $this->bo->net_settled);
                $cmm->setParamValue('pcredit_amt', 0);
                $cmm->setParamValue('pstatus', $this->bo->status);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $this->bo->ref_ledger_id = $detailpkid;
            }
        }
    }

}
