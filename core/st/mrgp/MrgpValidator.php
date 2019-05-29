<?php

namespace app\core\st\mrgp;

/**
 * MrgpValidator
 * @author Priyanka
 */
class MrgpValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateMrgpEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        foreach ($this->bo->mrgp_tran->Rows() as $tran_row) {
            if ($tran_row['in_qty'] > $tran_row['out_qty']) {
                $this->bo->addBRule('Item Details:  - Row[' . $tran_row['sl_no'] . '] Inward qty cannot be greater than outward qty.');
            }
        }
    }

    public function validateBeforeUnpost() {
        
    }

    public function validateBeforePost() {
    }

    public function validateBeforeStage(\app\cwf\vsla\workflow\WfOption $wfOption) {
        parent::validateBeforeStage($wfOption);
    }
}
