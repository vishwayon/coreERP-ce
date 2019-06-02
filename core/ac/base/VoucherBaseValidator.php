<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\base;

use \app\cwf\vsla\Math;
use YaLinqo\Enumerable;

/**
 * Description of AssetPurchaseValidator
 *
 * @author girish
 */
class VoucherBaseValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    protected function validateBusinessRules() {

        // Set sl no         
        for ($rowIndex = 0; $rowIndex < count($this->bo->vch_tran->Rows()); $rowIndex++) {
            $this->bo->vch_tran->Rows()[$rowIndex]['sl_no'] = $rowIndex + 1;
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
        If ($this->bo->credit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        If ($this->bo->debit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }

        // Set date as null if respective boolean filed is false
        if (!$this->bo->is_reversal) {
            $this->bo->reversal_date = NULL;
        }

        if (!$this->bo->is_pdc) {
            $this->bo->pdc_date = NULL;
        } else {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }

        if (!$this->bo->collected) {
            $this->bo->collection_date = null;
        }


        bcscale(Math::$amtScale); // set scale to amtScale
        $sumCredit = bcadd($this->bo->credit_amt, "0");
        $sumDebit = bcadd($this->bo->debit_amt, "0");

        if ($this->bo->doc_type == 'PAYV' || $this->bo->doc_type == 'PAYB' || $this->bo->doc_type == 'PAYC') {
            $sumDebit = bcadd($this->bo->annex_info->Value()->round_off_amt, "0");
        }
        $sumDebitFC = bcadd($this->bo->debit_amt_fc, "0");
        $sumCreditFC = bcadd($this->bo->credit_amt_fc, "0");

        foreach ($this->bo->vch_tran->Rows() as $row) {
            $sumDebit = bcadd($row['debit_amt'], $sumDebit);
            $sumDebitFC = bcadd($row['debit_amt_fc'], $sumDebitFC);
            $sumCredit = bcadd($row['credit_amt'], $sumCredit);
            $sumCreditFC = bcadd($row['credit_amt_fc'], $sumCreditFC);
            if (array_key_exists('gtt_gst_rate_id', $row) && ($this->bo->doc_type == 'PAYV' || $this->bo->doc_type == 'PAYB' || $this->bo->doc_type == 'PAYC')) {
                if ($this->bo->annex_info->Value()->line_item_gst) {
                    if (!$row['gtt_is_rc']) {
                        $sumDebit = bcadd(bcadd(bcadd(bcadd($row['gtt_sgst_amt'], $row['gtt_cgst_amt']), $row['gtt_igst_amt']), $row['gtt_cess_amt']), $sumDebit);
                    }
                } else {
                    if (!$this->bo->annex_info->Value()->gst_rc_info->apply_rc) {
                        $sumDebit = bcadd(bcadd(bcadd(bcadd($row['gtt_sgst_amt'], $row['gtt_cgst_amt']), $row['gtt_igst_amt']), $row['gtt_cess_amt']), $sumDebit);
                    }
                }
            }
        }

        if (bccomp($sumCredit, $sumDebit) != 0) {
            $this->bo->addBRule('Sum of Debits and Credits do not match.');
        }



        if ($this->bo->is_inter_branch) {
            $RowNo = 0;
            foreach ($this->bo->vch_tran->Rows() as $row1) {
                $RowNo++;
                if ($row['branch_id'] == -1) {
                    $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Branch is required');
                }
            }
        } else {
            foreach ($this->bo->vch_tran->Rows() as &$ref_ac_row) {
                $ref_ac_row['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            }
        }

        // Validate Duplicate accounts
        $this->validateDuplicateTranAccount();

        if (strtotime($this->bo->doc_date) < strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))
                or strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
            $this->bo->addBRule('Date should be within the financial year.');
        }

        // Remove row from vch_tran if debit and credit total matches and vch tran contains rows with 0 amt.
        for ($i = 0; $i < count($this->bo->vch_tran->Rows()); $i++) {
            if ($this->bo->vch_tran->Rows()[$i]['debit_amt'] == 0 && $this->bo->vch_tran->Rows()[$i]['credit_amt'] == 0) {
                $this->bo->vch_tran->removeRow($i);
            }
        }

        // Validate Subhead total if exists
        $RowNo = 0;
        foreach ($this->bo->vch_tran->Rows() as $row) {
            $RowNo++;

            // If selected account does not require Sub Head Allocation or ref allocation, remove allocated sub head or ref info  if any.
            $result = \app\core\ac\subHeadAlloc\SubHeadAllocHelper::IsDetailReqd($row['account_id']);
            if ($result['is_detail_reqd'] == 'false') {
                // remove sub head and ref ledger allocation 
                $sub_head_cnt = count($row['sub_head_ledger_tran']->Rows());
                for ($i = 0; $i <= $sub_head_cnt; $i++) {
                    $row['sub_head_ledger_tran']->removeRow(0);
                }

                $ref_cnt = count($row['ref_ledger_alloc_tran']->Rows());
                for ($i = 0; $i <= $ref_cnt; $i++) {
                    $row['ref_ledger_alloc_tran']->removeRow(0);
                }

                $row['ref_no'] = '';
                $row['ref_desc'] = '';
            } else if ($result['is_detail_reqd'] == 'true') {
                if ($result['sub_head_dim_id'] == -1) {// Ref Ledger reqd
                    // Remove rows from sub head ledger.
                    $sub_head_cnt = count($row['sub_head_ledger_tran']->Rows());
                    for ($i = 0; $i <= $sub_head_cnt; $i++) {
                        $row['sub_head_ledger_tran']->removeRow(0);
                    }

                    if ($row['ref_no'] == '') {
                        // Set connected branch id and document date in alloc
                        foreach ($row['ref_ledger_alloc_tran']->Rows() as &$ref_led_row) {
                            $ref_led_row['branch_id'] = $row['branch_id'];
                            $ref_led_row['affect_doc_date'] = $this->bo->doc_date;
                        }
                        // Validate ref ledger total 
                        $ref_debit_total = round(Enumerable::from($row['ref_ledger_alloc_tran']->Rows())->sum('$a==>$a["net_debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                        $ref_credit_total = round(Enumerable::from($row['ref_ledger_alloc_tran']->Rows())->sum('$a==>$a["net_credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                        if ($row['dc'] == 'D') {
                            if ($row['debit_amt'] != $ref_debit_total) {
                                $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Ref Ledger total should match with the Debits.');
                            }
                        } else if ($row['dc'] == 'C') {
                            if ($row['credit_amt'] != $ref_credit_total) {
                                $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Ref Ledger total should match with the Credits.');
                            }
                        }
                    }
                }
                if ($result['is_ref_ledger'] == 'false') {
                    // Set connected document date in alloc
                    foreach ($row['sub_head_ledger_tran']->Rows() as &$shl_row) {
                        $shl_row['doc_date'] = $this->bo->doc_date;
                        $shl_row['branch_id'] = $row['branch_id'];
                        if ($row['dc'] == 'D') {
                            $shl_row['credit_amt'] =  0;
                        }
                        else{
                            $shl_row['debit_amt'] =  0;
                        }
                    }
                    // Remove rows from ref ledger alloc.
                    $ref_cnt = count($row['ref_ledger_alloc_tran']->Rows());
                    for ($i = 0; $i <= $ref_cnt; $i++) {
                        $row['ref_ledger_alloc_tran']->removeRow(0);
                    }

                    $row['ref_no'] = '';
                    $row['ref_desc'] = '';

                    $debit_total = round(Enumerable::from($row['sub_head_ledger_tran']->Rows())->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
                    $credit_total = round(Enumerable::from($row['sub_head_ledger_tran']->Rows())->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                    if ($row['dc'] == 'D') {
                        if ($row['debit_amt'] != $debit_total) {
                            $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Sub head total should match with the Debits.');
                        }
                    } else if ($row['dc'] == 'C') {
                        if ($row['credit_amt'] != $credit_total) {
                            $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Sub head total should match with the Credits.');
                        }
                    }
                }
            }

            // Validate account with selected Sub Head Account
            for ($i = count($row['sub_head_ledger_tran']->Rows()) - 1; $i >= 0; $i--) {
                if ($row['sub_head_ledger_tran']->Rows()[$i]['sub_head_id'] == -1) {
                    $row['sub_head_ledger_tran']->removeRow($i);
                }
            }

            foreach ($row['sub_head_ledger_tran']->Rows() as $sub_head_row) {
                if ($row['account_id'] != $sub_head_row['account_id']) {
                    $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Sub Head details does not belong to the selected Account. Kindly revise the Sub Head Allocations.');
                    break;
                }
            }

            // Validate account with selected ref ledger Account
            foreach ($row['ref_ledger_alloc_tran']->Rows() as $ref_row) {
                if ($row['account_id'] != $ref_row['account_id']) {
                    $this->bo->addBRule('Account Info - Row[' . $RowNo . '] : Ref Ledger details does not belong to the selected Account. Kindly revise the Ref Ledger Allocations.');
                    break;
                }
            }
        }
    }

    protected function validateDuplicateTranAccount() {
        $accArray = array();
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
    }

    public function validateBeforeUnpost() {

        // If reconciled, don't allow to unpost  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select collected from ac.vch_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['collected']) {
                $this->bo->addBRule('This voucher has reconciled items. Cannot be unposted.');
            }
        }
        // If reversed, don't allow to unpost   
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select is_reversal from ac.vch_control where voucher_id=:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['is_reversal']) {
                $this->bo->addBRule('This voucher is reversed. Cannot be unposted.');
            }
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

    public function validateCashAccLimitOnPost($credit_amt, $account_id, $doc_date) {
        // Check if monthly or anual cash account limit is set for branch
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.account_id, b.branch_id, b.limit_val, b.limit_type_id 
                                from ac.cash_acc_limit a
                                inner join ac.cash_acc_limit_tran b on a.cash_acc_limit_id = b.cash_acc_limit_id
                                where a.account_id = :paccount_id and b.branch_id = :pbranch_id");
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('paccount_id', $account_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $cmm_gl = new \app\cwf\vsla\data\SqlCommand();
            $cmm_gl->setCommandText("select COALESCE(sum(credit_amt), 0) as credit_amt from ac.general_ledger a
                                    where a.account_id = :paccount_id and a.branch_id = :pbranch_id
                                            And a.doc_date between :pfrom_date and :pto_date");
            $cmm_gl->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm_gl->addParam('paccount_id', $account_id);
            $cmm_gl->addParam('pfrom_date', $doc_date);
            $cmm_gl->addParam('pto_date', $doc_date);
            if ($result->Rows()[0]['limit_type_id'] == 0) {// If Limit type is annual
                $cmm_gl->setParamValue('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
                $cmm_gl->setParamValue('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
            } else {// If Limit type is Monthly
                $cmm_gl->setParamValue('pfrom_date', date("Y-m-01", strtotime($doc_date)));
                $cmm_gl->setParamValue('pto_date', date("Y-m-t", strtotime($doc_date)));
            }
            $gl_result = \app\cwf\vsla\data\DataConnect::getData($cmm_gl);

            $gl_credit = 0;
            if (count($gl_result->Rows()) > 0) {
                $gl_credit = $gl_result->Rows()[0]['credit_amt'];
            }

            if (($gl_credit + $credit_amt) > $result->Rows()[0]['limit_val']) {
                return $result->Rows()[0]['limit_val'] - $gl_credit;
            }
            return 0;
        }
    }

}
