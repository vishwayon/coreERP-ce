<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\stockTransfer;

use YaLinqo\Enumerable;

/**
 * Description of StockTransferValidator
 *
 * @author Kaustubh
 */
class StockTransferValidator extends \app\core\st\base\StockBaseValidator {

    public function validateStockTransferEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {

        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one Stock Item is required.');
        } else {
            // Validate correct UoM
            parent::ValidateUoM($this->bo);

            foreach ($this->bo->stock_tran->Rows() as &$strow) {
                $strow['target_stock_location_id'] = $this->bo->annex_info->Value()->target_sl_id;
            }
        }

        // can cause problems with back dated entries
        \app\core\st\lotAlloc\LotAllocHelper::validateQcMatAlloc($this->bo, $this->bo->stock_tran);
        if (count($this->bo->getBRules()) > 0) {
            // Skip the next set of validations as allocations are incomplete
            return;
        }

        \app\core\st\lotAlloc\LotAllocHelper::validateSlLotAlloc($this->bo, $this->bo->stock_tran);
        
        // Flag materials that have qc true. This is for user display help, update freecount
        $mat_array = $this->bo->stock_tran->select("material_id");
        $qc_req = \app\core\st\lotAlloc\LotAllocHelper::getQcMat($mat_array);
        $fcount = 0;
        foreach ($this->bo->stock_tran->Rows() as &$dr_mat) {
            if (array_key_exists($dr_mat['material_id'], $qc_req)) {
                $dr_mat["has_qc"] = TRUE;
            }
            else{
                $dr_mat["has_qc"] = FALSE;
            }
        }

        if (count($this->bo->getBRules()) > 0) {
            // Skip the next set of validations as allocations are incomplete
            return;
        }

        // set amt in words
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

    private function getWacRate($material_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from st.fn_material_balance_wac(:pcompany_id, :pbranch_id, :pmaterial_id, :pfinyear, :pto_date)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $cmm->addParam('pmaterial_id', $material_id);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $cmm->addParam('pto_date', $this->bo->doc_date);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0]['rate'];
        }
        return 0;
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select stock_id From st.stock_transfer_park_post Where stock_id=:pstock_id And status=5");
        $cmm->addParam("pstock_id", $this->bo->stock_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->bo->addBRule("Stock Tranfer received and posted in target branch. Unpost not allowed");
        }
    }

    public function validateBeforePost() {
        parent::validateBeforePost();
    }

}
