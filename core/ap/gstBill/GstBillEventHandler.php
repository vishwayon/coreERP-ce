<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\gstBill;

use YaLinqo\Enumerable;

/**
 * Description of GstBillEventHandler
 *
 * @author Priyanka
 */
class GstBillEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);

        \app\core\ac\subHeadAlloc\SubHeadAllocHelper::CreateRefAllocTemp($this->bo);
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ap_bill_gtt_ovrd');
        if ($value == "0") {
            $this->bo->allow_gtt_ovrd = FALSE;
        } else {
            $this->bo->allow_gtt_ovrd = true;
        }
        $this->bo->bill_tran->getColumn("branch_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $this->bo->setTranColDefault('bill_tran', 'branch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));

        if ($this->bo->bill_id == "" or $this->bo->bill_id == "-1") {
            $this->bo->bill_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->en_bill_action = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
            $this->bo->annex_info->Value()->tds_net_adv = true;
        } else {

            // Fetch Adv alloc details
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->bill_id);

            foreach ($this->bo->ref_ledger_tran->Rows() as $tran_row) {
                foreach ($this->bo->bill_tran->Rows() as &$ref_row) {
                    if ($tran_row['vch_tran_id'] == $ref_row['bill_tran_id']) {
                        $ref_row['ref_no'] = $tran_row['ref_no'];
                        $ref_row['ref_desc'] = $tran_row['ref_desc'];
                        $ref_row['ref_ledger_id'] = $tran_row['ref_ledger_id'];
                        if (count($ref_row['ref_ledger_alloc_tran']->Rows()) > 0) {
                            $ref_row['is_create_ref'] = false;
                            $ref_row['is_alloc_ref'] = true;
                        } else {
                            $ref_row['is_create_ref'] = true;
                            $ref_row['is_alloc_ref'] = false;
                        }
                        break;
                    }
                }
            }
            
            // Get supplier person type details on edit
            if($this->bo->btt_person_type_id == -1){                
                $supp_tds_info = \app\core\tds\worker\TDSWorker::SuppTDSInfo($this->bo->supplier_id);
                if (count($supp_tds_info->Rows()) > 0) {
                    $this->bo->btt_person_type_id = $supp_tds_info->Rows()[0]['tds_person_type_id'];
                    $this->bo->btt_section_id = $supp_tds_info->Rows()[0]['tds_section_id'];
                }
            }
        }
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_id, $this->bo->doc_date);
        }
        
        $this->bo->select_po_visible = (bool)\app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ap_gstbill_po_select_visible');
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_id, $this->bo->doc_date);
        }
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);

        if ($tablename == 'ac.ref_ledger') {
            foreach ($this->bo->bill_tran->Rows() as &$ref_tran_row) {
                if ($ref_tran_row['ref_no'] != '') {
                    $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.ref_ledger', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);

                    if ($ref_tran_row['ref_ledger_id'] == '') {
                        $cmm = $ac->getInsertCmm();
                        $detailpkid = md5($this->bo->bill_id . ':' . \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id') . ':' . $ref_tran_row['account_id']);
                    } else {
                        $cmm = $ac->getUpdateCmm();
                        $detailpkid = $ref_tran_row['ref_ledger_id'];
                    }
                    $cmm->setParamValue('pref_ledger_id', $detailpkid);
                    $cmm->setParamValue('pvoucher_id', $this->bo->bill_id);
                    $cmm->setParamValue('pvch_tran_id', $ref_tran_row['bill_tran_id']);
                    $cmm->setParamValue('pdoc_date', $this->bo->doc_date);
                    $cmm->setParamValue('paccount_id', $ref_tran_row['account_id']);
                    $cmm->setParamValue('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                    $cmm->setParamValue('pref_no', $ref_tran_row['ref_no']);
                    $cmm->setParamValue('pref_desc', $ref_tran_row['ref_desc']);
                    $cmm->setParamValue('pdebit_amt', $ref_tran_row['debit_amt']);
                    $cmm->setParamValue('pcredit_amt', 0);
                    $cmm->setParamValue('pstatus', $this->bo->status);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                    $ref_tran_row['ref_ledger_id'] = $detailpkid;
                }
            }
        }
    }
}
