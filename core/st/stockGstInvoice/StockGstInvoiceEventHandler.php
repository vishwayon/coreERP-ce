<?php

namespace app\core\st\stockGstInvoice;

/**
 * StockGstInvoiceEventHandler
 * @author Girish
 */
class StockGstInvoiceEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

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

        // Create SO Detail temp table to select Sales Order Items
        $this->createSODetailTempTable();

        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->doc_stage_status = 0;

            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->vat_type_id = $this->bo->vat_type_id = -1;
        } else {
            \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetAdvAllocDetailsOnEdit($this->bo, $this->bo->stock_id);
        }

        // Custom Fields
        $this->bo->voucher_id = $this->bo->stock_id;
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        // Reset the custom field also
        $this->bo->voucher_id = $generatedKeys['stock_id'];
    }

    private function createSODetailTempTable() {
        $this->bo->so_detail_temp = new \app\cwf\vsla\data\DataTable();
        $scale = 0;
        $isUnique = false;

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('boolean');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->so_detail_temp->addColumn('select', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->so_detail_temp->addColumn('customer_id', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('salesman_id', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('material_id', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('uom_id', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('stock_location_id', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('fc_type_id', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->so_detail_temp->addColumn('so_doc_date', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->so_detail_temp->addColumn('opportunity_id', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('opportunity_tran_id', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('customer', $phpType, $default, 250, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('salesman_name', $phpType, $default, 50, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('material_name', $phpType, $default, 250, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('material_desc', $phpType, $default, 250, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('uom_desc', $phpType, $default, 50, $scale, $isUnique);

        $scale = 4;
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->so_detail_temp->addColumn('order_qty', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('issued_qty', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('balance_qty', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('net_amt', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('rate', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('rate_fc', $phpType, $default, 0, $scale, $isUnique);
        $this->bo->so_detail_temp->addColumn('exch_rate', $phpType, $default, 0, $scale, $isUnique);
        foreach ($this->bo->so_detail_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $this->bo->setTranMetaData('so_detail_temp', $cols);
    }

    public function afterEntityDelete($cn, $tablename) {
        parent::afterEntityDelete($cn, $tablename);
        if ($tablename == 'st.stock_control') {
            
            // Delete entry from stock ledger if any
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Delete from st.stock_ledger where voucher_id = :pstock_id');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
}
