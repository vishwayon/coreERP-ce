<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\systemSettings;
/**
 * Description of SystemSettingsValidator
 *
 * @author priyanka
 */
class SystemSettingsValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    //put your code here
    public function validateSystemSettingsEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();  
     }
    
    protected function validateBusinessRules() {     
        
    }
    
    protected function docIsCurrent() {
        return true;
    }
}
