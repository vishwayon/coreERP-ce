<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\hsnRate;

/**
 * Description of HSNRateValidator
 *
 * @author Priyanka
 */
class HSNRateValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateHSNRateEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        if ($this->bo->is_exempt) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From tx.gst_rate Where gst_rate_id = :pgst_rate_id");
            $cmm->addParam('pgst_rate_id', $this->bo->gst_rate_id);
            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtGst->Rows()) == 1) {
                $dr = $dtGst->Rows()[0];
                if (($dr['sgst_pcnt'] + $dr['cgst_pcnt'] + $dr['igst_pcnt']) > 0) {
                    $this->bo->addBRule("GST Rate Schedule required to be Nil/0% when item is exempt");
                }
            } else {
                $this->bo->addBRule("GST Rate Schedule required when item is exempt");
            }
        }
    }

}
