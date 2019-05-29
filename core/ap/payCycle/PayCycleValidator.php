<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\payCycle;

/**
 * Description of PayCycleValidator
 *
 * @author Valli
 */

class PayCycleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validatePayCycleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        
       // Validate duplicate Advance Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select pay_cycle from ap.pay_cycle where pay_cycle ilike :ppay_cycle '
                . 'and pay_cycle_id!=:ppay_cycle_id');
        $cmm->addParam('ppay_cycle_id', $this->bo->pay_cycle_id);
        $cmm->addParam('ppay_cycle', $this->bo->pay_cycle);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Pay Cycle already exists. Duplicate not allowed.');
        }       
    }
              
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();

    }
}