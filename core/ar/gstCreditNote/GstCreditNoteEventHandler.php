<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\gstCreditNote;

use YaLinqo\Enumerable;

/**
 * Description of GstCreditNoteEventHandler
 *
 * @author vaishali
 */
class GstCreditNoteEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->customer_account_id = $criteriaparam['formData']['SelectCustomer']['customer_id'];
            $this->bo->annex_info->Value()->dcn_type = $criteriaparam['formData']['SelectCustomer']['dcn_type'];
            $this->bo->voucher_id = "";
            $this->bo->status = 0;
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
            $this->bo->trigger_id = 'core';
            if (count($criteriaparam['formData']['SelectInvoice']) > 0) {
                $this->bo->annex_info->Value()->gst_output_info->customer_state_id = $criteriaparam['formData']['SelectInvoice'][0]['customer_state_id'];
                $this->bo->annex_info->Value()->gst_output_info->customer_gstin = $criteriaparam['formData']['SelectInvoice'][0]['customer_gstin'];
                $this->bo->annex_info->Value()->gst_output_info->customer_addr = $criteriaparam['formData']['SelectInvoice'][0]['customer_addr'];
                $this->bo->annex_info->Value()->gst_output_info->vat_type_id = $criteriaparam['formData']['SelectInvoice'][0]['vat_type_id'];
                $this->bo->annex_info->Value()->origin_inv_id = $criteriaparam['formData']['SelectInvoice'][0]['invoice_id'];
                $this->bo->annex_info->Value()->origin_inv_date = $criteriaparam['formData']['SelectInvoice'][0]['doc_date'];

//                $sl_no = 1;
//                foreach ($criteriaparam['formData']['SelectInvoice'] as $matrow) {
//                    $newRow = $this->bo->rcpt_tran->newRow();
//                    $newRow['sl_no'] = $sl_no;
//                    $newRow['vch_tran_id'] = $sl_no;
//                    $newRow['reference_id'] = $matrow['invoice_id'];
//                    $newRow['reference_tran_id'] = $matrow['invoice_tran_id'];
//                    $newRow['invoice_amt'] = $matrow['invoice_amt'];
//                    $newRow['credit_amt'] = 0;
//                    $newRow['credit_amt_fc'] = 0;
//                    $newRow['debit_amt'] = 0;
//                    $newRow['debit_amt_fc'] = 0;
//                    $newRow['account_id'] = $matrow['account_id'];
//                    $newRow['hsn_sc_id'] = $matrow['hsn_sc_id'];
//                    $newRow['dc'] = 'D';
//                    $newRow['description'] = $matrow['description'];
//                    $newRow['gtt_bt_amt'] = 0;
//                    $this->bo->rcpt_tran->AddRow($newRow);
//                    $sl_no += 1;
//                }
            }
//            // Fetch GST Information
//            $cmmGst = new \app\cwf\vsla\data\SqlCommand();
//            $cmmGst->setCommandText("Select * From tx.gst_tax_tran Where voucher_id=:pinvoice_id");
//            $cmmGst->addParam('pinvoice_id', $this->bo->annex_info->Value()->origin_inv_id);
//            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
//            foreach ($this->bo->rcpt_tran->Rows() as &$drTran) {
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
                                        From ar.fn_inv_for_cn(:pbranch_id, :pcustomer_id, :pfrom_date, :pto_date, :pvoucher_id)
                                        order by doc_date, invoice_id, invoice_tran_id");
            $cmmGst->addParam('pvoucher_id', $this->bo->annex_info->Value()->origin_inv_id);
            $cmmGst->addParam('pbranch_id', $this->bo->branch_id);
            $cmmGst->addParam('pcustomer_id', $this->bo->customer_account_id);
            $cmmGst->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
            $cmmGst->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
            $cmmGst->addParam('pvoucher_id', $this->bo->annex_info->Value()->origin_inv_id);
            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
            foreach ($this->bo->rcpt_tran->Rows() as &$drTran) {
                $drGst = Enumerable::from($dtGst->Rows())->where('$a==>$a["invoice_tran_id"] == "' . $drTran['reference_tran_id'] . '"')->toList();
                if (count($drGst) == 1) {
                    $drTran['invoice_amt'] = $drGst[0]['invoice_amt'];
                }
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
//        $this->bo->voucher_id = $this->bo->stock_id;
    }

}
