<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payhead;
use YaLinqo\Enumerable;

/**
 * Description of PayheadValidator
 *
 * @author Valli
 */

class PayheadValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validatePayheadEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate duplicate Payhead  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select payhead from hr.payhead where payhead ilike :ppayhead and payhead_id!=:ppayhead_id');
        $cmm->addParam('ppayhead', $this->bo->payhead);
        $cmm->addParam('ppayhead_id', $this->bo->payhead_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Payhead already exists. Duplicate payhead not allowed.');
        }
        
        // Check if Overtime Payhead exists
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select payhead_id from hr.payhead where payhead_type=:ppayhead_type and payhead_id!=:ppayhead_id and payhead_type = 'O'");
        $cmm->addParam('ppayhead_type', $this->bo->payhead_type);
        $cmm->addParam('ppayhead_id', $this->bo->payhead_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Overtime Payhead already created. Multiple Overtime Payhead not allowed.');
        }
        
        // Check if Loan Recovery Payhead exists
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select payhead_id from hr.payhead where payhead_type=:ppayhead_type and payhead_id!=:ppayhead_id and payhead_type = 'L'");
        $cmm->addParam('ppayhead_type', $this->bo->payhead_type);
        $cmm->addParam('ppayhead_id', $this->bo->payhead_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Loan Recovery Payhead already created. Multiple Loan Recovery Payhead not allowed.');
        }
       
        if($this->bo->payhead_type == "-1"){
            $this->bo->addBRule('Payhead Type is required.');
        }
    }
}