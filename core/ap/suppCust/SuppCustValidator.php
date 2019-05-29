<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppCust;

/**
 * Description of Supplier
 *
 * @author Valli
 */
class SuppCustValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSuppCustEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from ap.supp_cust where customer_id=:pcustomer_id and supplier_id !=:psupplier_id');
        $cmm->addParam('psupplier_id', $this->bo->supplier_id);
        $cmm->addParam('pcustomer_id', $this->bo->customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
             $this->bo->addBRule('Selected Customer already associated with other Supplier Account.');
        }     
        
    }
    
    protected function docIsCurrent() {
        // Overridden as last updated validation is not required.
        // And anchoring table ar.customer is for cwf purposes only
        return true;
    }
}
