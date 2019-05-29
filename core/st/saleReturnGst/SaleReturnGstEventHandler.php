<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\saleReturnGst;

/**
 * Description of SalesReturnEventHandler
 *
 * @author vaishali
 */
class SaleReturnGstEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->account_id = $criteriaparam['formData']['SelectCustomer']['account_id'];
            $this->bo->annex_info->Value()->dcn_type = $criteriaparam['formData']['SelectCustomer']['dcn_type'];
            $this->bo->stock_id = "";
            $this->bo->status = 0;
//            $custInfo = \app\core\ar\customer\CustomerHelper::getCustAddr($this->bo->account_id);
//            $this->bo->annex_info->Value()->gst_output_info->customer_state_id = $custInfo->Rows()[0]['gst_state_id'];
//            $this->bo->annex_info->Value()->gst_output_info->customer_gstin = $custInfo->Rows()[0]['gstin'];
//            $this->bo->customer_address = $custInfo->Rows()[0]['addr'];
            if ($criteriaparam['formData']['SelectStockInvoice']['reference_id'] != '') {
                $this->bo->reference_id = $criteriaparam['formData']['SelectStockInvoice']['reference_id'];
                //Fetch control info
                $cmmvt = new \app\cwf\vsla\data\SqlCommand();
                $cmmvt->setCommandText("Select stock_id, doc_date, vat_type_id, fc_type_id, exch_rate, 
                                            COALESCE((annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1) as customer_state_id, 
                                            COALESCE((annex_info->'gst_output_info'->>'customer_gstin')::varchar, '') as customer_gstin, 
                                            customer_address
                                        From st.stock_control Where stock_id=:pstock_id");
                $cmmvt->addParam("pstock_id", $this->bo->reference_id);
                $dtvt = \app\cwf\vsla\data\DataConnect::getData($cmmvt);
                if (count($dtvt->Rows()) == 1) {
                    $this->bo->si_date = $dtvt->Rows()[0]['doc_date'];
                    $this->bo->vat_type_id = $dtvt->Rows()[0]['vat_type_id'];
                    if($this->bo->vat_type_id > 100 && $this->bo->vat_type_id < 200) {
                        // This is VAT Invoice
                        $this->bo->annex_info->Value()->is_gst_inv = false;
                    } else if ($this->bo->vat_type_id > 300 && $this->bo->vat_type_id < 400) {
                        // This is GST Invoice
                        $this->bo->annex_info->Value()->is_gst_inv = true;
                    }
                    // This is required for printing in SalesReturn
                    $this->bo->annex_info->Value()->gst_output_info->customer_state_id = $dtvt->Rows()[0]['customer_state_id'];
                    $this->bo->annex_info->Value()->gst_output_info->customer_gstin = $dtvt->Rows()[0]['customer_gstin'];
                    $this->bo->customer_address = $dtvt->Rows()[0]['customer_address'];
                    $this->bo->annex_info->Value()->origin_inv_id = $this->bo->reference_id;
                    $this->bo->annex_info->Value()->origin_inv_date = $dtvt->Rows()[0]['doc_date'];
                    $this->bo->fc_type_id = $dtvt->Rows()[0]['fc_type_id'];
                    $this->bo->exch_rate = $dtvt->Rows()[0]['exch_rate'];
                }
                // Fetch tran info
//                $cmmTran = new \app\cwf\vsla\data\SqlCommand();
//                $cmmTran->setCommandText("Select * From st.stock_tran Where stock_id=:pstock_id Order by sl_no");
//                $cmmTran->addParam('pstock_id', $this->bo->annex_info->Value()->origin_inv_id);
//                $dtTran = \app\cwf\vsla\data\DataConnect::getData($cmmTran);
//                $sl_no = 1;
//                foreach ($criteriaparam['formData']['SelectStockInvoice'] as $matrow) {
//                    foreach($dtTran->Rows() as $drMat) {
//                        if($drMat['stock_tran_id'] == $matrow['stock_tran_id']) {
//                            $newRow = $this->bo->stock_tran->newRow();
//                            $newRow['sl_no'] = $sl_no;
//                            $newRow['reference_id'] = $drMat['stock_id'];
//                            $newRow['reference_tran_id'] = $drMat['stock_tran_id'];
//                            $newRow['material_type_id'] = $drMat['material_type_id'];
//                            $newRow['material_id'] = $drMat['material_id'];
//                            $newRow['stock_location_id'] = $drMat['stock_location_id'];
//                            $newRow['uom_id'] = $drMat['uom_id'];
//                            $newRow['issued_qty'] = 0;
//                            $newRow['received_qty'] = $matrow['return_qty'];
//                            $newRow['rate'] = $drMat['rate'];
//                            $newRow['rate_fc'] = $drMat['rate_fc'];
//                            $newRow['disc_is_value'] = true;
//                            $newRow['disc_percent'] = 0;
//                            $newRow['disc_amt'] = (($drMat['disc_amt']/$drMat['issued_qty']) * $matrow['return_qty']);
//                            $newRow['disc_amt_fc'] = (($drMat['disc_amt_fc']/$drMat['issued_qty']) * $matrow['return_qty']);
//                            
//                            if ($this->bo->fc_type_id == 0) {
//                                $newRow['bt_amt'] = round(($matrow['return_qty'] * $drMat['rate']) - $newRow['disc_amt'], \app\cwf\vsla\Math::$amtScale);
//                            } else {
//                                $newRow['bt_amt_fc'] = round(($matrow['return_qty'] * $matrow['rate_fc']) - $newRow['disc_amt_fc'], \app\cwf\vsla\Math::$amtScale);
//                                $newRow['bt_amt'] = round($newRow['bt_amt_fc'] * $this->bo->exch_rate, \app\cwf\vsla\Math::$amtScale);
//                            }
//                            $newRow['item_amt'] = $newRow['bt_amt'] + $newRow['tax_amt'];
//                            $this->bo->stock_tran->AddRow($newRow);
//                            $sl_no += 1;
//                        }
//                    }
//                }
//                // Fetch GST Information
//                if($this->bo->annex_info->Value()->is_gst_inv) {
//                    $cmmGst = new \app\cwf\vsla\data\SqlCommand();
//                    $cmmGst->setCommandText("Select * From tx.gst_tax_tran Where voucher_id=:pstock_id");
//                    $cmmGst->addParam('pstock_id', $this->bo->annex_info->Value()->origin_inv_id);
//                    $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
//                    foreach($this->bo->stock_tran->Rows() as &$drTran) {
//                        $drGst = $dtGst->findRow('gst_tax_tran_id', $drTran['reference_tran_id']);
//                        if(count($drGst)>1) {
//                            foreach($dtGst->getColumns() as $col) {
//                                $drTran['gtt_'.$col->columnName] = $drGst[$col->columnName];
//                            }
//                        }
//                        // Reset with previously clculated values
//                        $drTran['gtt_sgst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_sgst_amt'], \app\cwf\vsla\Math::$amtScale);
//                        $drTran['gtt_cgst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cgst_amt'], \app\cwf\vsla\Math::$amtScale);
//                        $drTran['gtt_igst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_igst_amt'], \app\cwf\vsla\Math::$amtScale);
//                        $drTran['gtt_cess_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cess_amt'], \app\cwf\vsla\Math::$amtScale);
//                        $drTran['gtt_bt_amt'] = $drTran['bt_amt'];
//                        $drTran['tax_amt'] = $drTran['gtt_sgst_amt'] + $drTran['gtt_cgst_amt'] + $drTran['gtt_igst_amt'] + $drTran['gtt_cess_amt'];
//                    }
//                }
            } else {
                $this->bo->fc_type_id = 0;
                $this->bo->exch_rate = 1;
            }
        }
         
        // Set custom field
        $this->bo->voucher_id = $this->bo->stock_id;
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $this->bo->voucher_id = $this->bo->stock_id;
    }

}
