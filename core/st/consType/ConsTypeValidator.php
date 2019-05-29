<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\consType;

/**
 * Description of ConsTypeValidator
 *
 * @author Valli
 */
class ConsTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateConsTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        // Validate duplicate Consumption Type Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select cons_type_desc from st.cons_type where cons_type_desc ilike :pcons_type_desc '
                             . 'and cons_type_id!=:pcons_type_id and company_id=:pcompany_id');
        $cmm->addParam('pcons_type_desc', $this->bo->cons_type_desc);
        $cmm->addParam('pcons_type_id', $this->bo->cons_type_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Consumption Type already exists. Duplicate(s) not allowed.');
        }
    }
}
