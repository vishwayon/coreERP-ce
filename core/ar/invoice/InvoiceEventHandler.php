<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\invoice;
use YaLinqo\Enumerable;

/**
 * Description of InvoiceEventHandler
 *
 * @author priyanka
 */
class InvoiceEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
        
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        
        $this->bo->trigger_id='core';
        $this->bo->tax_schedule_id=-1; 
        $this->bo->applicable_to_supplier = false;
        $this->bo->applicable_to_customer = true;
                
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CreateTaxDetailTemp($this->bo); 
        
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
        
        \app\core\ar\customer\CustomerHelper::CreateCustAddrTemp($this->bo);
        
        if($this->bo->invoice_id=="" or $this->bo->invoice_id=="-1")
        {
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->InitialiseInvoice($criteriaparam); 
            $this->bo->is_dispatched =  FALSE;
            $this->bo->dispatched_date = null;
            $this->bo->dispatch_method = 0;
            
        }
        else{
            \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->invoice_id);
            
            \app\core\tx\taxSchedule\worker\TaxScheduleHelper::GetTaxDetailsOnEdit($this->bo);
        }
    }
    
    protected function InitialiseInvoice($criteriaparam){
        $this->bo->en_invoice_action = 0;
        $this->bo->invoice_id="";
        $this->bo->status=0;
        $this->bo->income_type_id=$criteriaparam['formData']['SelectIncomeType']['income_type_id'];
        $this->bo->doc_date=$criteriaparam['formData']['SelectIncomeType']['doc_date'];

        $this->bo->fc_type_id=$criteriaparam['formData']['SelectIncomeType']['fc_type_id'];

        // Fetch exch rate for selected fc type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select exch_rate from ac.fc_type where fc_type_id=:pfc_type_id');
        $cmm->addParam('pfc_type_id', $this->bo->fc_type_id);
        $dtfc = \app\cwf\vsla\data\DataConnect::getData($cmm);        
        if(count($dtfc->Rows())>0){                
            $this->bo->exch_rate=$dtfc->Rows()[0]['exch_rate'];
        }

                 
        //Check if supplier has service Tax applier true
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select tax_schedule_id, seq_type from ar.income_type where income_type_id=:pincome_type_id');
        $cmm->addParam('pincome_type_id', $this->bo->income_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())>0){
            $this->bo->tax_schedule_id = $result->Rows()[0]['tax_schedule_id'];
            if($result->Rows()[0]['seq_type'] !=''){
                $this->bo->doc_type = $result->Rows()[0]['seq_type'];
            }
        }
        
        // Fetch default tax details on new
        if($this->bo->tax_schedule_id!=-1){
            $this->bo->tax_schedule_name = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/TaxSchedule.xml', 'tax_schedule', 'tax_schedule_id', $this->bo->tax_schedule_id);
            \app\core\tx\taxSchedule\worker\TaxScheduleHelper::GetTaxDefaultsOnNew($this->bo);
        }
    }
}
