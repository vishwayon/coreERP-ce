<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\saj;

use YaLinqo\Enumerable;

/**
 * Description of MCJValidator
 *
 * @author Priyanka
 */
class SajValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSajEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        foreach ($this->bo->saj_tran->Rows() as $row) {
            if($row['debit_sub_head_id'] == $row['credit_sub_head_id']){                
                    $this->bo->addBRule('Details - Row[' . $row['sl_no'] . '] : Debit sub head and credit sub head should be different.');
            }
        }
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
    public function validateBeforeUnPost() {
        // Compulsory method named. No implementation currently required
    }

}
