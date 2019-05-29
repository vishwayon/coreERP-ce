<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\businessUnit;

/**
 * Description of BusinessUnitValidator
 *
 * @author Priyanka
 */
class BusinessUnitValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateBusinessUnitEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {
        // Validate duplicate Income Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select business_unit from sys.business_unit where business_unit ilike :pbusiness_unit '
                . 'and business_unit_id!=:pbusiness_unit_id and company_id=:pcompany_id');
        $cmm->addParam('pbusiness_unit', $this->bo->business_unit);
        $cmm->addParam('pbusiness_unit_id', $this->bo->business_unit_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Business Unit already exists. Duplicate Business Unit not allowed.');
        }
    }
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
    }
}
