<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\base;

/**
 * Description of BankPaymentEventHandler
 *
 * @author Priyanka
 */
class VoucherBaseEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        \app\core\ac\subHeadAlloc\SubHeadAllocHelper::CreateAllocTemp($this->bo);
        \app\core\ac\subHeadAlloc\SubHeadAllocHelper::CreateRefAllocTemp($this->bo);

        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->voucher_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            //$this->bo->cheque_number="0"; 
            $this->bo->status = 0;
            if (strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $this->bo->doc_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        } else {
            foreach ($this->bo->ref_ledger_tran->Rows() as $tran_row) {
                foreach ($this->bo->vch_tran->Rows() as &$ref_row) {
                    if($tran_row['vch_tran_id'] == $ref_row['vch_tran_id']){
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
        }
        $this->bo->vch_tran->getColumn("branch_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $this->bo->setTranColDefault('vch_tran', 'branch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);

        if ($tablename == 'ac.ref_ledger') {
            foreach ($this->bo->vch_tran->Rows() as &$ref_tran_row) {
                if ($ref_tran_row['ref_no'] != '') {
                    $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('ac.ref_ledger', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);

                    if ($ref_tran_row['ref_ledger_id'] == '') {
                        $cmm = $ac->getInsertCmm();
                        $detailpkid = md5($this->bo->voucher_id . ':' . $ref_tran_row['branch_id'] . ':' . $ref_tran_row['account_id']);
                    } else {
                        $cmm = $ac->getUpdateCmm();
                        $detailpkid = $ref_tran_row['ref_ledger_id'];
                    }
                    $cmm->setParamValue('pref_ledger_id', $detailpkid);
                    $cmm->setParamValue('pvoucher_id', $this->bo->voucher_id);
                    $cmm->setParamValue('pvch_tran_id', $ref_tran_row['vch_tran_id']);
                    $cmm->setParamValue('pdoc_date', $this->bo->doc_date);
                    $cmm->setParamValue('paccount_id', $ref_tran_row['account_id']);
                    $cmm->setParamValue('pbranch_id', $ref_tran_row['branch_id']);
                    $cmm->setParamValue('pref_no', $ref_tran_row['ref_no']);
                    $cmm->setParamValue('pref_desc', $ref_tran_row['ref_desc']);
                    $cmm->setParamValue('pdebit_amt', $ref_tran_row['debit_amt']);
                    $cmm->setParamValue('pcredit_amt', $ref_tran_row['credit_amt']);
                    $cmm->setParamValue('pstatus', $this->bo->status);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                    $ref_tran_row['ref_ledger_id'] = $detailpkid;
                }
            }
        }
    }

}
