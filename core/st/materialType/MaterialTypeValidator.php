<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\materialType;

/**
 * Description of MaterialTypeValidator
 *
 * @author Shrishail
 */
class MaterialTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateMaterialTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        // Validate duplicate Material Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select material_type from st.material_type where material_type ilike :pmaterial_type '
                             . 'and material_type_id!=:pmaterial_type_id and company_id=:pcompany_id');
        $cmm->addParam('pmaterial_type', $this->bo->material_type);
        $cmm->addParam('pmaterial_type_id', $this->bo->material_type_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Stock type already exists. Duplicate Stock type not allowed.');
        }
    }
}
