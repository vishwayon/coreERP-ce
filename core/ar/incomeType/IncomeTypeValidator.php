<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\incomeType;

/**
 * Description of IncomeTypeValidator
 *
 * @author Ravindra
 */

class IncomeTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateIncomeTypeEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        if(count($this->bo->income_type_tran->Rows())==0)
        {
            $this->bo->addBRule('Atleast one Income Account Head is required in Income Type Details.');
        }

        // Validate duplicate A/C Head      
        $RowNo = 0;
        $Count=0;
        foreach ($this->bo->income_type_tran->Rows() as $row) {   
             $RowNo++;
             $Count=0;
            if($row['account_id']!=-1)   {
           foreach ($this->bo->income_type_tran->Rows() as $row1) {
               if($row['account_id']==$row1['account_id']){
                   $Count+=1;
             }
           }
         }
       }
        if($Count>1)
        {
            $this->bo->addBRule('Duplicate Income A/C Head not allowed. At row no -'. $RowNo);      
        }
    }
      
    protected function validateDuplicateIncomeType()
    {    
        // Validate duplicate Income Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select income_type_name from ar.income_type where income_type_name ilike :pincome_type_name '
                          . 'and income_type_id!=:pincome_type_id and company_id=:pcompany_id');
        $cmm->addParam('pincome_type_name', $this->bo->income_type_name);
        $cmm->addParam('pincome_type_id', $this->bo->income_type_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Income Type already exists. Duplicate Income Type not allowed.');
        } 
    }
     
    public function validateBeforeDelete() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from ar.invoice_control where income_type_id=:pincome_type_id');        
        $cmm->addParam('pincome_type_id', $this->bo->income_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Cannot delete Income Type as it is used in Invoice.');
        } 
        
        if($this->bo->is_system_created){
            $this->bo->addBRule('Cannot delete Income Type as it is System Generated.');
        }
        parent::validateBeforeDelete();
    }
}