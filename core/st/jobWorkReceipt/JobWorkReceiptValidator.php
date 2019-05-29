<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\jobWorkReceipt;

/**
 * Description of StockConsumptionValidator
 *
 * @author Kaustubh
 */
class JobWorkReceiptValidator extends \app\core\st\base\StockBaseValidator {
    
    public function validateJobWorkReceiptEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {  
        if(count($this->bo->stock_tran->Rows()) == 0){
            $this->bo->addBRule('Atleast one row is required in Stock Items');
        }        
        
        // Validate correct UoM
        parent::ValidateUoM($this->bo);
        
        // Flag materials that have qc true. This is for user display help, update freecount
        $mat_array = $this->bo->stock_tran->select("material_id");
        $qc_req = \app\core\st\lotAlloc\LotAllocHelper::getQcMat($mat_array);
        foreach ($this->bo->stock_tran->Rows() as &$tran_row) {
            if (array_key_exists($tran_row['material_id'], $qc_req)) {
                $tran_row["has_qc"] = TRUE;
            }
        }
        
        // Add entries in stock_tran_qc
        $rowcount = count($this->bo->stock_tran_qc->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->stock_tran_qc->removeRow(0);
        }
        foreach ($this->bo->stock_tran->Rows() as $st_row) {
            if ($st_row['has_qc']) {
                $newRow = $this->bo->stock_tran_qc->NewRow();
                $newRow['stock_tran_qc_id'] = '';
                $newRow['stock_tran_id'] = $st_row['stock_tran_id'];
                $newRow['stock_id'] = $st_row['stock_id'];
                $newRow['test_insp_id'] = $st_row['stock_tran_id'];
                $newRow['test_insp_date'] = $this->bo->doc_date;
                $newRow['material_id'] = $st_row['material_id'];
                $newRow['test_result_id'] = 1;
                $newRow['accept_qty'] = $st_row['received_qty'];
                $newRow['reject_qty'] =  0;
                $newRow['lot_no'] = '';
                $newRow['mfg_date'] = $this->bo->doc_date;
                $exp = new \DateTime($this->bo->doc_date);
                date_add($exp, new \DateInterval('P5D'));
                $newRow['exp_date'] = $exp->format("Y-m-d");
                $newRow['best_before'] = $exp->format("Y-m-d");
                $newRow['ref_info'] = '[{}]';
                $this->bo->stock_tran_qc->AddRow($newRow);
            }
        }
    }
    
    public function validateBeforeUnpost(){
        parent::validateStockBeforeUnpost();
    }
}
