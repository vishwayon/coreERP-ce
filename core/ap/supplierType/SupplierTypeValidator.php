<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierType;

/**
 * Description of SuppTypeValidator
 *
 * @author Priyanka
 */

class SupplierTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSupplierTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        // Validate duplicate Segment
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select supp_type from ap.supp_type where supp_type ilike :psupp_type '
                          . 'and supp_type_id!=:psupp_type_id and company_id=:pcompany_id');
        $cmm->addParam('psupp_type', $this->bo->supp_type);
        $cmm->addParam('psupp_type_id', $this->bo->supp_type_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Supplier Type already exists. Duplicate Supplier Type not allowed.');
        } 
    }
     
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
    }
}