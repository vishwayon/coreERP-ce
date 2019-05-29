<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\stockAdjustmentNote;

/**
 * Description of StockAdjustmentNoteValidator
 *
 * @author Shrishail
 */
class StockAdjustmentNoteValidator extends \app\core\st\base\StockBaseValidator {

    public function validateStockAdjustmentNoteEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {

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

        \app\core\st\lotAlloc\LotAllocHelper::validateSlLotAlloc($this->bo, $this->bo->stock_tran);
        if (count($this->bo->getBRules()) > 0) {
            // Skip the next set of validations as allocations are incomplete
            return;
        }

        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one row is required in Stock Items');
        } else {
            // Validate correct UoM
            parent::ValidateUoM($this->bo);

            $RowNo = 0;
            foreach ($this->bo->stock_tran->Rows() as &$refrow) {
                $RowNo++;

                if ($refrow['ir'] != "I" && $refrow['ir'] != "R") {
                    $this->bo->addBRule('Stock Items - Row[' . $RowNo . '] : I or R is required.');
                } else if ($refrow['ir'] == "I") {
                    if ($refrow['issued_qty'] == 0) {
                        $this->bo->addBRule('Stock Items - Row[' . $RowNo . '] : Issued qty cannot be 0.');
                    }
                    $refrow['rate'] = 0;
                    $refrow['received_qty'] = 0;
                } else if ($refrow['ir'] == "R") {
                    if ($refrow['received_qty'] == 0) {
                        $this->bo->addBRule('Stock Items - Row[' . $RowNo . '] : Received qty cannot be 0.');
                    }
                    $refrow['issued_qty'] = 0;
                }

                //Calculate item amount for each row in material Info
                if ($refrow['issued_qty'] > 0) {
                    $refrow['item_amt'] = round($refrow['issued_qty'] * $refrow['rate'], \app\cwf\vsla\Math::$amtScale);
                } else {
                    $refrow['item_amt'] = round($refrow['received_qty'] * $refrow['rate'], \app\cwf\vsla\Math::$amtScale);
                }
                $refrow['sl_no'] = $RowNo;
            }
        }

        // Flag materials that have qc true. This is for user display help, update freecount
        $mat_array = $this->bo->stock_tran->select("material_id");
        $qc_req = \app\core\st\lotAlloc\LotAllocHelper::getQcMat($mat_array);
        foreach ($this->bo->stock_tran->Rows() as &$tran_row) {
            if (array_key_exists($tran_row['material_id'], $qc_req)) {
                $tran_row["has_qc"] = TRUE;
            }
        }

        // Add entries in stock_tran_qc
        $rowcount = count($this->bo->stock_tran_qc->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->stock_tran_qc->removeRow(0);
        }
        foreach ($this->bo->stock_tran->Rows() as $st_row) {
            if ($st_row['received_qty'] > 0 && $st_row['has_qc']) {
                $newRow = $this->bo->stock_tran_qc->NewRow();
                $newRow['stock_tran_qc_id'] = '';
                $newRow['stock_tran_id'] = $st_row['stock_tran_id'];
                $newRow['stock_id'] = $st_row['stock_id'];
                $newRow['test_insp_id'] = $st_row['stock_tran_id'];
                $newRow['test_insp_date'] = $this->bo->doc_date;
                $newRow['material_id'] = $st_row['material_id'];
                $newRow['test_result_id'] = 1;
                $newRow['accept_qty'] = $st_row['received_qty'];
                $newRow['reject_qty'] = 0;
                $newRow['lot_no'] = '';
                $newRow['mfg_date'] = $this->bo->doc_date;
                $exp = new \DateTime($this->bo->doc_date);
                date_add($exp, new \DateInterval('P5D'));
                $newRow['exp_date'] = $exp->format("Y-m-d");
                $newRow['best_before'] = $exp->format("Y-m-d");
                $newRow['ref_info'] = '{}';
                $this->bo->stock_tran_qc->AddRow($newRow);
            }
        }
        if ($this->bo->annex_info->Value()->adj_opbl && strtotime($this->bo->doc_date) != strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))) {
            $this->bo->addBRule('Adjust Op Bal allowed only when doc date is Financial Year start date');
        }
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
    }

    public function validateBeforePost() {

        $stock_neg_allow = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue("stock_neg_allow");
        if ($stock_neg_allow != 'true') {
            $var = [];
            foreach ($this->bo->stock_tran->Rows() as $tran_row) {
                if ($tran_row['issued_qty'] > 0) {
                    $dr = [];
                    $dr['material_id'] = $tran_row['material_id'];
                    $dr['uom_id'] = $tran_row['uom_id'];
                    $dr['stock_location_id'] = $tran_row['stock_location_id'];
                    $dr['received_qty'] = $tran_row['received_qty'];
                    $dr['issued_qty'] = $tran_row['issued_qty'];
                    array_push($var, $dr);
                }
            }

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select * from st.fn_sl_post_val_nst(:pcompany_id, :pbranch_id, :pfinyear, :pvoucher_id, :pdoc_date, :pjson_tran)");
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $cmm->addParam('pvoucher_id', $this->bo->stock_id);
            $cmm->addParam('pdoc_date', $this->bo->doc_date);
            $cmm->addParam('pjson_tran', json_encode($var));
            $bal_result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($bal_result->Rows()) > 0) {
                $balrow = $bal_result->Rows()[0];
                $this->bo->addBRule('Insufficient stock balance for ' . $balrow['material_name'] . ' (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($balrow['balance']) . ') in SL: ' . $balrow['stock_location_name'] . ' on ' . $balrow['doc_date']);
            }
        }

//        Validate sl_lot
        \app\core\st\lotAlloc\LotAllocHelper::validateQcMatAlloc($this->bo, $this->bo->stock_tran);
    }

}
