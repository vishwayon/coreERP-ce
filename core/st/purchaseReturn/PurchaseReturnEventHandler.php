<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\purchaseReturn;
use YaLinqo\Enumerable;

/**
 * Description of SupplierPaymentEventHandler
 *
 * @author Priyanka
 */
class PurchaseReturnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->account_id = $criteriaparam['formData']['SelectSupplier']['account_id'];
            $this->bo->doc_date = $criteriaparam['formData']['SelectSupplier']['doc_date'];
            $this->bo->stock_id = "";
            $this->bo->status = 0;
            if (count($criteriaparam['formData']['SelectStockPurchase']) > 0) {
                $this->bo->reference_id = $criteriaparam['formData']['SelectStockPurchase']['stock_id'];
                $this->bo->sp_date = $criteriaparam['formData']['SelectStockPurchase']['sp_date'];
            } else {
                $this->bo->fc_type_id = 0;
                $this->bo->exch_rate = 1;
            }
            if (count($criteriaparam['formData']['SelectMaterial']) > 0) {
                $this->bo->fc_type_id = $criteriaparam['formData']['SelectMaterial'][0]['fc_type_id'];
                $this->bo->exch_rate = $criteriaparam['formData']['SelectMaterial'][0]['exch_rate'];
            }
            $stock_purchase_id = "";
            // Fill Payable Alloc Tran
            foreach ($criteriaparam['formData']['SelectMaterial'] as $matrow) {
                $stock_purchase_id = $matrow['stock_id'];
                $newRow = $this->bo->stock_tran->newRow();
                $newRow['reference_id'] = $matrow['stock_id'];
                $newRow['reference_tran_id'] = $matrow['stock_tran_id'];
                $newRow['material_id'] = $matrow['material_id'];
                $newRow['material_name'] = $matrow['material_name'];
                $newRow['stock_location_id'] = $matrow['stock_location_id'];
                $newRow['uom_id'] = $matrow['uom_id'];
                $newRow['issued_qty'] = $matrow['return_qty'];
                $newRow['received_qty'] = 0;
                $newRow['rate'] = $matrow['rate'];
                $newRow['rate_fc'] = $matrow['rate_fc'];
                $newRow['disc_is_value'] = false;
                $newRow['disc_percent'] = 0;
                $newRow['disc_amt'] = 0;
                $newRow['disc_amt_fc'] = 0;
                $newRow['tax_schedule_id'] = $matrow['tax_schedule_id'];
                $newRow['en_tax_type'] = $matrow['en_tax_type'];
                $newRow['tax_pcnt'] = $matrow['tax_pcnt'];
                if ($this->bo->fc_type_id == 0) {
                    $newRow['bt_amt'] = round($matrow['return_qty'] * $matrow['rate'], \app\cwf\vsla\Math::$amtScale);
                } else {
                    $newRow['bt_amt_fc'] = round($matrow['return_qty'] * $matrow['rate_fc'], \app\cwf\vsla\Math::$amtScale);
                    $newRow['bt_amt'] = round($newRow['item_amt_fc'] * $this->bo->exch_rate, \app\cwf\vsla\Math::$amtScale);
                }
                if ($matrow['en_tax_type'] == 0) {
                    $newRow['tax_amt'] = $newRow['bt_amt'] * $matrow['tax_pcnt'] / 100;
                }
                $newRow['item_amt'] = $newRow['bt_amt'] + $newRow['tax_amt'];
                $this->bo->stock_tran->AddRow($newRow);
            }
            $bt_total= round(Enumerable::from($this->bo->stock_tran->Rows())->sum('$a==>$a["bt_amt"]'), \app\cwf\vsla\Math::$amtScale);
                
            if ($stock_purchase_id != '') {
                // Fetch Landed Cost from stock purchase
                $sl_no = 0;
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select a.*, b.annex_info->>'items_total_amt' as items_gross
                                    from st.stock_lc_tran a
                                    inner join st.stock_control b on a.stock_id = b.stock_id
                                    where a.stock_id = :pstock_id
                                            and a.supplier_paid = true");
                $cmm->addParam('pstock_id', $stock_purchase_id);
                $dtlc = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach($dtlc->Rows() as $row){
                    $sl_no = $sl_no + 1;
                    $nr = $this->bo->stock_lc_tran->newRow();
                    $nr['stock_id'] = '';
                    $nr['stock_lc_tran_id'] = $sl_no;
                    $nr['en_apportion_type'] = $row['en_apportion_type'];
                    $nr['account_id'] = $row['account_id'];
                    $nr['supplier_paid'] = $row['supplier_paid'];
                    $nr['account_affected_id'] = $row['account_affected_id'];
                    $nr['tax_schedule_id'] = $row['tax_schedule_id'];
                    $nr['en_tax_type'] = $row['en_tax_type'];
                    $nr['tax_pcnt'] = $row['tax_pcnt'];
                    $nr['tax_amt'] = $row['tax_amt'];
                    $nr['bill_no'] = $row['bill_no'];
                    $nr['bill_date'] = $row['bill_date'];
                    $nr['is_taxable'] = $row['is_taxable'];
                    $nr['description'] = $row['description'];
                    $nr['apply_itc'] = $row['apply_itc'];
                    $nr['debit_amt'] = round(($row['debit_amt']/$row['items_gross'])* $bt_total, \app\cwf\vsla\Math::$amtScale);
                    $nr['debit_amt_fc'] = 0;
                    
                    if ($row['en_tax_type'] == 0) {
                        $nr['tax_amt'] = $nr['debit_amt'] * $row['tax_pcnt'] / 100;
                    }
                    $this->bo->stock_lc_tran->AddRow($nr);
                }
            }
        } 
        $this->bo->supplier = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $this->bo->account_id);
        // Fetch Payable Ledger ID for selected Stock Purchase
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select rl_pl_id, doc_date from ac.rl_pl where voucher_id=:pvoucher_id');
        $cmm->addParam('pvoucher_id', $this->bo->reference_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $this->bo->rl_pl_id = $dt->Rows()[0]['rl_pl_id'];
            $this->bo->sp_date = $dt->Rows()[0]['doc_date'];
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}
