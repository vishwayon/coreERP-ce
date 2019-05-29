<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\purchaseReturnGst;

use YaLinqo\Enumerable;

/**
 * Description of PurchaseReturnGstEventHandler
 *
 * @author Priyanka
 */
class PurchaseReturnGstEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);

        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            if (array_key_exists('for_pv', $criteriaparam)) {
                // This is auto DCN from Stock Purchase (build formData)
                $cmmpv = new \app\cwf\vsla\data\SqlCommand();
                $cmmpv->setCommandText("Select * From st.stock_control Where stock_id = :pstock_id");
                $cmmpv->addParam("pstock_id", $criteriaparam["for_pv"]);
                $dtpv = \app\cwf\vsla\data\DataConnect::getData($cmmpv);
                if (count($dtpv->Rows()) == 1) {
                    $formData = [];
                    $formData['SelectSupplier'] = ['account_id' => $dtpv->Rows()[0]['account_id'], 'dcn_type' => 0];
                    $formData['SelectStockPurchase'] = ['reference_id' => $criteriaparam["for_pv"]];
                    $criteriaparam['formData'] = $formData;
                }
            }

            if (array_key_exists('formData', $criteriaparam)) {
                $this->bo->account_id = $criteriaparam['formData']['SelectSupplier']['account_id'];
                $this->bo->annex_info->Value()->dcn_type = $criteriaparam['formData']['SelectSupplier']['dcn_type'];
                $this->bo->stock_id = "";
                $this->bo->status = 0;
//                $suppInfo = \app\core\ap\supplier\SupplierHelper::getSuppAddr($this->bo->account_id);
//                $this->bo->annex_info->Value()->gst_input_info->supplier_state_id = $suppInfo->Rows()[0]['gst_state_id'];
//                $this->bo->annex_info->Value()->gst_input_info->supplier_gstin = $suppInfo->Rows()[0]['gstin'];
//                $this->bo->annex_info->Value()->gst_input_info->supplier_address = $suppInfo->Rows()[0]['addr'];

                if ($criteriaparam['formData']['SelectStockPurchase']['reference_id'] != '') {
                    $this->bo->reference_id = $criteriaparam['formData']['SelectStockPurchase']['reference_id'];
                    //                $this->bo->sp_date = $criteriaparam['formData']['SelectStockPurchase']['sp_date'];
                    //Fetch control info
                    $cmmvt = new \app\cwf\vsla\data\SqlCommand();
                    $cmmvt->setCommandText("Select stock_id, doc_date, vat_type_id, fc_type_id, exch_rate, 
                                                COALESCE((annex_info->'gst_input_info'->>'supplier_state_id')::bigint, -1) as supplier_state_id, 
                                                COALESCE((annex_info->'gst_input_info'->>'supplier_gstin')::varchar, '') as supplier_gstin, 
                                                COALESCE((annex_info->'gst_input_info'->>'supplier_addr')::varchar, '') as supplier_addr 
                                            From st.stock_control Where stock_id=:pstock_id");
                    $cmmvt->addParam("pstock_id", $this->bo->reference_id);
                    $dtvt = \app\cwf\vsla\data\DataConnect::getData($cmmvt);
                    if (count($dtvt->Rows()) == 1) {
                        $this->bo->sp_date = $dtvt->Rows()[0]['doc_date'];
                        $this->bo->vat_type_id = $dtvt->Rows()[0]['vat_type_id'];
                        if ($this->bo->vat_type_id > 200 && $this->bo->vat_type_id < 300) {
                            // This is VAT Purchase
                            $this->bo->annex_info->Value()->is_gst_pur = false;
                        } else if ($this->bo->vat_type_id > 400 && $this->bo->vat_type_id < 500) {
                            // This is GST Purchase
                            $this->bo->annex_info->Value()->is_gst_pur = true;
                        }
                        // This is required for printing in SalesReturn
                        $this->bo->annex_info->Value()->gst_input_info->supplier_state_id = $dtvt->Rows()[0]['supplier_state_id'];
                        $this->bo->annex_info->Value()->gst_input_info->supplier_gstin = $dtvt->Rows()[0]['supplier_gstin'];
                        $this->bo->annex_info->Value()->gst_input_info->supplier_address = $dtvt->Rows()[0]['supplier_addr'];

                        $this->bo->annex_info->Value()->origin_inv_id = $this->bo->reference_id;
                        $this->bo->annex_info->Value()->origin_inv_date = $dtvt->Rows()[0]['doc_date'];
                        $this->bo->fc_type_id = $dtvt->Rows()[0]['fc_type_id'];
                        $this->bo->exch_rate = $dtvt->Rows()[0]['exch_rate'];
                    }

                    // Orignated from PV. try to get rejected qty
                    if (array_key_exists('for_pv', $criteriaparam)) {
                        $cmmrej = new \app\cwf\vsla\data\SqlCommand();
                        $cmmrej->setCommandText("Select a.material_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                                        a.stock_id, a.stock_tran_id, sum(b.reject_qty) as reject_qty
                                                    From st.stock_tran a 
                                                    Inner Join st.stock_tran_qc b On a.stock_tran_id = b.stock_tran_id
                                                    Where a.stock_id = :pstock_id
                                                    group by a.material_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                                                a.stock_id, a.stock_tran_id
                                                    Having sum(b.reject_qty) > 0");
                        $cmmrej->addParam("pstock_id", $criteriaparam["for_pv"]);
                        $dtrej = \app\cwf\vsla\data\DataConnect::getData($cmmrej);

                        if (count($dtrej->Rows()) > 0) {
                            $i = 0;
                            foreach ($dtrej->Rows() as $drrej) {
                                $drn = $this->bo->stock_tran->NewRow();
                                $drn['sl_no'] = ++$i;
                                $drn['stock_tran_id'] = $drn['sl_no'];
                                $drn['material_id'] = $drrej['material_id'];
                                $drn['stock_location_id'] = $drrej['stock_location_id'];
                                $drn['uom_id'] = $drrej['uom_id'];
                                $drn['issued_qty'] = $drrej['reject_qty'];
                                $drn['other_amt'] = $drrej['rate'];
                                $drn['rate'] = $drrej['rate'];
                                $drn['bt_amt'] = round(floatval($drn['issued_qty']) * floatval($drn['rate']), 2);
                                $drn['reference_id'] = $drrej['stock_id'];
                                $drn['reference_tran_id'] = $drrej['stock_tran_id'];
                                $this->bo->stock_tran->addRow($drn);
                            }
                        }
                    }
                    
                    // Orignated from PV. try to get rejected qty
                    if (array_key_exists('SelectSpgForPrv', $criteriaparam['formData'])) {
                        $cmmrej = new \app\cwf\vsla\data\SqlCommand();
                        $cmmrej->setCommandText("Select a.material_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                                        a.stock_id, a.stock_tran_id, sum(b.reject_qty) as reject_qty
                                                    From st.stock_tran a 
                                                    Inner Join st.stock_tran_qc b On a.stock_tran_id = b.stock_tran_id
                                                    Where a.stock_id = :pstock_id
                                                            And a.stock_tran_id in (". $criteriaparam['formData']['SelectSpgForPrv']['selected_spgs'] .")
                                                    group by a.material_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                                                a.stock_id, a.stock_tran_id
                                                    Having sum(b.reject_qty) > 0");
                        $cmmrej->addParam("pstock_id", $this->bo->reference_id);
                        $dtrej = \app\cwf\vsla\data\DataConnect::getData($cmmrej);

                        if (count($dtrej->Rows()) > 0) {
                            $i = 0;
                            foreach ($dtrej->Rows() as $drrej) {
                                $drn = $this->bo->stock_tran->NewRow();
                                $drn['sl_no'] = ++$i;
                                $drn['stock_tran_id'] = $drn['sl_no'];
                                $drn['material_id'] = $drrej['material_id'];
                                $drn['stock_location_id'] = $drrej['stock_location_id'];
                                $drn['uom_id'] = $drrej['uom_id'];
                                $drn['issued_qty'] = $drrej['reject_qty'];
                                $drn['other_amt'] = $drrej['rate'];
                                $drn['rate'] = $drrej['rate'];
                                $drn['bt_amt'] = round(floatval($drn['issued_qty']) * floatval($drn['rate']), 2);
                                $drn['reference_id'] = $drrej['stock_id'];
                                $drn['reference_tran_id'] = $drrej['stock_tran_id'];
                                $this->bo->stock_tran->addRow($drn);
                            }
                        }
                    }
                    
                }
            } else {
                $this->bo->fc_type_id = 0;
                $this->bo->exch_rate = 1;
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Update st.stock_control
                Set annex_info = jsonb_set(annex_info, \'{dcn_ref_id}\', \'"' . $this->bo->stock_id . '"\') 
                Where stock_id = :pstock_id');
        $cmm->addParam("pstock_id", $this->bo->annex_info->Value()->origin_inv_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public function afterDeleteCommit() {
        parent::afterDeleteCommit();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Update st.stock_control
                Set annex_info = jsonb_set(annex_info, \'{dcn_ref_id}\', \'""\') 
                Where stock_id = :pstock_id');
        $cmm->addParam("pstock_id", $this->bo->annex_info->Value()->origin_inv_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

}
