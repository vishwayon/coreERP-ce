<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\gstRate;

/**
 * Description of GSTRateValidator
 *
 * @author Priyanka
 */
class GSTRateValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateGSTRateEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate Tax Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select gst_rate_desc from tx.gst_rate where gst_rate_desc ilike :pgst_rate_desc and gst_rate_id!=:pgst_rate_id');
        $cmm->addParam('pgst_rate_desc', $this->bo->gst_rate_desc);
        $cmm->addParam('pgst_rate_id', $this->bo->gst_rate_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('GST Rate already exists. Duplicate GST Rate not allowed.');
        }
    }
}
