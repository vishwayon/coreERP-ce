<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\stockTransfer;

/**
 * Description of StockTransferEventHandler
 *
 * @author Kaustubh
 */
class StockTransferEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $this->bo->st_str_qc_reqd = false;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select (annex_info->>'has_str_qc')::Boolean has_str_qc
            From sys.branch
            Where branch_id = {branch_id}");
        $dt_str_qc = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt_str_qc->Rows()) == 1) {
            $this->bo->st_str_qc_reqd = $dt_str_qc->Rows()[0]['has_str_qc'];
        }

        $this->bo->qc_requested = false;
        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->status = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        }
        $this->bo->for_receipt = false;
        // Flag materials that have qc true. This is for user display help, update freecount
        $mat_array = $this->bo->stock_tran->select("material_id");
        $qc_req = \app\core\st\lotAlloc\LotAllocHelper::getQcMat($mat_array);
        $fcount = 0;
        foreach ($this->bo->stock_tran->Rows() as &$dr_mat) {
            if (array_key_exists($dr_mat['material_id'], $qc_req)) {
                $dr_mat["has_qc"] = TRUE;
            }
        }

        if (isset($criteriaparam['for_receipt'])) {
            $this->bo->for_receipt = $criteriaparam['for_receipt'];
            $this->bo->receipt_posted = $criteriaparam['receipt_posted'];            
            
            // Check if receipt is posted
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select status From st.stock_transfer_park_post Where stock_id = :pstock_id;');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            $dtsr = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtsr->Rows()) > 0) {
                if ($dtsr->Rows()[0]['status'] == 5) {
                    $this->bo->receipt_posted = true;
                }
            }
            
            // Fetch requested qty from extn table if exists
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select receipt_qty, stock_tran_id, short_qty, receipt_sl_id From st.stock_tran_extn Where stock_id = :pstock_id;');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            $dtex = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtex->Rows()) > 0) {
                $this->bo->qc_requested = true;
            }
            
            // If current date is greater than connected finyear end set year end as received on
            if(strtotime($this->bo->st_received_on) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->st_received_on = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }

            // Set received on 
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select doc_date From st.stock_transfer_park_post Where stock_id = :pstock_id And doc_date is not null;');
            $cmm->addParam('pstock_id', $this->bo->stock_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $this->bo->st_received_on = $dt->Rows()[0]['doc_date'];
            }

            $cmmQc = new \app\cwf\vsla\data\SqlCommand();
            $cmmQc->setCommandText("select b.material_id, a.test_insp_attr_id, a.test_desc, 
                                                    case when a.test_type_id = 1 then a.range_result::varchar else case when passed then pass_val else fail_val end end as result
                                    From prod.test_insp_tran a
                                    Inner join prod.test_insp_control b on a.test_insp_id = b.test_insp_id
                                    Where b.annex_info->'doc_ref_info'->>'doc_ref_id' = :pstock_id
                                                    And a.conducted = true
                                                    And a.test_insp_attr_id != 100;");
            $cmmQc->addParam('pstock_id', $this->bo->stock_id);
            $dtQc = \app\cwf\vsla\data\DataConnect::getData($cmmQc);

            foreach ($this->bo->stock_tran->Rows() as &$row) {
                if ($this->bo->st_str_qc_reqd && $row['has_qc']) {
                    $row['receipt_qty'] = 0;
                } else {
                    $row['receipt_qty'] = $row['issued_qty'];
                }
                $row['receipt_sl_id'] = -1;
                foreach ($dtex->Rows() as $exrow) {
                    if ($row['stock_tran_id'] == $exrow['stock_tran_id']) {
                        $row['receipt_sl_id'] = $exrow['receipt_sl_id'];
                        $row['receipt_qty'] = $exrow['receipt_qty'];
                        $row['short_qty'] = $exrow['short_qty'];
                    }
                }
            }
        }

        // Set default stock location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select stock_location_id, stock_location_name From st.stock_location Where branch_id={branch_id} And is_default_for_branch=true;');
        $dtsl = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtsl->Rows()) == 1) {
            $this->bo->default_sl = $dtsl->Rows()[0];
        }

        if (\app\core\st\stockGstPurchase\StockGstPurchaseHelper::hasQCModule() && $this->bo->status == 5) {
            \app\core\st\stockGstPurchase\StockGstPurchaseHelper::loadQcTestResult($this->bo);
        }

        $this->bo->vbt_amt_tot = 0.00;

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
