<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\taxSchedule;
use YaLinqo\Enumerable;
/**
 * Description of TaxScheduleValidator
 *
 * @author vaishali
 */
class TaxScheduleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateTaxScheduleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        
        if($this->bo->applicable_to_customer ==false && $this->bo->applicable_to_supplier ==false)
        {
            $this->bo->addBRule('Please select Applicable To Customer or Applicable To Supplier');
        }

         if(count($this->bo->tax_detail_tran->Rows()) == 0){
            $this->bo->addBRule('Atleast one Tax Detail is required.');
          }          
        
          else if(count($this->bo->tax_detail_tran->Rows()) > 0){
         
                foreach ($this->bo->tax_detail_tran->Rows() as $row){
                    if($row['description'] =='' ){
                        $this->bo->addBRule('Tax Detail cannot be left blank.');     
                    }  
                    if($row['tax_on_perc'] > 100 ){
                        $this->bo->addBRule('Tax On Percentage cannot be greater than 100.');     
                    } 
                }
              
                $list = Enumerable::from($this->bo->tax_detail_tran->Rows())->groupBy('$a==>$a["description"]')->toList();
                foreach($list as $groupKey => $groupData) {
                   if(count($groupData) >1){
                      $this->bo->addBRule('Duplicate Tax Details not allowed.');
                   }
               }
          }
        
             // Validate duplicate Tax schedule
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select description from tx.tax_schedule where description ilike :pdescription and tax_schedule_id!=:ptax_schedule_id');
        $cmm->addParam('pdescription', $this->bo->description);
        $cmm->addParam('ptax_schedule_id', $this->bo->tax_schedule_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Tax Schedule already exists. Duplicate Tax Schedule not allowed.');
        }
    }
}
