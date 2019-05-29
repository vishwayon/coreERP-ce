<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\gstDebitNote;

use YaLinqo\Enumerable;

/**
 * Description of GstDebitNoteEventHandler
 *
 * @author Priyanka
 */
class GstDebitNoteEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->supplier_account_id = $criteriaparam['formData']['SelectSupplier']['supplier_id'];
            $this->bo->annex_info->Value()->dcn_type = $criteriaparam['formData']['SelectSupplier']['dcn_type'];
            $this->bo->voucher_id = "";
            $this->bo->status = 0;
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->trigger_id = 'core';
            if (count($criteriaparam['formData']['SelectBill']) > 0) {
                $this->bo->annex_info->Value()->gst_input_info->supplier_state_id = $criteriaparam['formData']['SelectBill'][0]['supp_state_id'];
                $this->bo->annex_info->Value()->gst_input_info->supplier_gstin = $criteriaparam['formData']['SelectBill'][0]['supp_gstin'];
                $this->bo->annex_info->Value()->gst_input_info->supplier_address = $criteriaparam['formData']['SelectBill'][0]['supp_addr'];
                $this->bo->annex_info->Value()->gst_input_info->vat_type_id = $criteriaparam['formData']['SelectBill'][0]['vat_type_id'];
                $this->bo->annex_info->Value()->origin_bill_id = $criteriaparam['formData']['SelectBill'][0]['bill_id'];
                $this->bo->annex_info->Value()->origin_bill_date = $criteriaparam['formData']['SelectBill'][0]['doc_date'];
                $this->bo->annex_info->Value()->origin_is_reg = true;
                $this->getSuppCtpInfo();
//                $sl_no = 1;
//                foreach ($criteriaparam['formData']['SelectBill'] as $matrow) {
//                    $newRow = $this->bo->pymt_tran->newRow();
//                    $newRow['sl_no'] = $sl_no;
//                    $newRow['vch_tran_id'] = $sl_no;
//                    $newRow['reference_id'] = $matrow['bill_id'];
//                    $newRow['reference_tran_id'] = $matrow['bill_tran_id'];
//                    $newRow['bill_amt'] = $matrow['bill_amt'];
//                    $newRow['credit_amt'] = 0;
//                    $newRow['credit_amt_fc'] = 0;
//                    $newRow['debit_amt'] = 0;
//                    $newRow['debit_amt_fc'] = 0;
//                    $newRow['account_id'] = $matrow['account_id'];
//                    $newRow['hsn_sc_id'] = $matrow['hsn_sc_id'];
//                    $newRow['dc'] = 'D';
//                    $newRow['description'] = $matrow['description'];
//                    $newRow['gtt_bt_amt'] = 0;
//                    $this->bo->pymt_tran->AddRow($newRow);
//                    $sl_no += 1;
//                }
            }
            // Fetch GST Information
//            $cmmGst = new \app\cwf\vsla\data\SqlCommand();
//            $cmmGst->setCommandText("Select * From tx.gst_tax_tran Where voucher_id=:pbill_id");
//            $cmmGst->addParam('pbill_id', $this->bo->annex_info->Value()->origin_bill_id);
//            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
//            foreach ($this->bo->pymt_tran->Rows() as &$drTran) {
//                $drGst = $dtGst->findRow('gst_tax_tran_id', $drTran['reference_tran_id']);
//                if (count($drGst) > 1) {
//                    foreach ($dtGst->getColumns() as $col) {
//                        $drTran['gtt_' . $col->columnName] = $drGst[$col->columnName];
//                    }
//                }
//                // Reset with previously clculated values
//                $drTran['gtt_sgst_amt'] = round(($drTran['debit_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_sgst_amt'], \app\cwf\vsla\Math::$amtScale);
//                $drTran['gtt_cgst_amt'] = round(($drTran['debit_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cgst_amt'], \app\cwf\vsla\Math::$amtScale);
//                $drTran['gtt_igst_amt'] = round(($drTran['debit_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_igst_amt'], \app\cwf\vsla\Math::$amtScale);
//                $drTran['gtt_cess_amt'] = round(($drTran['debit_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cess_amt'], \app\cwf\vsla\Math::$amtScale);
//                $drTran['gtt_bt_amt'] = $drTran['debit_amt'];
//                $drTran['tax_amt'] = $drTran['gtt_sgst_amt'] + $drTran['gtt_cgst_amt'] + $drTran['gtt_igst_amt'] + $drTran['gtt_cess_amt'];
//            }
        } else {
            // Fetch GST Information
            $cmmGst = new \app\cwf\vsla\data\SqlCommand();
            $cmmGst->setCommandText("Select * 
                                        From ap.fn_bill_for_dn(:pbranch_id, :psupplier_id, :pfrom_date, :pto_date, :pvoucher_id)
                                        order by doc_date, bill_id, bill_tran_id");
            $cmmGst->addParam('pvoucher_id', $this->bo->annex_info->Value()->origin_bill_id);
            $cmmGst->addParam('pbranch_id', $this->bo->branch_id);
            $cmmGst->addParam('psupplier_id', $this->bo->supplier_account_id);
            $cmmGst->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
            $cmmGst->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
            $cmmGst->addParam('pvoucher_id', $this->bo->annex_info->Value()->origin_bill_id);
            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
            foreach ($this->bo->pymt_tran->Rows() as &$drTran) {
                $drGst = Enumerable::from($dtGst->Rows())->where('$a==>$a["bill_tran_id"] == "' . $drTran['reference_tran_id'] . '"')->toList();
                if (count($drGst) == 1) {
                    $drTran['bill_amt'] = $drGst[0]['bill_amt'];
                }
            }
        }
//
//        // Set custom field
//        $this->bo->voucher_id = $this->bo->stock_id;
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
//        $this->bo->voucher_id = $this->bo->stock_id;
    }

    private function getSuppCtpInfo() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select COALESCE((annex_info->'satutory_details'->>'is_ctp')::bool, false) as is_ctp from ap.supplier where supplier_id = :psupplier_id");
        $cmm->addParam('psupplier_id', $this->bo->account_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->annex_info->Value()->gst_input_info->is_ctp = $result->Rows()[0]['is_ctp'];
        }
    }

}
