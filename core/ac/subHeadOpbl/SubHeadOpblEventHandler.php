<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHeadOpbl;

use YaLinqo\Enumerable;

/**
 * Description of CustomerOPBLRefEventHandler
 *
 * @author Kaustubh
 */
class SubHeadOpblEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $from_date = strtotime('- 1 days', strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')));
        $this->bo->sub_head_ledger->getColumn("doc_date")->default = date("Y-m-d", $from_date);
        $this->bo->setTranColDefault('sub_head_ledger', 'doc_date', date("Y-m-d", $from_date));

        $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
    }

    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);

        if ($tablename == 'ac.sub_head_ledger') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select ROW_NUMBER() over(order by a.sub_head) as sl_no, a.sub_head_id, a.sub_head, b.sub_head_ledger_id, COALESCE(b.voucher_id,'') as voucher_id, COALESCE(b.vch_tran_id,'') vch_tran_id, 
                                                    COALESCE(b.doc_date, '1970-01-01') as doc_date, COALESCE(b.account_id, -1) as account_id, COALESCE(b.fc_type_id, 0) as fc_type_id, COALESCE(b.exch_rate, 1) as exch_rate,
                                            COALESCE(b.debit_amt, 0.0000) as debit_amt, 0 as debit_amt_fc, COALESCE(b.credit_amt, 0.0000) as credit_amt, 0 as credit_amt_fc,
                                            COALESCE(b.not_by_alloc, true) as not_by_alloc, COALESCE(b.narration, '') as narration
                                    from ac.sub_head a
                                    left join ac.sub_head_ledger b on a.sub_head_id = b.sub_head_id and b.account_id=:paccount_id and b.branch_id =:pbranch_id And (b.not_by_alloc = true OR left(voucher_id, 4)= 'OPBL') And b.finyear = :pfinyear
                                    where a.sub_head_dim_id = :psub_head_dim_id
                                    order by a.sub_head;");

            $cmm->addParam('paccount_id', $this->bo->account_id);
            $cmm->addParam('psub_head_dim_id', $this->bo->sub_head_dim_id);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $dtTran = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($dtTran->Rows() as $row) {
                $newRow = $this->bo->sub_head_ledger->NewRow();
                $newRow['sl_no'] = $row['sl_no'];
                $newRow['sub_head_ledger_id'] = $row['sub_head_ledger_id'];
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $newRow['voucher_id'] = $row['voucher_id'];
                $newRow['vch_tran_id'] = $row['vch_tran_id'];
                if (strtotime($row['doc_date']) == strtotime('1970-01-01')) {
                    $from_date = strtotime('- 1 days', strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')));
                    $newRow['doc_date'] = date("Y-m-d", $from_date);
                } else {
                    $newRow['doc_date'] = $row['doc_date'];
                }
                $newRow['account_id'] = $row['account_id'];
                $newRow['sub_head_id'] = $row['sub_head_id'];
                $newRow['fc_type_id'] = $row['fc_type_id'];
                $newRow['exch_rate'] = $row['exch_rate'];
                $newRow['debit_amt_fc'] = $row['debit_amt_fc'];
                $newRow['credit_amt_fc'] = $row['credit_amt_fc'];
                $newRow['debit_amt'] = $row['debit_amt'];
                $newRow['credit_amt'] = $row['credit_amt'];
                $newRow['narration'] = $row['narration'];
                $newRow['not_by_alloc'] = $row['not_by_alloc'];
                $newRow['sub_head'] = $row['sub_head'];
                $this->bo->sub_head_ledger->AddRow($newRow);
            }
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        // Avoid saving the base document as it is used only as an anchor
        // Base document data cannot be changed from here
        // Save the Opening Balance entries made by the user
        if ($tablename == 'ac.sub_head_ledger') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM ac.sub_head_ledger '
                    . 'WHERE sub_head_ledger_id=:psub_head_ledger_id ');

            foreach ($this->bo->sub_head_ledger->Rows() as $row) {
                if (($row['sub_head_ledger_id'] != '') && ($row['debit_amt'] == 0 or $row['credit_amt'] == 0)) {
                    $cmm->addParam('psub_head_ledger_id', $row['sub_head_ledger_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                } 
            }

            //Add rows to ac.sub_head_ledger
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from ac.sp_sub_head_opbl_add_update(:psub_head_ledger_id, :pcompany_id, :pbranch_id, :pfinyear, :pvoucher_id,
                :pdoc_date, :paccount_id, :psub_head_id, :pfc_type_id, :pexch_rate, :pdebit_amt_fc, :pcredit_amt_fc, 
                :pdebit_amt, :pcredit_amt, :pnarration)');


            foreach ($this->bo->sub_head_ledger->Rows() as &$ref_row) {
                if ($ref_row['debit_amt'] > 0 or $ref_row['credit_amt'] > 0) {
                    $shl_id = $ref_row['sub_head_ledger_id'];
                    $finyear = $ref_row['finyear'];
                    if ($ref_row['sub_head_ledger_id'] == null || $ref_row['sub_head_ledger_id'] == '') {
                        $ref_row['voucher_id'] = 'OPBL/' . \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear') . '/' . \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id')
                                . '/' . $this->bo->account_id . '/' . $ref_row['sub_head_id'];
                        $finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
                        $shl_id = md5($ref_row['voucher_id']);
                    }
                    $cmm->addParam('psub_head_ledger_id', $shl_id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);

                    $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                    $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                    $cmm->addParam('pvoucher_id', $ref_row['voucher_id']);
                    $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
                    $cmm->addParam('pdoc_date', $ref_row['doc_date']);
                    $cmm->addParam('paccount_id', $this->bo->account_id);
                    $cmm->addParam('psub_head_id', $ref_row['sub_head_id']);
                    $cmm->addParam('pfc_type_id', $ref_row['fc_type_id']);
                    $cmm->addParam('pexch_rate', $ref_row['exch_rate']);
                    $cmm->addParam('pdebit_amt_fc', $ref_row['debit_amt_fc']);
                    $cmm->addParam('pcredit_amt_fc', $ref_row['credit_amt_fc']);
                    $cmm->addParam('pdebit_amt', $ref_row['debit_amt']);
                    $cmm->addParam('pcredit_amt', $ref_row['credit_amt']);
                    $cmm->addParam('pnarration', $ref_row['narration']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                    $ref_row['sub_head_ledger_id'] = $cmm->getParamValue('psub_head_ledger_id');
                }
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}
