<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\ptn;

/**
 * Description of ProductionTransferNoteEventHandler
 *
 * @author Valli
 */
class PtnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->target_branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        }
        $this->bo->for_receipt = false;
        if (isset($criteriaparam['for_receipt'])) {
            $this->bo->for_receipt = $criteriaparam['for_receipt'];
            $this->bo->receipt_posted = $criteriaparam['receipt_posted'];
            
            // If current date is greater than connected finyear end set year end as received on
            if(strtotime($this->bo->st_received_on) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->st_received_on = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
        }


        // Flag materials that have qc true. This is for user display help
        $mat_array = $this->bo->stock_tran->select("material_id");
        $qc_req = \app\core\st\lotAlloc\LotAllocHelper::getQcMat($mat_array);
        foreach ($this->bo->stock_tran->Rows() as &$dr_mat) {
            if (array_key_exists($dr_mat['material_id'], $qc_req)) {
                $dr_mat["has_qc"] = TRUE;
            }
        }
    }

}
