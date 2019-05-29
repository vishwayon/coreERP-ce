<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\multiCustReceipt;

use \app\cwf\vsla\Math;
use YaLinqo\Enumerable;

/**
 * Description of MultiCustReceiptValidator
 *
 * @author Priyanka
 */
class MultiCustReceiptValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateMultiCustReceiptEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {

//        if($this->bo->rcpt_type != 2){            
//            $rowcount = count($this->bo->payable_ledger_alloc_tran->Rows());
//            for ($i = 0; $i <= $rowcount; $i++) {
//                $this->bo->payable_ledger_alloc_tran->removeRow(0);
//            }
//        }
        if ($this->bo->rcpt_type == 2) { // if type is AR to AP do not allow adv and other adj  
            if ($this->bo->adv_amt > 0 || $this->bo->adv_amt_fc > 0) {
                $this->bo->addBRule('Advance Amount should be zero for Settlemet Type AR to AP.');
            }
            if ($this->bo->annex_info->value()->other_adj > 0 || $this->bo->annex_info->value()->other_adj_fc > 0) {
                $this->bo->addBRule('Other Adjustments not allowed for Settlemet Type AR to AP.');
            }
        }
        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }

        $tranrowNo = 0;
        foreach ($this->bo->rcpt_tran->Rows() as &$reftranrow) {
            $tranrowNo++;
            $reftranrow['sl_no'] = $tranrowNo;
        }

        $tranrowNo = 0;
        foreach ($this->bo->rcpt_adv_tran->Rows() as &$refadvrow) {
            $tranrowNo++;
            $refadvrow['sl_no'] = $tranrowNo;
        }

