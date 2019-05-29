<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\bill;

use YaLinqo\Enumerable;

/**
 * Description of BillEventHandler
 *
 * @author Kaustubh
 */
class BillEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->tax_schedule_id = -1;
        // Create Tran table for tax detail
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CreateTaxDetailTemp($this->bo);
        $this->bo->applicable_to_supplier = true;
        $this->bo->applicable_to_customer = false;


        \app\core\ap\advanceAlloc\AdvanceAllocHelper::CreateAllocTemp($this->bo);

        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);

        if ($this->bo->bill_id == "" or $this->bo->bill_id == "-1") {
            $this->bo->bill_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->en_bill_action = 0;
            $this->bo->supplier_id = $criteriaparam['formData']['SelectSupplier']['supplier_id'];
            $this->bo->doc_date = $criteriaparam['formData']['SelectSupplier']['doc_date'];
            $this->bo->annex_info->Value()->tds_net_adv = true;

//            //Check if supplier has service Tax applier true
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select is_tds_applied, tax_schedule_id from ap.supplier_tax_info where supplier_id=:psupplier_id');
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($result->Rows()) > 0) {
                if ($result->Rows()[0]['tax_schedule_id'] != -1) {
                    $this->bo->tax_schedule_id = $result->Rows()[0]['tax_schedule_id'];
                }
                $this->bo->annex_info->Value()->is_tds_applied = $result->Rows()[0]['is_tds_applied'];
            }

            // Fetch default tax details on new
            if ($this->bo->tax_schedule_id != -1) {
                $this->bo->tax_schedule_name = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/TaxSchedule.xml', 'tax_schedule', 'tax_schedule_id', $this->bo->tax_schedule_id);
                \app\core\tx\taxSchedule\worker\TaxScheduleHelper::GetTaxDefaultsOnNew($this->bo);
            }
        } else {

            // Fetch Adv alloc details
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->bill_id);


            \app\core\tx\taxSchedule\worker\TaxScheduleHelper::GetTaxDetailsOnEdit($this->bo);
        }

        $this->bo->supplier = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $this->bo->supplier_id);

        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_id, $this->bo->doc_date);
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->supplier_id, $this->bo->doc_date);
        }
    }
}
