<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\stockConsumption;

/**
 * Description of StockConsumptionValidator
 *
 * @author Kaustubh
 */
class StockConsumptionValidator extends \app\core\st\base\StockBaseValidator {

    public function validateStockConsumptionEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        if (count($this->bo->stock_tran->Rows()) == 0) {
            $this->bo->addBRule('Atleast one row is required in Stock Items');
        }

        // Validate correct UoM
        parent::ValidateUoM($this->bo);

        \app\core\st\lotAlloc\LotAllocHelper::validateSlLotAlloc($this->bo, $this->bo->stock_tran);
        if (count($this->bo->getBRules()) > 0) {
            // Skip the next set of validations as allocations are incomplete
            return;
        }
    }

    public function validateBeforeUnpost() {
        parent::validateStockBeforeUnpost();
    }

    public function validateBeforePost() {
        parent::validateBeforePost();

//        Validate sl_lot
        \app\core\st\lotAlloc\LotAllocHelper::validateQcMatAlloc($this->bo, $this->bo->stock_tran);
    }

}
