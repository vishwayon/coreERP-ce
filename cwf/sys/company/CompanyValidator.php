<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\sys\company;
/**
 * Description of CompanyValidator
 *
 * @author Ravindra
 */
class CompanyValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateCompanyEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();  
     }
    
    protected function validateBusinessRules() {        
        
         // Validate duplicate Company Name
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select company_name from sys.company where company_name ilike :pcompany_name AND company_id!=:pcompany_id');
        $cmm->addParam('pcompany_name', $this->bo->company_name);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm,\app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Company Name already exists. Duplicate Company Name not allowed.');}
     
       // Validate duplicate Company Code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select company_code from sys.company where company_code ilike :pcompany_code and company_id!=:pcompany_id');
        $cmm->addParam('pcompany_code', $this->bo->company_code);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm,\app\cwf\vsla\data\DataConnect::MAIN_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Company Code already exists. Duplicate Company Code not allowed.');           
        }
        if($this->bo->company_id==-1){
        
            if($this->bo->branch_code==''){
                $this->bo->addBRule('Branch Code is required');           
            }

            if($this->bo->branch_name==''){
                $this->bo->addBRule('Branch Name is required');           
            }

            if($this->bo->branch_description==''){
                $this->bo->addBRule('Branch Description is required');           
            }

            if($this->bo->currency==''){
                $this->bo->addBRule('Currency is required');           
            }

            if($this->bo->sub_currency==''){
                $this->bo->addBRule('Sub Currency is required');           
            }

            if($this->bo->currency_displayed==''){
                $this->bo->addBRule('Currency Displayed is required');           
            }

            if($this->bo->currency_system==-1){
                $this->bo->addBRule('Currency System is required');           
            }

            if($this->bo->finyear_code==''){
                $this->bo->addBRule('Fin Year is required');           
            }
            
            if(strtotime($this->bo->fin_year_begin)> strtotime($this->bo->fin_year_end)){
                $this->bo->addBRule('Year Begin should be less than Year End.');    
            }
        }                  
    }
}

