<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\ptn;

use YaLinqo\Enumerable;

/**
 * Description of PtnValidator
 *
 * @author Valli
 */
class PtnValidator extends \app\core\st\base\StockBaseValidator {

    public function validatePtnEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Select atleast one Stock Item to transfer.');
        }

        $row_cnt = 0;
        parent::ValidateUoM($this->bo);
        foreach ($this->bo->stock_tran->Rows() as &$refrow) {
            $row_cnt = $row_cnt + 1;
            $refrow['sl_no'] = $row_cnt;
        }


        // Validate correct UoM
        $row_no = 0;
        foreach ($this->bo->stock_tran->Rows() as $row) {
            $row_no = $row_no + 1;

            if ($row['stock_location_id'] == $row['target_stock_location_id']) {
                $this->bo->addBRule('Stock Items - Row[' . $row_no . '] : Source and Target Stock Location should be different.');
            }
            $r = Enumerable::from($this->bo->stock_tran->Rows())->where('$a==>$a["stock_location_id"] == ' . $row['stock_location_id'] .
                            ' && $a["target_stock_location_id"] == ' . $row['target_stock_location_id'] .
                            ' && $a["material_id"] == ' . $row['material_id'] .
                            ' && $a["uom_id"] == ' . $row['uom_id'])->toList();
            if (count($r) > 1) {
                $this->bo->addBRule('Stock Items - Row[' . $row_no . '] : There are multiple entries for same Stock Item with the same Unit, Source Stock Loc. and Target Stock Loc.');
            }
        }
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
    }

    public function validateBeforePost() {
//        parent::validateBeforePost();
    }

    public function validateBeforeStage(\app\cwf\vsla\workflow\WfOption $wfOption) {
        if ($this->bo->doc_stage_id == 'pick-list' && $wfOption->next_stage_id == 'confirm-issue') {
            parent::validateNegativeStock();

            //        Validate sl_lot
            \app\core\st\lotAlloc\LotAllocHelper::validateQcMatAlloc($this->bo, $this->bo->stock_tran);
            \app\core\st\lotAlloc\LotAllocHelper::validateSlLotAlloc($this->bo, $this->bo->stock_tran);
            if (count($this->bo->getBRules()) > 0) {
                // Skip the next set of validations as allocations are incomplete
                return;
            }
        }
    }

}
