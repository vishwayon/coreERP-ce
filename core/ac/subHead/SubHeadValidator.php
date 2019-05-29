<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHead;

/**
 * Description of SubHeadValidator
 *
 * @author Shrishail
 */
class SubHeadValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSubHeadEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        // Validate duplicate SubHead
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select sub_head from ac.sub_head where sub_head ilike :psub_head '
                             . 'and sub_head_id!=:psub_head_id and company_id=:pcompany_id');
        $cmm->addParam('psub_head', $this->bo->sub_head);
        $cmm->addParam('psub_head_id', $this->bo->sub_head_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Sub head already exists. Duplicate sub head not allowed.');
        }
        
        if(!$this->bo->is_closed){
            $this->bo->closed_date = NULL;
        }
    }
}
