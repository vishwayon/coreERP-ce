<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppToCust;

/**
 * Description of Supplier
 *
 * @author Vaishali
 */
class SuppToCustValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSuppToCustEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        // Check If supplier is already associated with customer
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select customer_name from ar.customer where customer_id = :psupplier_id');
        $cmm->addParam('psupplier_id', $this->bo->supplier_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows()) == 1){            
            $this->bo->addBRule('Supplier already associated with Customer.');
        }
    }
    
    protected function docIsCurrent() {
        // Overridden as last updated validation is not required.
        // And anchoring table ar.customer is for cwf purposes only
        return true;
    }
}
