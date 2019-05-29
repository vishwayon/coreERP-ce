<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\matConversionNote;

use YaLinqo\Enumerable;

/**
 * Description of StockTransferValidator
 *
 * @author Kaustubh
 */
class MatConversionNoteValidator extends \app\core\st\base\StockBaseValidator {

    public function validateMatConversionNoteEditForm() {
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
        \app\core\st\lotAlloc\LotAllocHelper::validateQcMatAlloc($this->bo, $this->bo->stock_tran);
    }

}
