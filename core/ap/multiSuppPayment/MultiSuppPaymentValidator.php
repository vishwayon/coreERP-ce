<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\multiSuppPayment;

use YaLinqo\Enumerable;

/**
 * Description of MultiSuppPayment
 *
 * @author Priyanka
 */
class MultiSuppPaymentValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateMultiSuppPaymentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select currency, sub_currency, currency_system from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If ($this->bo->credit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->credit_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }

        if ($this->bo->fc_type_id == 0) {
            $row['credit_amt_fc'] = 0;
            $row['net_credit_amt_fc'] = 0;
        }

        foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$refrow) {
            // Avoid server side calculations. Only reset values where required.
            if ($this->bo->fc_type_id == 0) {
                $refrow['debit_amt_fc'] = 0;
                $refrow['net_debit_amt_fc'] = 0;
                $refrow['write_off_amt_fc'] = 0;
            }
            $refrow['doc_date'] = $this->bo->doc_date;
            $refrow['exch_rate'] = $this->bo->exch_rate;
        }

        // Check if payment is blocked for selected supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select (annex_info->>'block_pymt')::boolean block_pymt from ap.supplier where supplier_id = :psupplier_id");
        $cmm->addParam('psupplier_id', -1);

        $RowNo = 0;
        foreach ($this->bo->payable_ledger_alloc_tran->Rows() as $tran) {
                $RowNo++;
            $cmm->setParamValue('psupplier_id', $tran['account_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                if ($dt->Rows()[0]['block_pymt'] == TRUE) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Payments are blocked for ' . \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $tran['account_id']));
                }
            }
        }

        if (!$this->bo->is_inter_branch) {
            $cnt = Enumerable::from($this->bo->payable_ledger_alloc_tran->Rows())->distinct('$a==>$a["branch_id"]')->count();
            if ($cnt > 1) {
                $this->bo->addBRule('Cannot select Bills accross branches for Normal Payment.');
            }
        }
        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }

        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->pymt_type == 0) {
                if ($acc_type_id != 1 && $acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash Bank account.');
                }
            } else if ($this->bo->pymt_type == 1) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 45) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            }
        }

        $RowNo = 0;
        if ($this->bo->fc_type_id == 0) {
            foreach ($this->bo->payable_ledger_alloc_tran->Rows() as $tran) {
                $RowNo++;
                if (floatval($tran['net_debit_amt']) > floatval($tran['balance'])) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Net Settled amount cannot be greater than Balance.');
                }
                if (floatval($tran['net_debit_amt']) == 0) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Net Settled amount is required');
                }
                if (floatval($tran['debit_amt']) == 0) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Settled amount is required');
                }
            }
        } else if ($this->bo->fc_type_id != 0) {
            foreach ($this->bo->payable_ledger_alloc_tran->Rows() as $tran) {
                $RowNo++;
                if ($tran['net_debit_amt_fc'] > $tran['balance_fc']) {
                    $this->bo->addBRule('Row[' . $RowNo . ']:Net Settled amount FC cannot be greater than Balance FC.');
                }
                if ($tran['net_debit_amt_fc'] == 0) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Net Settled amount FC is required');
                }
                if ($tran['debit_amt_fc'] == 0) {
                    $this->bo->addBRule('Payable Allocations - Row[' . $RowNo . '] : Settled amount FC is required');
                }
            }
        }
        
        // validate settlements for date
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('With pl_tran
            As
            (	Select x.rl_pl_id, -x.debit_amt as alloc_amt
                    From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, debit_amt Numeric(18,4))
            )
            Select a.rl_pl_id, a.voucher_id
            From ac.rl_pl a 
            Inner Join pl_tran b On a.rl_pl_id = b.rl_pl_id
            where a.doc_date > :pdoc_date');
        $cmm->addParam('pdoc_date', $this->bo->doc_date);
        $current_alloc = $this->bo->payable_ledger_alloc_tran->select(['rl_pl_id', 'debit_amt']);
        $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
        $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtExcess->Rows())>0) {
            $this->bo->addBRule('Document Date preceeds Bill settlement(s) ['.$dtExcess->Rows()[0]['voucher_id'].']. Kindly resettle the bill.');
        }
            
        // validate excess settlements
        if($this->bo->fc_type_id == 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('With pl_tran
                As
                (	Select x.rl_pl_id, -x.debit_amt as alloc_amt
                        From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, debit_amt Numeric(18,4))
                ),
                pl_settle
                As
                (	-- All origins
                    Select a.rl_pl_id, (a.credit_amt-a.debit_amt) as balance_amt
                    From ac.rl_pl a
                    Inner Join pl_tran b On a.rl_pl_id = b.rl_pl_id
                    Union All -- All allocs without the current voucher
                    Select b.rl_pl_id, -(b.debit_amt-b.credit_amt) 
                    From ac.rl_pl_alloc b
                    Inner Join pl_tran c On b.rl_pl_id = c.rl_pl_id
                    Where b.voucher_id != :pvoucher_id
                    Union All -- allocations in current voucher
                    Select a.rl_pl_id, a.alloc_amt
                    From pl_tran a
                )
                Select a.rl_pl_id, b.voucher_id, Sum(a.balance_amt)
                From pl_settle a 
                Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                Group by a.rl_pl_id, b.voucher_id
                Having Sum(a.balance_amt) < 0;');
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $current_alloc = $this->bo->payable_ledger_alloc_tran->select(['rl_pl_id', 'debit_amt']);
            $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
            $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtExcess->Rows())>0) {
                $this->bo->addBRule('Bill settlement(s) exceed balance available for ['.$dtExcess->Rows()[0]['voucher_id'].']. Kindly resettle the bill.');
            }
        } else {
            // Todo: Validate the FC amounts only
        }
    }

    public function validateBeforeDelete() {
        if ($this->bo->collected) {
            $this->bo->addBRule('This voucher has reconciled items. Cannot be deleted.');
        }
        parent::validateBeforeDelete();
    }

    public function validateBeforeUnpost() {        
        // If reconciled, don't allow to unpost   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from ap.pymt_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be deleted.');
            }
        }
        
        if($this->bo->is_reversed){
            $this->bo->addBRule('This voucher has been reversed. Cannot be unposted.');
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
        
        If ($this->bo->credit_amt == 0) {
            $this->bo->addBRule('Total Amount should be greater than zero.');
        }
    }
}
