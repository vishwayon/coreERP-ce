<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\srr;

/**
 * Description of SrrValidator
 *
 * @author Priyanka
 */
class SrrValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSrrEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        
        // Validate duplicate Stock Location Name or Stock Location Code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select srr_desc from st.srr where srr_id != :psrr_id '
                             . ' And srr_desc ilike :psrr_desc');
        $cmm->addParam('psrr_desc', $this->bo->srr_desc);
        $cmm->addParam('psrr_id', $this->bo->srr_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Description already exists. Duplicate Description not allowed.');
        }
    } 
}
