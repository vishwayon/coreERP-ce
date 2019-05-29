<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\hsn;

/**
 * Description of HsnValidator
 *
 * @author Girish Shenoy
 */
class HsnValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateHsnEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select hsn_code from st.hsn where hsn_code ilike :phsn_code '
                             . 'and hsn_id!=:phsn_id and company_id=:pcompany_id');
        $cmm->addParam('phsn_code', $this->bo->hsn_code);
        $cmm->addParam('phsn_id', $this->bo->hsn_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dthsc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dthsc->Rows())>0) {
            $this->bo->addBRule('HS Code already exists. Duplicate(s) not allowed.');
        }
        
        
        // Validate duplicate HSN code/description
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select hsn from st.hsn where hsn ilike :phsn '
                             . 'and hsn_id!=:phsn_id and company_id=:pcompany_id');
        $cmm->addParam('phsn', $this->bo->hsn_code);
        $cmm->addParam('phsn_id', $this->bo->hsn_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('HS Description already exists. Duplicate(s) not allowed.');
        }
    }
}
