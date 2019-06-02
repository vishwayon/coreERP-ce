<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppManualSet;

use YaLinqo\Enumerable;

/**
 * Description of CustManualSetEventHandler
 *
 * @author Priyanka
 */
class SuppManualSetEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->trigger_id = 'core';
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->supplier_id = $criteriaparam['formData']['SelectSupplier']['account_id'];
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            if (count($criteriaparam['formData']['SelectVch']) > 0) {
                $this->bo->fc_type_id = $criteriaparam['formData']['SelectVch'][0]['fc_type_id'];
                $this->bo->voucher_id = $criteriaparam['formData']['SelectVch'][0]['voucher_id'];
                $this->bo->balance = $criteriaparam['formData']['SelectVch'][0]['balance'];
                $this->bo->balance_fc = $criteriaparam['formData']['SelectVch'][0]['balance_fc'];
                $this->bo->doc_date = $criteriaparam['formData']['SelectVch'][0]['doc_date'];
                $this->bo->pl_id = $criteriaparam['formData']['SelectVch'][0]['rl_pl_id'];
                $this->bo->bill_date = $criteriaparam['formData']['SelectVch'][0]['bill_date'];
                $this->bo->bill_no = $criteriaparam['formData']['SelectVch'][0]['bill_no'];
            } else {
                $this->bo->fc_type_id = 0;
            }

            // Fetch exch rate for selected fc type
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select exch_rate from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $this->bo->exch_rate = $dtfc->Rows()[0]['exch_rate'];
            }
        } else {
            
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
        if ($tablename == 'ap.supplier') {
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ap.supplier', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm = $ac->getFetchCmm();
            $cmm->setParamValue('psupplier_id', $criteriaparam['formData']['SelectSupplier']['account_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $this->bo->supplier = $dt->Rows()[0]['supplier'];
            }
        }
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
        if ($tablename == 'ap.supplier') {

            // Fetch allocation for same invoice if exists
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select *, (substring(vch_tran_id, length(voucher_id)+2, length(vch_tran_id))::bigint) as sl_no from ac.rl_pl_alloc where branch_id = :pbranch_id and voucher_id = :pvoucher_id 
                                    and account_id = :paccount_id');
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmm->addParam('paccount_id', $this->bo->supplier_id);
            $dtcheck = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $dt_check_max_sl = 0;
            if(count($dtcheck->Rows())>0){
                $dt_check_max_sl = Enumerable::from($dtcheck->Rows())->max('$a==>$a["sl_no"]');
            }
            // To delete existsing allocations
            $cmmDel = new \app\cwf\vsla\data\SqlCommand();
            $cmmDel->setCommandText('delete from ac.rl_pl_alloc where branch_id = :pbranch_id and voucher_id = :pvoucher_id 
                                    and account_id = :paccount_id and rl_pl_id = :prl_pl_id');
            $cmmDel->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmmDel->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmmDel->addParam('paccount_id', $this->bo->supplier_id);
            $cmmDel->addParam('prl_pl_id', $this->bo->pl_id);
            foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$ref_rl_row) {
                // Check if selected adv ref already settled for the same invoice
                // If exists delete existing rows from alloc and insert new row with the old + new amt
                $dt_check_row = Enumerable::from($dtcheck->Rows())->where('$a==>$a["rl_pl_id"]=="' . $ref_rl_row["rl_pl_id"] . '"')->toList();
                foreach ($dt_check_row as $row) {
                    $ref_rl_row['credit_amt'] = $ref_rl_row['credit_amt'] + $row['net_credit_amt'];
                }
                $cmmDel->setParamValue('prl_pl_id', $ref_rl_row['rl_pl_id']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmmDel, $cn);
            }
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$ref_tran_row) {
                // save all allocations
                $dt_check_max_sl = $dt_check_max_sl + 1;
                $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.rl_pl_alloc', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN);
                $cmm = $ac->getInsertCmm();
                $ref_tran_row['sl_no'] = $dt_check_max_sl;
                $detailpkid = md5($this->bo->voucher_id . ':' . $ref_tran_row['sl_no']);
                $cmm->setParamValue('prl_pl_id', $ref_tran_row['rl_pl_id']);
                $cmm->setParamValue('prl_pl_alloc_id', $detailpkid);
                $cmm->setParamValue('pbranch_id', $ref_tran_row['branch_id']);
                $cmm->setParamValue('pvoucher_id', $this->bo->voucher_id);
                $cmm->setParamValue('pvch_tran_id', $this->bo->voucher_id . ':' . $ref_tran_row['sl_no']);
                $cmm->setParamValue('pdoc_date', $this->bo->doc_date);
                $cmm->setParamValue('paccount_id', $ref_tran_row['account_id']);
                $cmm->setParamValue('pexch_rate', $this->bo->exch_rate);
                $cmm->setParamValue('pdebit_amt', $ref_tran_row['debit_amt']);
                $cmm->setParamValue('pdebit_amt_fc', $ref_tran_row['debit_amt_fc']);
                $cmm->setParamValue('pcredit_amt', $ref_tran_row['credit_amt']);
                $cmm->setParamValue('pcredit_amt_fc', $ref_tran_row['credit_amt_fc']);
                $cmm->setParamValue('pwrite_off_amt', 0);
                $cmm->setParamValue('pwrite_off_amt_fc', 0);
                $cmm->setParamValue('pdebit_exch_diff', 0);
                $cmm->setParamValue('pcredit_exch_diff', 0);
                $cmm->setParamValue('ptds_amt', 0);
                $cmm->setParamValue('ptds_amt_fc', 0);
                $cmm->setParamValue('pother_exp_fc', 0);
                $cmm->setParamValue('pother_exp', 0);
                $cmm->setParamValue('pnet_debit_amt', $ref_tran_row['debit_amt']);
                $cmm->setParamValue('pnet_debit_amt_fc', $ref_tran_row['debit_amt_fc']);
                $cmm->setParamValue('pnet_credit_amt', $ref_tran_row['credit_amt']);
                $cmm->setParamValue('pnet_credit_amt_fc', $ref_tran_row['credit_amt_fc']);
                $cmm->setParamValue('pstatus', 5);
                $cmm->setParamValue('pgst_tds_amt_fc', 0);
                $cmm->setParamValue('pgst_tds_amt', 0);
                $cmm->setParamValue('ptran_group', 'payable_ledger_alloc_tran');
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_tran_row['rl_pl_alloc_id'] = $detailpkid;
            }

            // Update amt in receivable ledger
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ap.sp_pl_manual_ref_post(:ppl_id, :pamt_to_be_settled) ');
            $cmm->addParam('ppl_id', $this->bo->pl_id);
            $cmm->addParam('pamt_to_be_settled', $this->bo->advance_amt);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

            // Update invoice table for the adv amt
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ap.sp_bill_manual_ref_update(:pvoucher_id, :padv_amt_tot) ');
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $cmm->addParam('padv_amt_tot', $this->bo->advance_amt);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
    
    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

}
