<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierPayment;

/**
 * Description of SupplierPaymentEventHandler
 *
 * @author Priyanka
 */
class SupplierPaymentEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->pymt_tran->getColumn("dc")->default = "D";
        $this->bo->setTranColDefault('pymt_tran', 'dc', "D");

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->is_inter_branch = (boolean) $criteriaparam['formData']['SelectSupplier']['is_inter_branch'];
            //$this->bo->is_ac_payee = true;
            $this->bo->supplier_account_id = $criteriaparam['formData']['SelectSupplier']['account_id'];
            $this->bo->voucher_id = "";
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            if (count($criteriaparam['formData']['SelectVch']) > 0) {
                $this->bo->fc_type_id = $criteriaparam['formData']['SelectVch'][0]['fc_type_id'];
            } else {
                $this->bo->fc_type_id = 0;
            }

            $this->bo->exch_rate = 1;
            //$this->bo->cheque_number="0"; 
            $this->bo->status = 0;
            $this->bo->pymt_type = 0;
            $this->bo->cheque_date ='1970-01-01';
            $this->bo->annex_info->Value()->is_bt = true;

            // Fetch exch rate for selected fc type
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select exch_rate from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $this->bo->exch_rate = $dtfc->Rows()[0]['exch_rate'];
            }

            // Fetch supplier Name
//            $cmm = new \app\cwf\vsla\data\SqlCommand();
//            $cmm->setCommandText(' Select supplier_name  from ap.supplier  where supplier_id=:psupplier_id');
//            $cmm->addParam('psupplier_id', $this->bo->supplier_account_id);
//            $dtSupp = \app\cwf\vsla\data\DataConnect::getData($cmm);
//            if (count($dtSupp->Rows()) > 0) {
//                $this->bo->supplier_detail = $dtSupp->Rows()[0]['supplier_name'];
//            }

            // Fill Payable Alloc Tran
            $sl_no = 1;
            foreach ($criteriaparam['formData']['SelectVch'] as $pltran) {
                $newRow = $this->bo->pl_alloc_tran->newRow();
                $newRow['branch_id'] = $pltran['branch_id'];
                $newRow['bill_id'] = $pltran['voucher_id'];
                $newRow['vch_doc_date'] = $pltran['doc_date'];
                $newRow['doc_date'] = $pltran['doc_date'];
                $newRow['account_id'] = $pltran['account_id'];
                $newRow['bill_no'] = $pltran['bill_no'];
                $newRow['bill_date'] = $pltran['bill_date'];
                $newRow['balance'] = $pltran['over_due'] + $pltran['not_due'];
                $newRow['balance_fc'] = $pltran['over_due_fc'] + $pltran['not_due_fc'];
                $newRow['debit_amt'] = $pltran['over_due'] + $pltran['not_due'];
                $newRow['debit_amt_fc'] = $pltran['over_due_fc'] + $pltran['not_due_fc'];
                $newRow['net_debit_amt'] = $pltran['over_due'] + $pltran['not_due'];
                $newRow['net_debit_amt_fc'] = $pltran['over_due_fc'] + $pltran['not_due_fc'];
                $newRow['write_off_amt'] = 0;
                $newRow['write_off_amt_fc'] = 0;
                $newRow['rl_pl_id'] = $pltran['rl_pl_id'];
                $newRow['rl_pl_alloc_id'] = $sl_no;
                $this->bo->pl_alloc_tran->AddRow($newRow);
                $sl_no = $sl_no + 1;
            }
            
            // Get related customer_id for selected supplier
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select a.customer_id, b.customer_name'
                    . ' from ap.supp_cust a Inner join ar.customer b on a.customer_id = b.customer_id'
                    . ' where a.supplier_id=:psupplier_id');
            $cmm->addParam('psupplier_id', $this->bo->supplier_account_id);
            $dtSuppCust = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtSuppCust->Rows()) > 0) {
                $this->bo->annex_info->Value()->customer_id = $dtSuppCust->Rows()[0]['customer_id'];
                $this->bo->customer = $dtSuppCust->Rows()[0]['customer_name'];
            }
            
        } else {

            $this->bo->received_from = $this->bo->supplier;
            if ($this->bo->status == 5) {                
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select a.rl_pl_id, a.voucher_id, a.doc_date, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date
                    from ac.rl_pl a 
                    inner join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
                    where b.voucher_id = :pvoucher_id");
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->pl_alloc_tran->Rows() as &$refpl_row) {
                    foreach ($resultTemplate->Rows() as $row) {
                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refpl_row['vch_doc_date'] = $row['doc_date'];
                            $refpl_row['bill_no'] = $row['bill_no'];
                            $refpl_row['bill_date'] = $row['bill_date'];
                            $refpl_row['bill_id'] = $row['voucher_id'];
                        }
                    }
                }
                
                // Receivable Alloc Tran
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
                $cmm->setCommandText("select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.bill_no, case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date, a.balance, a.balance_fc 
                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a");
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', 0);
                $cmm->addParam('paccount_id', $this->bo->supplier_account_id);
                $cmm->addParam('pto_date', $this->bo->doc_date);
                $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
                $cmm->addParam('pdc', 'C');
                $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($this->bo->pl_alloc_tran->Rows() as &$refpl_row) {
                    foreach ($resultTemplate->Rows() as $row) {
                        if ($refpl_row['rl_pl_id'] == $row['rl_pl_id']) {
                            $refpl_row['vch_doc_date'] = $row['doc_date'];
                            $refpl_row['bill_no'] = $row['bill_no'];
                            $refpl_row['bill_date'] = $row['bill_date'];
                            $refpl_row['bill_id'] = $row['voucher_id'];
                            $refpl_row['balance'] = $row['balance'];
                            $refpl_row['balance_fc'] = $row['balance_fc'];
                        }
                    }
                }
                
                // Get Adv allocations
                \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->voucher_id);
                
                // Receivable Alloc Tran
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select a.rl_pl_id, a.account_id, a.voucher_id, a.doc_date, a.balance, a.balance_fc, is_opbl
                    from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a');
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pbranch_id', 0); // Always get data for all branches
                $cmm->addParam('paccount_id', $this->bo->annex_info->Value()->customer_id);
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
            
            // Fetch Adv alloc details
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->voucher_id);
        }

        $this->bo->supplier = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $this->bo->supplier_account_id);
        $this->bo->customer = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer_name', 'customer_id', $this->bo->annex_info->Value()->customer_id);

        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_account_id, $this->bo->doc_date);
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
        foreach ($this->bo->pl_alloc_tran->Rows() as &$refdiff_row) {
            foreach ($resultTemplate->Rows() as $row) {
                if ($refdiff_row['rl_pl_id'] == $row['rl_pl_id']) {
                    $refdiff_row['debit_exch_diff'] = $row['debit_exch_diff'];
                    $refdiff_row['net_debit_amt'] = round($refdiff_row['debit_amt'], \app\cwf\vsla\Math::$amtScale) + (round($refdiff_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale)) + round($refdiff_row['debit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_account_id, $this->bo->doc_date);
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
                $cmm->setParamValue('pdebit_amt', 0);
                $cmm->setParamValue('pcredit_amt', $this->bo->credit_amt);
                $cmm->setParamValue('pstatus', $this->bo->status);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $this->bo->ref_ledger_id = $detailpkid;
            }
        }
    }

}
