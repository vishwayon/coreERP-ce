<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\mfg;

/**
 * Description of MfgValidator
 *
 * @author Girish Shenoy
 */
class MfgValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateMfgEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        // Validate duplicate Material Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select mfg from st.mfg where mfg ilike :pmfg '
                             . 'and mfg_id!=:pmfg_id and company_id=:pcompany_id');
        $cmm->addParam('pmfg', $this->bo->mfg);
        $cmm->addParam('pmfg_id', $this->bo->mfg_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Manufacturer already exists. Duplicate(s) not allowed.');
        }
    }
}
