<?php

namespace app\core\st\stockGstInvoice;

/**
 * StockGstInvoiceValidator
 * @author Girish
 */
class StockGstInvoiceValidator extends \app\core\st\base\StockBaseValidator {

    public function validateStockGstInvoiceEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        // Validate for GST
        if(strtotime($this->bo->doc_date) < strtotime('2017-07-01')) {
            $this->bo->addBRule('This Invoice type is not allowed before 01 Jul, 2017');
        }
        
        // If ship to address entered city and pin is required
        if($this->bo->annex_info->Value()->gst_output_info->is_ship_consign){
            if($this->bo->annex_info->Value()->gst_output_info->ship_consign_city == ''){
                $this->bo->addBRule('Ship To city is required.');
            }
            if($this->bo->annex_info->Value()->gst_output_info->ship_consign_pin == ''){
                $this->bo->addBRule('Ship To Pin is required.');
            }
        }
        
        // Validate pick-list stage
        if ($this->bo->doc_stage_id == 'pick-list') {
            $this->validatePickListStage();
        }

        \app\core\ar\advanceAlloc\AdvanceAllocHelper::ValidateAdvance($this->bo, $this->bo->account_id, $this->bo->stock_id);

        // Ensure that there is no excess advance settled
        if ($this->bo->net_amt < 0) {
            $this->bo->addBRule('Advance settlement is in excess of Invoice Amount');
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

    private function validatePickListStage() {
        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one Stock Item is required.');
        } else {
            // Validate correct UoM
            parent::ValidateUoM($this->bo);
        }

        // Validate mat war info
        $cmmMatInfo = new \app\cwf\vsla\data\SqlCommand();
        $cmmMatInfo->setCommandText("Select 
                    material_id,
                    coalesce((annex_info->'war_info'->>'has_war')::boolean, false) has_war,
                    coalesce((annex_info->'supp_info'->>'has_batch')::boolean, false) has_batch
                From st.material a
                Where a.material_id = Any (:pmat_ids::BigInt[])");
        $mats = $this->bo->stock_tran->select('material_id');
        $matids = implode(', ', $mats);
        $cmmMatInfo->addParam('pmat_ids', '{' . $matids . '}');
        $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmmMatInfo);
        $matInfo = $dtMatInfo->asArray('material_id', ['has_war', 'has_batch']);
        // Validate War Info
        $row_cnt = 0;
        foreach ($this->bo->stock_tran->Rows() as $tran_row) {
            $row_cnt += 1;
            $mat_id = intval($tran_row['material_id']);
            if ($matInfo[$mat_id][0]['has_war']) {
                foreach ($tran_row['stock_tran_war']->Rows() as $war_row) {
                    if ($war_row['mfg_serial'] == '') {
                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Serial No. is required');
                    }
//  This code is commented as the user is not aware of the mfg. date in all cases
//                    if (strtotime($war_row['mfg_date']) > strtotime($this->bo->doc_date)) {
//                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Date is greater than Document Date');
//                    }
//                    if (strtotime($war_row['mfg_date']) == strtotime('1970-01-01')) {
//                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Date is Required');
//                    }
                }

                $war_row_cnt = count($tran_row['stock_tran_war']->Rows());
                if (intval($tran_row['issued_qty']) != $war_row_cnt) {
                    $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': Warranty Serial count does not match with issued qty.');
                }
            } else {
                // clear all invalid entries
                // This code is commented as the user can enter war information for items of his choice.
                // $tran_row['stock_tran_war']->removeAll();
            }
        }
    }

    private function validateInvoicedStage() {
        if (count($this->bo->stock_tran->Rows()) != 0) {
            $row_cnt = 0;
            // Validate Rate and bt_amt
            foreach ($this->bo->stock_tran->Rows() as $tran_row) {
                $row_cnt += 1;
                if (floatval($tran_row['rate']) <= 0) {
                    $this->bo->addBRule('Stock Items - Row[' . $row_cnt . '] : Rate is required');
                }
                if (floatval($tran_row['bt_amt']) <= 0) {
                    $this->bo->addBRule('Stock Items - Row[' . $row_cnt . '] : Amount cannot be zero/negative');
                }
            }
            
            // Update Hsn Qty
            foreach($this->bo->stock_tran->Rows() as &$stran) {
                $stran['gtt_hsn_qty'] = $stran['issued_qty'];
            }
        }
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
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
            $this->bo->addBRule('Cannot Unpost as this stock invoice is used in following documents. ' . $msgstr);
        }
    }

    public function validateBeforeStage(\app\cwf\vsla\workflow\WfOption $wfOption) {
        parent::validateBeforeStage($wfOption);

        if ($this->bo->doc_stage_id == 'pick-list' && $wfOption->next_stage_id == 'dispatched') {
            // This validates for stock balance availibility
            parent::validateNegativeStock();
        }
    }

    public function validateBeforePost() {
        // Since the nagative stocks were validated at dispatched stage, 
        // we only validate for values here
        $this->validateInvoicedStage();
    }

}
