<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppManualSet;

use YaLinqo\Enumerable;

/**
 * Description of SuppManualSetValidator
 *
 * @author priyanka
 */
class SuppManualSetValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSuppManualSetEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function docIsCurrent() {
        // Overridden as last updated validation is not required.
        // And anchoring table ar.customer is for cwf purposes only
        return true;
    }

    protected function validateBusinessRules() {

        if (!$this->validateDateValue($this->bo->doc_date)) {
            $this->bo->addBRule('Document date is not a valid date for selected financial year');
        }

        $row_no = 0;
        $alloc_amt = 0;
        foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$ref_row) {
            $row_no = $row_no + 1;
            $ref_row['sl_no'] = $row_no;
            $alloc_amt = $alloc_amt + $ref_row['credit_amt'];
        }

        // Validate Adv Alloc
        \app\core\ap\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->supplier_id, $this->bo->voucher_id);

        // This validates excess allocations with effect to the current voucher.(required if clicks on Save multiple times) 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('With pl_tran
                As
                (	Select x.rl_pl_id, -x.credit_amt as alloc_amt
                    From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, credit_amt Numeric(18,4))
                ),
                pl_settle
                As
                (	-- All origins
                    Select a.rl_pl_id, (a.debit_amt-a.credit_amt) as balance_amt
                    From ac.rl_pl a
                    Inner Join pl_tran b On a.rl_pl_id = b.rl_pl_id
                    Union All -- All allocs without the current voucher
                    Select b.rl_pl_id, -(b.credit_amt-b.debit_amt) 
                    From ac.rl_pl_alloc b 
                    Inner Join pl_tran c On b.rl_pl_id = c.rl_pl_id
                    Union All -- allocations in current voucher
                    Select a.rl_pl_id, a.alloc_amt
                    From pl_tran a
                )
                Select a.rl_pl_id, b.voucher_id, Sum(a.balance_amt)
                From pl_settle a 
                Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                Group by a.rl_pl_id, b.voucher_id
                Having Sum(a.balance_amt) < 0;');
        $current_alloc = $this->bo->payable_ledger_alloc_tran->select(['rl_pl_id', 'credit_amt']);
        $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
        $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtExcess->Rows()) > 0) {
            $this->bo->addBRule('Advance settlement(s) exceed balance available for [' . $dtExcess->Rows()[0]['voucher_id'] . ']. Kindly resettle advances.');
        }

        
        
        // This is to validate current balance with settlements
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select sum(a.balance) balance
                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, '', 'C') a
                where a.voucher_id = :pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $cmm->addParam('paccount_id', $this->bo->supplier_id);
        $cmm->addParam('pto_date', $this->bo->doc_date);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtBal = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $bal = 0;
        if (count($dtBal->Rows()) == 1) {
            $bal = $dtBal->Rows()[0]['balance'];
        }

        if (bcsub(strval($alloc_amt), strval($bal), 2) > 0) {
            $this->bo->addBRule('Bill settlement(s) exceed bill balance. Excess settlement(s) not allowed.');
        }
    }

}
