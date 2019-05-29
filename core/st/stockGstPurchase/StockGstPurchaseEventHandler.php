<?php

namespace app\core\st\stockGstPurchase;

/**
 * StockGstPurchaseEventHandler
 * @author Girish
 */
class StockGstPurchaseEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        // Set default stock location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select stock_location_id, stock_location_name From st.stock_location Where branch_id={branch_id} And is_default_for_branch=true;');
        $dtsl = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtsl->Rows()) == 1) {
            $this->bo->default_sl = $dtsl->Rows()[0];
        }

        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);

        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
        } else {
            // Fetch Adv alloc details
            \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->stock_id);
        }

        // Custom Fields for Display
        $this->bo->items_total = 0.00;
        $this->bo->annex_info->Value()->bill_level_tax = true;
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->account_id, $this->bo->doc_date);
        }

        if (StockGstPurchaseHelper::hasQCModule() && $this->bo->doc_stage_id == 'confirm-receipt') {
            StockGstPurchaseHelper::loadQcTestResult($this->bo);
        }
        if(StockGstPurchaseHelper::hasQCModule()) {
            $this->bo->vshow_ts_info = true;
        } else {
            $this->bo->vshow_ts_info = false;
        }
        // For SKM - This is always FALSE
        $this->bo->vshow_ts_info = false;
        
        // Fetch Cash Supplier Registered ID
        if (\app\cwf\vsla\utils\SettingsHelper::HasKey('cash_supp_regd_id')) {
            $this->bo->v_cash_supp_regd_id = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('cash_supp_regd_id');
        } else {
            $this->bo->v_cash_supp_regd_id = -999;
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Fetch Unsettled advance total for unposted bill
        if ($this->bo->status != 5) {
            $this->bo->unstl_adv_amt = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($this->bo->account_id, $this->bo->doc_date);
        }
    }

    public function afterEntityDelete($cn, $tablename) {
        parent::afterEntityDelete($cn, $tablename);
        if ($tablename == 'st.stock_control') {
            // Delete entry from stock loc if any
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Delete from st.sl_lot where sl_id = (select stock_ledger_id from st.stock_ledger where voucher_id = :pstock_id)');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            
            
            // Delete entry from stock ledger if any
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Delete from st.stock_ledger where voucher_id = :pstock_id');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            
            // Delete entry from qc_pending_doc if any            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='prod' And table_name = 'qc_pending_doc'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dt->Rows())>0) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Delete from prod.qc_pending_doc where voucher_id = :pstock_id');
                $cmm->addParam('pstock_id', $this->bo->stock_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
        }
    }
}
