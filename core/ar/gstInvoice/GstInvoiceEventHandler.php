<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\gstInvoice;
use YaLinqo\Enumerable;

/**
 * Description of GstInvoiceEventHandler
 *
 * @author priyanka
 */
class GstInvoiceEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->trigger_id='core';
        
        // Set round_off decimal from settings        
        $this->bo->invoice_rf_to = 0;
        if (\app\cwf\vsla\utils\SettingsHelper::HasKey('invoice_rf_to')) {
            $this->bo->invoice_rf_to = floatval(\app\cwf\vsla\utils\SettingsHelper::GetKeyValue('invoice_rf_to'));
        }
        
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
                
        if($this->bo->invoice_id=="" or $this->bo->invoice_id=="-1")
        {
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->InitialiseInvoice($criteriaparam); 
            $this->bo->is_dispatched =  FALSE;
            $this->bo->dispatched_date = null;
            $this->bo->dispatch_method = 0;
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            
        }
        else{
            \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->invoice_id);
        }
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->customer_id, $this->bo->doc_date);
        }
    }
    
    protected function InitialiseInvoice($criteriaparam){
        $this->bo->en_invoice_action = 0;
        $this->bo->invoice_id="";
        $this->bo->status=0;
        $this->bo->income_type_id=$criteriaparam['formData']['SelectIncomeType']['income_type_id'];
                 
        //Check if supplier has service Tax applier true
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select tax_schedule_id, seq_type from ar.income_type where income_type_id=:pincome_type_id');
        $cmm->addParam('pincome_type_id', $this->bo->income_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if(count($result->Rows())>0){
            if($result->Rows()[0]['seq_type'] !=''){
                $this->bo->doc_type = $result->Rows()[0]['seq_type'];
            }
        }
    }
}
