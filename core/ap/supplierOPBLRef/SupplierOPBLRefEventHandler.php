<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierOPBLRef;

use YaLinqo\Enumerable;

/**
 * Description of SupplierOPBLRefEventHandler
 *
 * @author Priyanka
 */
class SupplierOPBLRefEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $this->bo->supplier_payable_ledger_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->supplier_payable_ledger_temp->cloneColumns($this->bo->supplier_payable_ledger);
        $this->fillPayableLedgerTemp();


        $from_date = strtotime('- 1 days', strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')));

        $this->bo->supplier_payable_ledger->getColumn("doc_date")->default = date("Y-m-d", $from_date);
        $this->bo->setTranColDefault('supplier_payable_ledger', 'doc_date', date("Y-m-d", $from_date));

        $this->bo->supplier_payable_ledger->getColumn("fc_type_id")->default = 0;
        $this->bo->setTranColDefault('supplier_payable_ledger', 'fc_type_id', 0);

        $this->bo->supplier_payable_ledger->getColumn("exch_rate")->default = 1;
        $this->bo->setTranColDefault('supplier_payable_ledger', 'exch_rate', 1);

        $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
    }

    private function fillPayableLedgerTemp() {
        $rowcount = count($this->bo->supplier_payable_ledger_temp->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->supplier_payable_ledger_temp->removeRow(0);
        }

        foreach ($this->bo->supplier_payable_ledger->Rows() as $row) {
            $newRow = $this->bo->supplier_payable_ledger_temp->NewRow();
            $newRow['is_allow_edit'] = $row['is_allow_edit'];
            $newRow['rl_pl_id'] = $row['rl_pl_id'];
            $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $newRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $newRow['voucher_id'] = $row['voucher_id'];
            $newRow['vch_tran_id'] = $row['vch_tran_id'];
            $newRow['doc_date'] = $row['doc_date'];
            $newRow['account_id'] = $row['account_id'];
            $newRow['bill_no'] = $row['bill_no'];
            $newRow['bill_date'] = $row['bill_date'];
            $newRow['fc_type_id'] = $row['fc_type_id'];
            $newRow['exch_rate'] = $row['exch_rate'];
            $newRow['debit_amt_fc'] = $row['debit_amt_fc'];
            $newRow['credit_amt_fc'] = $row['credit_amt_fc'];
            $newRow['debit_amt'] = $row['debit_amt'];
            $newRow['credit_amt'] = $row['credit_amt'];
            $newRow['narration'] = $row['narration'];
            $newRow['en_bill_type'] = $row['en_bill_type'];
            $newRow['is_opbl'] = $row['is_opbl'];
            $this->bo->supplier_payable_ledger_temp->AddRow($newRow);
        }
    }

    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);

        if ($tablename == 'ac.rl_pl') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select case when exists (Select b.* from ac.rl_pl_alloc b where b.rl_pl_id = a.rl_pl_id)  then false
                                        Else true 
                                        End as is_allow_edit, a.rl_pl_id, a.company_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, 
                                    a.bill_no, a.bill_date, a.fc_type_id, a.exch_rate, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, 
                                    a.credit_amt, a.narration, a.en_bill_type, a.is_opbl
                                from ac.rl_pl a
                                where a.is_opbl=true and a.account_id=:paccount_id and a.branch_id =:pbranch_id;');

            $cmm->addParam('paccount_id', $this->bo->supplier_id);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $dtTran = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($dtTran->Rows() as $row) {
                $newRow = $this->bo->supplier_payable_ledger->NewRow();
                $newRow['is_allow_edit'] = $row['is_allow_edit'];
                $newRow['rl_pl_id'] = $row['rl_pl_id'];
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                $newRow['voucher_id'] = $row['voucher_id'];
                $newRow['vch_tran_id'] = $row['vch_tran_id'];
                $newRow['doc_date'] = $row['doc_date'];
                $newRow['account_id'] = $row['account_id'];
                $newRow['bill_no'] = $row['bill_no'];
                $newRow['bill_date'] = $row['bill_date'];
                $newRow['fc_type_id'] = $row['fc_type_id'];
                $newRow['exch_rate'] = $row['exch_rate'];
                $newRow['debit_amt_fc'] = $row['debit_amt_fc'];
                $newRow['credit_amt_fc'] = $row['credit_amt_fc'];
                $newRow['debit_amt'] = $row['debit_amt'];
                $newRow['credit_amt'] = $row['credit_amt'];
                $newRow['narration'] = $row['narration'];
                $newRow['en_bill_type'] = $row['en_bill_type'];
                $newRow['is_opbl'] = $row['is_opbl'];
                $this->bo->supplier_payable_ledger->AddRow($newRow);
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
        if ($tablename == 'ac.rl_pl') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM ac.rl_pl '
                    . 'WHERE rl_pl_id=:prl_pl_id ');

            foreach ($this->bo->supplier_payable_ledger_temp->Rows() as $temprow) {
                $deletedrow = true;
                foreach ($this->bo->supplier_payable_ledger->Rows() as $row) {
                    if ($row['rl_pl_id'] == $temprow['rl_pl_id']) {
                        $deletedrow = false;
                        break;
                    }
                    $deletedrow = TRUE;
                }

                if ($deletedrow) {
                    $cmm->addParam('prl_pl_id', $temprow['rl_pl_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }

            //Add rows to ac.rl_pl
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from ap.sp_supplier_opbl_ref_add_update(:prl_pl_id, :pcompany_id, :pbranch_id, :pvoucher_id,
                :pdoc_date, :paccount_id, :pbill_no, :pbill_date, :pfc_type_id, :pexch_rate, :pdebit_amt_fc, :pcredit_amt_fc, 
                :pdebit_amt, :pcredit_amt, :pnarration, :pen_bill_type)');
            $v_id = -1;
            foreach ($this->bo->supplier_payable_ledger->Rows() as &$ref_row) {
                $pl_id = $ref_row['rl_pl_id'];
                if ($ref_row['rl_pl_id'] == null || $ref_row['rl_pl_id'] == '') {
                    $pl_id = md5(\app\cwf\vsla\entity\EntityManager::getDocSeqID('OPBL', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'), 'ap.supplier_opbl_ref', $cn, $v_id));
                }
                $cmm->addParam('prl_pl_id', $pl_id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);

                $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                $cmm->addParam('pvoucher_id', $ref_row['voucher_id']);
                $cmm->addParam('pdoc_date', $ref_row['doc_date']);
                $cmm->addParam('paccount_id', $this->bo->supplier_id);
                $cmm->addParam('pbill_no', '');
                $cmm->addParam('pbill_date', $ref_row['doc_date']);
                $cmm->addParam('pfc_type_id', $ref_row['fc_type_id']);
                $cmm->addParam('pexch_rate', $ref_row['exch_rate']);
                $cmm->addParam('pdebit_amt_fc', $ref_row['debit_amt_fc']);
                $cmm->addParam('pcredit_amt_fc', $ref_row['credit_amt_fc']);
                $cmm->addParam('pdebit_amt', $ref_row['debit_amt']);
                $cmm->addParam('pcredit_amt', $ref_row['credit_amt']);
                $cmm->addParam('pnarration', $ref_row['narration']);
                if ($ref_row['debit_amt'] > 0) {
                    $cmm->addParam('pen_bill_type', 1);
                } else {
                    $cmm->addParam('pen_bill_type', 0);
                }
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_row['rl_pl_id'] = $cmm->getParamValue('prl_pl_id');
            }

            // Update or insert Customer balance in Account Balance Table
            $credit_total = round(Enumerable::from($this->bo->supplier_payable_ledger->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
            $debit_total = round(Enumerable::from($this->bo->supplier_payable_ledger->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
            $balance = round(($debit_total - $credit_total), \app\cwf\vsla\Math::$amtScale);

            // Insert records in account balance for new financial year
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select * from ac.sp_account_balance_add_update(:pcompany_id, :pbranch_id, :paccount_id, :pbalance)");
            $cmm->addParam('paccount_id', $this->bo->supplier_id);
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pbalance', $balance);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $this->fillPayableLedgerTemp();
    }
    
    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

}
