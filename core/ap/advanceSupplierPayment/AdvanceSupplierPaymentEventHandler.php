<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\advanceSupplierPayment;

/**
 * Description of AdvanceSupplierPaymentEventHandler
 *
 * @author priyanka
 */
class AdvanceSupplierPaymentEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->pymt_type = 0;
            $this->bo->target_branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            if (isset($criteriaparam['formData'])) {
                if (count($criteriaparam['formData']['SelectPO']) > 0) {
                    $this->bo->annex_info->Value()->po_no = $criteriaparam['formData']['SelectPO'][0]['stock_id'];
                    $this->bo->annex_info->Value()->po_date = $criteriaparam['formData']['SelectPO'][0]['doc_date'];
                    $this->bo->gross_adv_amt = $criteriaparam['formData']['SelectPO'][0]['advance_amt'];
                    $this->bo->supplier_account_id = $criteriaparam['formData']['SelectPO'][0]['account_id'];
                    $this->bo->supplier_detail = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $this->bo->supplier_account_id);
                    $this->bo->annex_info->Value()->is_tds_applied = \app\core\tds\worker\TDSWorker::TDSInfoExists($this->bo->supplier_account_id);
                    $this->bo->target_branch_id = $criteriaparam['formData']['SelectPO'][0]['target_branch_id'];
                    if ($this->bo->target_branch_id != \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id')) {
                        $this->bo->is_inter_branch = true;
                    }
                }
            }
        } else {
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

        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5 && $this->bo->supplier_account_id != -1) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_account_id, $this->bo->doc_date);
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_account_id, $this->bo->doc_date);
        }

        if ($this->bo->annex_info->Value()->po_no != '') {
            // Update ASP no in PO, if PO ref no is available in ASP         
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText(" update st.stock_control
                                set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                where stock_id = :ppo_no
                                        and annex_info->>'adv_ref_no' is not null");
            $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
            $cmm->addParam('padv_ref_id', '"' . $this->bo->voucher_id . '"');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);

            // Update ASP no in PO, if PO ref no is available in ASP - This is POCG
            if (substr($this->bo->annex_info->Value()->po_no, 0, 4) == "POCG") {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText(" update fa.ap_control
                                    set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                    where ap_id = :ppo_no
                                            and annex_info->>'adv_ref_no' is not null");
                $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
                $cmm->addParam('padv_ref_id', '"' . $this->bo->voucher_id . '"');
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
            }
            
            // Update ASP no in PO, if PO ref no is available in ASP - This is Media Purchase Order
            if (substr($this->bo->annex_info->Value()->po_no, 0, 4) == "SPO2") {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText(" update pub.po_control
                                    set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                    where po_id = :ppo_no
                                            and annex_info->>'adv_ref_no' is not null");
                $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
                $cmm->addParam('padv_ref_id', '"' . $this->bo->voucher_id . '"');
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
            }
        }
    }

    public function afterDeleteCommit() {
        parent::afterDeleteCommit();

        if ($this->bo->annex_info->Value()->po_no != '') {
            // Remove ASP no in PO, if PO ref no is available in ASP on delete
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText(" update st.stock_control
                                set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                where stock_id = :ppo_no
                                        and annex_info->>'adv_ref_no' is not null");
            $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
            $cmm->addParam('padv_ref_id', '""');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }

        // Update ASP no in PO, if PO ref no is available in ASP - This is POCG
        if (substr($this->bo->annex_info->Value()->po_no, 0, 4) == "POCG") {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText(" update fa.ap_control
                                    set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                    where ap_id = :ppo_no
                                            and annex_info->>'adv_ref_no' is not null");
            $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
            $cmm->addParam('padv_ref_id', '""');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }

        // Update ASP no in PO, if PO ref no is available in ASP - This is Media PO
        if (substr($this->bo->annex_info->Value()->po_no, 0, 4) == "SPO2") {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText(" update pub.po_control
                                    set annex_info =  jsonb_set(annex_info, '{adv_ref_no}', :padv_ref_id::jsonb, false) 
                                    where po_id = :ppo_no
                                            and annex_info->>'adv_ref_no' is not null");
            $cmm->addParam('ppo_no', $this->bo->annex_info->Value()->po_no);
            $cmm->addParam('padv_ref_id', '""');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
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
                $cmm->setParamValue('pcredit_amt', $this->bo->gross_adv_amt);
                $cmm->setParamValue('pstatus', $this->bo->status);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $this->bo->ref_ledger_id = $detailpkid;
            }
        }
    }

}
