<?php

namespace app\core\ac\gstBankPymt;

use YaLinqo\Enumerable;
/**
 * GstBankPymtValidator
 * @author Priyanka
 */
class GstBankPymtValidator extends \app\core\ac\gstPymt\GstPymtValidator {

    public function validateGstBankPymtEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        parent::validateBusinessRules();
    }
}
