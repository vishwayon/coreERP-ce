<?php

namespace app\core\st\stockGstPurchase;

use YaLinqo\Enumerable;

/**
 * StockGstPurchaseValidator
 *
 * @author Girish
 */
class StockGstPurchaseValidator extends \app\core\st\base\StockBaseValidator {

    public function validateStockGstPurchaseEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        // Ensure max date cut-off for GST
        if (strtotime($this->bo->doc_date) < strtotime('2017-07-01')) {
            $this->bo->addBRule('GST Purchase not allowed before 01 Jul, 2017');
        }

        $rowno = 0;
        foreach ($this->bo->stock_tran->Rows() as &$refst_tran_row) {
            $rowno = $rowno + 1;
            $refst_tran_row['sl_no'] = $rowno;
        }

        if ($this->bo->bill_no != '' && $this->bo->bill_no != 'BNR') {
            $this->validateDuplicateBillNo();
            if ($this->bo->bill_amt == 0) {
                $this->bo->addBRule('Bill Amount is required');
            }
            if (strtotime($this->bo->doc_date) < strtotime($this->bo->bill_date)) {
                $this->bo->addBRule('Purchase voucher date cannot be less than Bill Date');
            }
        }

        // Validate correct UoM
        parent::ValidateUoM($this->bo);

