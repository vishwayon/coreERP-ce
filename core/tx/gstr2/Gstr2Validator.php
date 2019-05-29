<?php

namespace app\core\tx\gstr2;

/**
 * 
 * @author girishshenoy
 */
class Gstr2Validator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGSTR2EditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        $dataParams = new \stdClass();
        $dataParams->gst_state_id = $this->bo->gst_state_id;
        $dataParams->ret_period_from = $this->bo->ret_period_from;
        $dataParams->ret_period_to = $this->bo->ret_period_to;
        $dt_pending = \app\core\tx\gstr2\Gstr2Worker::getPendingDocData($dataParams);
        if (count($dt_pending->Rows()) > 0) {
            $this->bo->addBRule("Requested period contains pending documents. Post all pending documents before saving GSTR1");
        }
        
        if($this->bo->ret_status == 2) {
            $this->bo->addBRule("GSTR2 return already uploaded. Modifications not allowed after upload.");
        }
        
        if(count($this->bo->getBRules()) == 0 && $this->bo->ret_status == 0) {
            $this->bo->ret_status = 1; // Created
            $this->bo->ret_status_desc = "Created";
        }
    }

}
