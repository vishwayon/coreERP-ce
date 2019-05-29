<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\matConversionNote;

/**
 * Description of LocationTransferNoteEventHandler
 *
 * @author Priyanka
 */
class MatConversionNoteEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        // Set default stock location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select stock_location_id, stock_location_name From st.stock_location Where branch_id={branch_id} And is_default_for_branch=true;');
        $dtsl = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtsl->Rows()) == 1) {
            $this->bo->default_sl = $dtsl->Rows()[0];
        }

        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->annex_info->Value()->output_mat_type_id = -1;
            $this->bo->annex_info->Value()->output_mat_id = -1;
            $this->bo->annex_info->Value()->output_uom_id = -1;
            $this->bo->annex_info->Value()->output_sl_id = $this->bo->default_sl['stock_location_id'];
        } else {
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

}
