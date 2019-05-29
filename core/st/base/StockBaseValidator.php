<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\base;

/**
 * Description of AssetPurchaseValidator
 *
 * @author girish
 */
class StockBaseValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    protected function validateBusinessRules() {
        
    }

    protected function ValidateUoM($bo) {
        // check UoM for selected stock Item.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select material_id from st.uom where uom_id=:puom_id');
        $cmm->addParam('puom_id', -1);
        $rowno = 0;
        foreach ($bo->stock_tran->Rows() as $tran_row) {
            $rowno = $rowno + 1;
            $cmm->setParamValue('puom_id', $tran_row['uom_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $material_id = $dt->Rows()[0]['material_id'];
                if ($material_id != $tran_row['material_id']) {
                    $bo->addBRule('Stock Items - Row[' . $rowno . '] : Select valid UoM for Stock Item.');
                }
            }
        }
    }

    protected function validateStockBeforeUnpost() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select * from st.fn_sl_unpost_val_nst(:pvoucher_id)");
        $cmm->addParam('pvoucher_id', $this->bo->stock_id);
        $bal_result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($bal_result->Rows()) > 0) {
//            foreach($bal_result->Rows() as $balrow){
            $this->bo->addBRule('Stock balance for ' . $bal_result->Rows()[0]['material_name'] . ' is negative (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($bal_result->Rows()[0]['balance']) . ') in SL: ' . $bal_result->Rows()[0]['stock_location_name'] . ' on ' . $bal_result->Rows()[0]['doc_date']);
//            }
        }
    }

    protected function validateNegativeStock() {
        $stock_neg_allow = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue("stock_neg_allow");
        if ($stock_neg_allow != 'true') {
            $var = $this->bo->stock_tran->select(['material_id', 'uom_id', 'stock_location_id', 'received_qty', 'issued_qty']);
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
    }

    public function validateBeforePost() {
        $this->validateNegativeStock();
    }

}
