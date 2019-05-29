<?php
namespace app\cwf\sys\entityextn;

class EntityExtnValidator 
    extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateEntityExtnEditForm() {
        // conduct default form validations
        $this->bo->extn_info = EntityExtnHelper::toExtnFields($this->postData->custom_fields);
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);        
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    function docIsCurrent() {
        return TRUE;
    }
    
    protected function validateBusinessRules() {
        $brules = EntityExtnHelper::validateFields($this->postData->custom_fields, $this->bo->bo_id);
        if(count($brules) > 0) {
            foreach ($brules as $brule) {
                $this->bo->addBRule($brule);
            }
        }
    }
        
    public function validateBeforeDelete() {
    }
    
}