        $rowno = 0;
        // Validate LcType
        foreach ($this->bo->stock_lc_tran->Rows() as $lc_row) {
            $rowno = $rowno + 1;
            if ($lc_row['lc_type_id'] == -1) {
                $this->bo->addBRule('Landed Cost - Row[' . $rowno . '] : Landed Cost Type required');
            }
        }

        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one Stock Item is required.');
        } else {
            //Validate each row
            foreach ($this->bo->stock_tran->Rows() as &$refrow) {
                if ($refrow['material_id'] == -1) {
                    $this->bo->addBRule('Stock Items - Row[' . $refrow['sl_no'] . ']: Select valid stock item');
                }
                if ($refrow['stock_location_id'] == -1) {
                    $this->bo->addBRule('Stock Items - Row[' . $refrow['sl_no'] . ']: Select valid stock location');
                }

                if ($refrow['received_qty'] == 0) {
                    $this->bo->addBRule('Stock Items - Row[' . $refrow['sl_no'] . ']: Received Qty is required');
                }

                if (floatval($refrow['disc_amt']) < -2) {
                    $this->bo->addBRule('Stock Items - Row[' . $refrow['sl_no'] . ']: Negative discount allowed only for round up/down. Should be greater than -2');
                }

                if ($refrow['item_amt'] < 0) {
                    $this->bo->addBRule('Stock Items - Row[' . $refrow['sl_no'] . ']: Item Value cannot be negative. Enter correct discount figure.');
                }
                // Fix Discount as it is entered by user
                if ($refrow['disc_amt'] > 0) {
                    $refrow['disc_Is_value'] = true;
                }
                //Fix apply_itc
                $refrow['apply_itc'] = $refrow['gtt_apply_itc'];
            }
        }
        \app\core\ap\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->account_id, $this->bo->stock_id);

        // For each lcType, update exp_ac_id, liab_ac_id
        if (count($this->bo->stock_lc_tran->Rows()) > 0) {
            $cmmLcType = new \app\cwf\vsla\data\SqlCommand();
            $cmmLcType->setCommandText("select lc_type_id, lc_desc, 
                                            (jdata->>'req_alloc')::Boolean req_alloc, 
                                            (jdata->>'post_gl')::Boolean post_gl, 
                                            exp_ac_id, liab_ac_id
                                        from st.lc_type
                                        Where company_id={company_id}
                                        order by lc_desc");
            $dtLc = \app\cwf\vsla\data\DataConnect::getData($cmmLcType);
            foreach ($this->bo->stock_lc_tran->Rows() as &$reflc_row) {
                $drLc = $dtLc->findRow("lc_type_id", $reflc_row["lc_type_id"]);
                $reflc_row["account_id"] = $drLc["exp_ac_id"];
                $reflc_row["account_affected_id"] = $drLc["liab_ac_id"];
                $reflc_row["req_alloc"] = $drLc["req_alloc"];
                $reflc_row["post_gl"] = $drLc["post_gl"];
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
        If ($this->bo->total_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->total_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }
    }

    public function validateBeforeUnpost() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from ac.rl_pl_alloc
                                where rl_pl_id in (select rl_pl_id from ac.rl_pl 
                                                                where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->stock_id);
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
            $this->bo->addBRule('Cannot Unpost as this stock purchase is used in following documents. ' . $msgstr);
        }
    }

    private function validateDuplicateBillNo() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from ap.fn_validate_bill_no(:paccount_id, :pbill_no, :pbill_date, :pvoucher_id)');
        $cmm->addParam('pbill_no', $this->bo->bill_no);
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $cmm->addParam('pvoucher_id', $this->bo->stock_id);
        $cmm->addParam('pbill_date', $this->bo->bill_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Bill No ' . $this->bo->bill_no . ' Dt. ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']) . ' already entered for the selected Supplier in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
        } else {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select bill_no, bill_date, voucher_id from ac.rl_pl 
                        where account_id=:paccount_id and bill_no ilike :pbill_no and bill_date = :pbill_date and voucher_id!=:pvoucher_id");
            $cmm->addParam('pvoucher_id', $this->bo->stock_id);
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $cmm->addParam('pbill_no', $this->bo->bill_no);
            $cmm->addParam('pbill_date', $this->bo->bill_date);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('Bill No ' . $this->bo->bill_no . ' Dt. ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']) . ' already used for the selected Ledger Account in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
            }
        }
    }

    public function validateBeforeStage(\app\cwf\vsla\workflow\WfOption $wfOption) {
        parent::validateBeforeStage($wfOption);

        if ($this->bo->doc_stage_id == 'book-purchase' && $wfOption->doc_action == \app\cwf\vsla\workflow\DocWorkflow::WF_REJECT) {
            parent::validateStockBeforeUnpost(); // This validates for negative stocks
            \app\core\st\lotAlloc\LotAllocHelper::validateOnUnpost($this->bo, $this->bo->stock_id); // Validate lot allocations 
        } else if ($this->bo->doc_stage_id == 'confirm-receipt' && $wfOption->next_stage_id == 'book-purchase') {
            // Validate for qc items
            $this->validateQCTestInsp();
            if ($this->bo->annex_info->Value()->is_closed) {
                $this->bo->addBRule('Cannot forward document that is closed for all items rejected.');
            }
        }
    }

    public function validateBeforePost() {
        parent::validateBeforePost();
        if (floatval($this->bo->total_amt) == 0) {
            $this->bo->addBRule('Nil value Stock Purchase not allowed');
        }
        if ($this->bo->bill_no != 'BNR') {
            $this->validateDuplicateBillNo();
            if ($this->bo->bill_amt == 0) {
                $this->bo->addBRule('Bill Amount is required');
            }
            if ($this->bo->bill_amt != $this->bo->total_amt) {
                $this->bo->addBRule('Bill Amount and Purchase Amount do not match. Please verify.');
            }
            if (strtotime($this->bo->doc_date) < strtotime($this->bo->bill_date)) {
                $this->bo->addBRule('Purchase voucher date cannot be less than Bill Date');
            }
        }

        // Validate Ref ledger alloc

        $RowNo = 0;
        foreach ($this->bo->stock_lc_tran->Rows() as &$reflc_row) {
            $RowNo++;
            if ($reflc_row['req_alloc']) {
                // Set connected branch id and document date in alloc
                foreach ($reflc_row['ref_ledger_alloc_tran']->Rows() as &$ref_led_row) {
                    $ref_led_row['branch_id'] = $this->bo->branch_id;
                    $ref_led_row['affect_doc_date'] = $this->bo->doc_date;
                }
                // Validate ref ledger total 
                $ref_credit_total = round(Enumerable::from($reflc_row['ref_ledger_alloc_tran']->Rows())->sum('$a==>$a["net_credit_amt"]'), \app\cwf\vsla\Math::$amtScale);

                if ($reflc_row['debit_amt'] != $ref_credit_total) {
                    $this->bo->addBRule('Landed Costs - Row[' . $RowNo . '] : Ref Ledger total should match with the amount.');
                }
            }
            else{
                $ref_cnt = count($reflc_row['ref_ledger_alloc_tran']->Rows());
                for ($i = 0; $i <= $ref_cnt; $i++) {
                    $reflc_row['ref_ledger_alloc_tran']->removeRow(0);
                }
            }
        }
    }

    private function validateQCTestInsp() {
        // Break the code if production module is not available
        if (StockGstPurchaseHelper::hasQCModule()) {
            StockGstPurchaseHelper::loadQcTestResult($this->bo);
            StockGstPurchaseHelper::validateQcTestCompleted($this->bo);
            // This is not required in SKM, hence commented
            //StockGstPurchaseHelper::validateTsInfo($this->bo);

            // Validate 100% rejections
            if ($this->bo->annex_info->Value()->is_closed) {
                $this->bo->annex_info->Value()->closed_on = date('Y-m-d');
                $this->bo->annex_info->Value()->closed_reason = 'QC_REJECT';
            }
        }
    }

}