//        // Update rcpt date in PL alloc
//        foreach ($this->bo->payable_ledger_alloc_tran->Rows() as &$ref_plrow) {
//            $ref_plrow['doc_date'] = $this->bo->doc_date;
//            $ref_plrow['exch_rate'] = $this->bo->exch_rate;
//        }

        $refrowNo = 0;
        foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as &$refrow) {
            $refrowNo++;
            if ($this->bo->fc_type_id == 0) {
                $refrow['credit_amt_fc'] = 0;
                $refrow['net_credit_amt_fc'] = 0;
            } else {
                $refrow['credit_amt'] = round(($refrow['credit_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                $refrow['write_off_amt'] = round(($refrow['write_off_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                $refrow['tds_amt'] = round(($refrow['tds_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                $refrow['gst_tds_amt'] = round(($refrow['gst_tds_amt_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                $refrow['other_exp'] = round(($refrow['other_exp_fc'] * $this->bo->exch_rate), \app\cwf\vsla\Math::$amtScale);
                $refrow['net_credit_amt_fc'] = round($refrow['credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) 
                        + (round($refrow['write_off_amt_fc'], \app\cwf\vsla\Math::$amtScale)
                        + round($refrow['tds_amt_fc'], \app\cwf\vsla\Math::$amtScale) 
                        + round($refrow['gst_tds_amt_fc'], \app\cwf\vsla\Math::$amtScale) 
                        + round($refrow['other_exp_fc'], \app\cwf\vsla\Math::$amtScale));
            }
            $refrow['net_credit_amt'] = round($refrow['credit_amt'], \app\cwf\vsla\Math::$amtScale) 
                    + (round($refrow['write_off_amt'], \app\cwf\vsla\Math::$amtScale) 
                    + round($refrow['tds_amt'], \app\cwf\vsla\Math::$amtScale) 
                    + round($refrow['gst_tds_amt'], \app\cwf\vsla\Math::$amtScale) 
                    + round($refrow['other_exp'], \app\cwf\vsla\Math::$amtScale)) 
                    + round($refrow['credit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
            $refrow['doc_date'] = $this->bo->doc_date;
            $refrow['exch_rate'] = $this->bo->exch_rate;
        }

        if (!$this->bo->is_inter_branch) {
            $cnt = Enumerable::from($this->bo->receivable_ledger_alloc_tran->Rows())->distinct('$a==>$a["branch_id"]')->count();
            if ($cnt > 1) {
                $this->bo->addBRule('Cannot select Invoices accross branches for Normal Receipt.');
            }
        }

        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If ($this->bo->debit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->debit_amt_fc > 0) {

            // Fetch currency and sub currency for selected FC
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select currency, sub_currency from ac.fc_type where fc_type_id=:pfc_type_id');
            $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
            $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtfc->Rows()) > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt_fc);
                $this->bo->amt_in_words_fc = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $dtfc->Rows()[0]['currency'], $dtfc->Rows()[0]['sub_currency'], $currency_system);
            }
        }

        $RowNo = 0;
        foreach ($this->bo->receivable_ledger_alloc_tran->Rows() as $tran) {
            $RowNo++;
            if ($this->bo->fc_type_id == 0) {
                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) > round($tran['balance'], \app\cwf\vsla\Math::$amtScale)) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . ']: Net Settled amount cannot be greater than Balance');
                }
                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) == 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Settled Amount is required');
                }
//                if(round($tran['credit_amt'], \app\cwf\vsla\Math::$amtScale) == 0){
//                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Settled Amount is required');                 
//                }
                if (round($tran['credit_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Received amount cannot be negative');
                }
                if (round($tran['write_off_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Dis. cannot be negative');
                }
                if (round($tran['tds_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Tax Ded./With. cannot be negative');
                }
                if (round($tran['other_exp'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Other Exp. cannot be negative');
                }
                if (round($tran['net_credit_amt'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Settled cannot be negative');
                }
            } else if ($this->bo->fc_type_id != 0) {
                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) > round($tran['balance_fc'], \app\cwf\vsla\Math::$amtScale)) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Settled amount FC cannot be greater than Balance FC');
                }
                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) == 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Settled amount FC is required');
                }
                if (round($tran['credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Received amount FC cannot be negative');
                }
                if (round($tran['write_off_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Dis. FC cannot be negative');
                }
                if (round($tran['tds_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Tax Ded./With. FC cannot be negative');
                }
                if (round($tran['other_exp_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Other Exp. FC cannot be negative');
                }
                if (round($tran['net_credit_amt_fc'], \app\cwf\vsla\Math::$amtScale) < 0) {
                    $this->bo->addBRule('Receivable Allocations - Row[' . $RowNo . '] : Net Settled FC cannot be negative');
                }
            }
        }

        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->rcpt_type == 0) {
                if ($acc_type_id != 1 && $acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash Bank account.');
                }
            } else if ($this->bo->rcpt_type == 1) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 45) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            } else if ($this->bo->rcpt_type == 2) {
                if ($acc_type_id != 12) {
                    $this->bo->addBRule('Please select Supplier account.');
                }
            }
        }

        // validate excess settlements
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('With rl_tran
            As
            (	Select x.rl_pl_id, -x.credit_amt as alloc_amt
                    From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, credit_amt Numeric(18,4))
            )
            Select a.rl_pl_id, a.voucher_id
            From ac.rl_pl a 
            Inner Join rl_tran b On a.rl_pl_id = b.rl_pl_id
            where a.doc_date > :pdoc_date');
        $cmm->addParam('pdoc_date', $this->bo->doc_date);
        $current_alloc = $this->bo->receivable_ledger_alloc_tran->select(['rl_pl_id', 'credit_amt']);
        $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
        $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtExcess->Rows()) > 0) {
            $this->bo->addBRule('Document Date preceeds Invoice settlement(s) [' . $dtExcess->Rows()[0]['voucher_id'] . ']. Kindly resettle the receipt.');
        }

        // validate excess settlements
        if ($this->bo->fc_type_id == 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('With rl_tran
                As
                (	Select x.rl_pl_id, -x.credit_amt as alloc_amt
                        From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, credit_amt Numeric(18,4))
                ),
                rl_settle
                As
                (	-- All origins
                    Select a.rl_pl_id, (a.debit_amt-a.credit_amt) as balance_amt
                    From ac.rl_pl a
                    Inner Join rl_tran b On a.rl_pl_id = b.rl_pl_id
                    Union All -- All allocs without the current voucher
                    Select b.rl_pl_id, -(b.credit_amt-b.debit_amt) 
                    From ac.rl_pl_alloc b
                    Inner Join rl_tran c On b.rl_pl_id = c.rl_pl_id
                    Where b.voucher_id != :pvoucher_id
                    Union All -- allocations in current voucher
                    Select a.rl_pl_id, a.alloc_amt
                    From rl_tran a
                )
                Select a.rl_pl_id, b.voucher_id, Sum(a.balance_amt)
                From rl_settle a 
                Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                Group by a.rl_pl_id, b.voucher_id
                Having Sum(a.balance_amt) < 0;');
            $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
            $current_alloc = $this->bo->receivable_ledger_alloc_tran->select(['rl_pl_id', 'credit_amt']);
            $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
            $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtExcess->Rows()) > 0) {
                $this->bo->addBRule('Invoice settlement(s) exceed balance available for [' . $dtExcess->Rows()[0]['voucher_id'] . ']. Kindly resettle the invoice.');
            }
        } else {
            // Todo: Validate the FC amounts only
        }

        if ($this->bo->received_from == '') {
            $this->bo->received_from = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->customer_account_id);
        }
    }

    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
    }

    public function validateBeforeUnpost() {

        // If reconciled, don't allow to unpost   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from ar.rcpt_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be deleted.');
            }
        }

        // Validate if the advance entered in allocated in any invoice
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select voucher_id from ac.rl_pl_alloc
                            where rl_pl_id in (select rl_pl_id from ac.rl_pl where voucher_id = :pvoucher_id)');
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $msgstr = '';
            foreach ($result->Rows() as $row) {
                if ($msgstr == '') {
                    $msgstr = $row['voucher_id'];
                } else {
                    $msgstr = $msgstr . ', ' . $row['voucher_id'];
                }
            }
            $this->bo->addBRule('Cannot Unpost as advance is already settled in invoice(s) - ' . $msgstr . ' .');
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
        
        bcscale(Math::$amtScale); // set scale to amtScale
        if ($this->bo->fc_type_id == 0) {
            $lefttot = bcadd($this->bo->credit_amt_total, "0");
            $lefttot = bcadd($this->bo->adv_amt, $lefttot);
            $lefttot = bcadd($this->bo->annex_info->value()->other_adj, $lefttot);
            $netset = bcadd($this->bo->net_settled, "0");

            if (bccomp($lefttot, $netset) != 0) {
                $this->bo->addBRule('Net Received + Advance Received + Other Adjustment (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($lefttot) . ') should match with Amount Received (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($netset) . ')');
            }
        } else {
            
            $lefttotfc = bcadd($this->bo->credit_amt_total_fc, "0");
            $lefttotfc = bcadd($this->bo->adv_amt_fc, $lefttot);
            $lefttotfc = bcadd($this->bo->annex_info->value()->other_adj_fc, $lefttot);
            $netsetfc = bcadd($this->bo->net_settled_fc, "0");
            
            if (bccomp($lefttotfc, $netsetfc) != 0) {
                $this->bo->addBRule('Net Received FC + Advance Received FC + Other Adjustment FC (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($lefttotfc) . ') should match with Amount Received FC (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($netsetfc) . ')');
            }

        }

        if ($this->bo->rcpt_type == 2) {
            if ($this->bo->fc_type_id == 0) {
                if ($this->bo->net_settled != $this->bo->credit_amt) {
                    $this->bo->addBRule('Amount Received (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt) . ') should match with Total Payable Settlements (' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->credit_amt) . ')');
                }
            } else {
                if ($this->bo->net_settled_fc != $this->bo->credit_amt_fc) {
                    $this->bo->addBRule('Amount Received FC (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($this->bo->debit_amt_fc) . ') should match with Total Payable Settlements FC(' . \app\cwf\vsla\utils\FormatHelper::FormatNumber($this->bo->credit_amt_fc) . ')');
                }
            }
        }
    }

}
