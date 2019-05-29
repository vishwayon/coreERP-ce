<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\journalVoucher;

use YaLinqo\Enumerable;

/**
 * Description of JournalVoucherValidator
 *
 * @author Priyanka
 */
class JournalVoucherValidator extends \app\core\ac\base\VoucherBaseValidator {

    public function validateJournalVoucherEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        parent::validateBusinessRules();

        // Validate Duplicate accounts
        $accArray = array();
        array_push($accArray, \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id') . "_" . $this->bo->account_id);
        foreach ($this->bo->vch_tran->Rows() as $row) {
            array_push($accArray, $row['branch_id'] . "_" . $row['account_id']);
        }
        foreach ($accArray as $row) {
            $accCount = 0;
            foreach ($accArray as $row1) {
                if ($row == $row1) {
                    $accCount += 1;
                }
            }
            if ($accCount > 1) {
                $this->bo->addBRule('Duplicate accounts not allowed in Account Info.');
                break;
            }
        }

        // Validate control debit and credit amount
        if ($this->bo->debit_amt > 0 && $this->bo->credit_amt > 0) {
            $this->bo->addBRule('Both debit and credit amount cannot be greater than zero.');
        }

        if ($this->bo->debit_amt == 0 && $this->bo->credit_amt == 0) {
            $this->bo->addBRule('Debit/Credit amount is required');
        }

        // If interbranch, validate all account type should not be interbranch account
        if ($this->bo->is_inter_branch) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {// Control account
                if ($dt->Rows()[0]['account_type_id'] == 45) {
                    $this->bo->addBRule('GL Account should not be of type Inter-branch.');
                }
            }
            $RowNo = 0;
            foreach ($this->bo->vch_tran->Rows() as $row) {// Tran accounts       
                $RowNo++;
                $cmm->addParam('paccount_id', $row['account_id']);
                $dt1 = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dt1->Rows()) > 0) {// Control account
                    if ($dt1->Rows()[0]['account_type_id'] == 45) {
                        $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Account should not be of type Inter-branch.');
                    }
                }
            }
        }
        // Validate Subhead total if exists for Bank Account
        // If selected account does not require Sub Head Allocation or ref allocation, remove allocated sub head or ref info  if any.
        $result = \app\core\ac\subHeadAlloc\SubHeadAllocHelper::IsDetailReqd($this->bo->account_id);
        if ($result['is_detail_reqd'] == 'false') {
            // remove sub head and ref ledger allocation 
            $sub_head_cnt = count($this->bo->shl_head_tran->Rows());
            for ($i = 0; $i <= $sub_head_cnt; $i++) {
                $this->bo->shl_head_tran->removeRow(0);
            }

            $ref_cnt = count($this->bo->rla_head_tran->Rows());
            for ($i = 0; $i <= $ref_cnt; $i++) {
                $this->bo->rla_head_tran->removeRow(0);
            }

            $this->bo->ref_no = '';
            $this->bo->ref_desc = '';
        } else if ($result['is_detail_reqd'] == 'true') {
            if ($result['sub_head_dim_id'] == -1) {// Ref Ledger reqd
                // Remove rows from sub head ledger.
                $sub_head_cnt = count($this->bo->shl_head_tran->Rows());
                for ($i = 0; $i <= $sub_head_cnt; $i++) {
                    $this->bo->shl_head_tran->removeRow(0);
                }

                if ($this->bo->ref_no == '') {
                    // Set connected branch id and document date in alloc
                    foreach ($this->bo->rla_head_tran->Rows() as &$ref_led_row) {
                        $ref_led_row['branch_id'] = $this->bo->branch_id;
                        $ref_led_row['affect_doc_date'] = $this->bo->doc_date;
                    }
                    // Validate ref ledger total 
                    $ref_debit_total = 0;
                    $ref_credit_total = 0;
                    if ($this->bo->debit_amt > 0) {
                        $ref_debit_total = round(Enumerable::from($this->bo->rla_head_tran->Rows())->sum('$a==>$a["net_debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                        if ($this->bo->debit_amt != $ref_debit_total) {
                            $this->bo->addBRule('Ref Ledger total should match with the Credits for GL Account');
                        }
                    } else {
                        $ref_credit_total = round(Enumerable::from($this->bo->rla_head_tran->Rows())->sum('$a==>$a["net_credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                        if ($this->bo->credit_amt != $ref_credit_total) {
                            $this->bo->addBRule('Ref Ledger total should match with the Credits for GL Account');
                        }
                    }
                }
            }
            if ($result['is_ref_ledger'] == 'false') {
                // Set connected document date in alloc
                foreach ($this->bo->shl_head_tran->Rows() as &$shl_row) {
                    $shl_row['branch_id'] = $this->bo->branch_id;
                    $shl_row['doc_date'] = $this->bo->doc_date;
                }
                // Remove rows from ref ledger alloc.
                $ref_cnt = count($this->bo->rla_head_tran->Rows());
                for ($i = 0; $i <= $ref_cnt; $i++) {
                    $this->bo->rla_head_tran->removeRow(0);
                }
                $this->bo->ref_no = '';
                $this->bo->ref_desc = '';
                $credit_total = 0;
                $bedit_total = 0;
                if ($this->bo->debit_amt > 0) {
                    $debit_total = round(Enumerable::from($this->bo->shl_head_tran->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                    if ($this->bo->debit_amt != $debit_total) {
                        $this->bo->addBRule('Sub head total should match with the Debits for GL Account.');
                    }
                } else {
                    $credit_total = round(Enumerable::from($this->bo->shl_head_tran->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                    if ($this->bo->credit_amt != $credit_total) {
                        $this->bo->addBRule('Sub head total should match with the Credits for GL Account.');
                    }
                }
            }
        }

        // Validate account with selected Sub Head Account
        for ($i = count($this->bo->shl_head_tran->Rows()) - 1; $i >= 0; $i--) {
            if ($this->bo->shl_head_tran->Rows()[$i]['sub_head_id'] == -1) {
                $this->bo->shl_head_tran->removeRow($i);
            }
        }

        foreach ($this->bo->shl_head_tran->Rows() as $sub_head_row) {
            if ($this->bo->account_id != $sub_head_row['account_id']) {
                $this->bo->addBRule('Sub Head details does not belong to the selected Account. Kindly revise the Sub Head Allocations.');
                break;
            }
        }

        // Validate account with selected ref ledger Account
        foreach ($this->bo->rla_head_tran->Rows() as $ref_row) {
            if ($this->bo->account_id != $ref_row['account_id']) {
                $this->bo->addBRule('Ref Ledger details does not belong to the selected Account. Kindly revise the Ref Ledger Allocations.');
                break;
            }
        }
    }

}
