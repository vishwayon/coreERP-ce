<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\finalSettlement;

/**
 * Description of FinalSettlementValidator
 *
 * @author valli
 */

class FinalSettlementValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateFinalSettlementEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() 
    {
        if(count($this->bo->fin_set_payroll_tran->Rows())==0){
          $this->bo->addBRule('Atleast one record in final settlement payroll is required.');}
       
//        if ($this->bo->net_settlement_amt==0 ){
//          $this->bo->addBRule('Net settlement amount cannot be zero');}  
// 
        //Pay From Date  validation
        if ($this->bo->fin_set_from_date > $this->bo->fin_set_to_date){
          $this->bo->addBRule('Final Settlement From Date cannot be greater than Final Settlement To Date');}   
          
        
        // 
        
        if ($this->bo->en_resign_type==1){
            
            if ($this->bo->notice_pay>0){
                $this->bo->addBRule('Resigned employee are not applicable to notice pay. It is applicable only for employees who are terminated');
            }
            
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.employee_id, b.full_employee_name, c.installment_date from hr.loan_control a
                              inner join hr.employee b on a.employee_id=b.employee_id 
                              inner join hr.loan_tran c on a.loan_id=c.loan_id 
                              where a.status <> 5 and a.employee_id=:pemployee_id
                              and c.installment_date > :pfin_set_from_date and (b.resign_date is null or b.resign_date >= :pfin_set_from_date)');
        
        $cmm->addParam('pemployee_id', $this->bo->employee_id);       
        $cmm->addParam('pfin_set_from_date', $this->bo->fin_set_from_date);     
            
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        if(count($result->Rows())>0){
            $this->bo->addBRule('Loan not authorised for this employee');             
        }
        
        // Set Amt In Words   
        
        $currency='';
        $subCurrency='';
        $currency_system='';
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        if(count($dtbr->Rows())>0){
            $currency=$dtbr->Rows()[0]['currency'];
            $subCurrency=$dtbr->Rows()[0]['sub_currency'];
            $currency_system=$dtbr->Rows()[0]['currency_system'];
        } 
         
        $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->net_settlement_amt);
        $this->bo->net_amt_in_words =  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system); 
        
    }
     
    public function validateBeforeDelete() 
    {
        // conduct default form validations
        parent::validateBeforeDelete();   
        

    }
    
    public function validateBeforeUnpost() {       

    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}