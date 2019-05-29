<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\salesReturn;

/**
 * Description of SalesReturnEventHandler
 *
 * @author vaishali
 */
class SalesReturnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->stock_id=="" or $this->bo->stock_id=="-1")
        {
            $this->bo->account_id=$criteriaparam['formData']['SelectCustomer']['account_id'];
            $this->bo->doc_date=$criteriaparam['formData']['SelectCustomer']['doc_date'];
            $this->bo->stock_id="";
            $this->bo->status=0;
            if(count($criteriaparam['formData']['SelectStockInvoice'])>0){
                $this->bo->reference_id=$criteriaparam['formData']['SelectStockInvoice']['stock_id'];
                $this->bo->si_date=$criteriaparam['formData']['SelectStockInvoice']['si_date'];
                //Fetch vat_type
                $cmmvt = new \app\cwf\vsla\data\SqlCommand();
                $cmmvt->setCommandText("Select stock_id, vat_type_id From st.stock_control Where stock_id=:pstock_id");
                $cmmvt->addParam("pstock_id", $criteriaparam['formData']['SelectStockInvoice']['stock_id']);
                $dtvt = \app\cwf\vsla\data\DataConnect::getData($cmmvt);
                if(count($dtvt->Rows()) == 1) {
                    $this->bo->vat_type_id = $dtvt->Rows()[0]['vat_type_id'];
                }
                // This is required for printing in SalesReturn
                $this->bo->annex_info->Value()->origin_inv_id = $criteriaparam['formData']['SelectStockInvoice']['stock_id'];
                $this->bo->annex_info->Value()->origin_inv_date = $criteriaparam['formData']['SelectStockInvoice']['si_date'];
            }
            else{
                $this->bo->fc_type_id=0;
                $this->bo->exch_rate=1;
            }
            if(count($criteriaparam['formData']['SelectMaterial']) > 0){
                $this->bo->fc_type_id=$criteriaparam['formData']['SelectMaterial'][0]['fc_type_id'];
                $this->bo->exch_rate=$criteriaparam['formData']['SelectMaterial'][0]['exch_rate'];
            }
            
            //
            $sl_no = 1;
            foreach($criteriaparam['formData']['SelectMaterial'] as $matrow){
                $newRow=$this->bo->stock_tran->newRow();
                $newRow['sl_no']= $sl_no;
                $newRow['reference_id']= $matrow['stock_id'];
                $newRow['reference_tran_id']= $matrow['stock_tran_id'];
                $newRow['material_type_id']= $matrow['material_type_id'];
                $newRow['material_id']= $matrow['material_id'];
                $newRow['material_name']= $matrow['material_name'];
                $newRow['stock_location_id']= $matrow['stock_location_id'];
                $newRow['uom_id']= $matrow['uom_id'];      
                $newRow['issued_qty']= 0;        
                $newRow['received_qty']=$matrow['return_qty'];               
                $newRow['rate']= $matrow['rate'];               
                $newRow['rate_fc']= $matrow['rate_fc'];            
                $newRow['disc_is_value']= false;
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
                if ($matrow['en_tax_type'] == 0 || $matrow['en_tax_type'] == 1 ) {
                   $newRow['tax_amt'] = round($newRow['bt_amt'] * $matrow['tax_pcnt'] / 100, \app\cwf\vsla\Math::$amtScale);
                } 
                $newRow['item_amt'] = $newRow['bt_amt'] + $newRow['tax_amt'];
                $this->bo->stock_tran->AddRow($newRow);
                $sl_no += 1;
            }
        } 
        else{
              
            
        }
        $this->bo->customer =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ar/lookups/Customer.xml', 'customer', 'customer_id', $this->bo->account_id);
        // Fetch Receivable Ledger ID for selected Stock Invoice
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select rl_pl_id, doc_date from ac.rl_pl where voucher_id=:pvoucher_id');
        $cmm->addParam('pvoucher_id', $this->bo->reference_id);
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){ 
            $this->bo->rl_pl_id= $dt->Rows()[0]['rl_pl_id'];
            $this->bo->si_date= $dt->Rows()[0]['doc_date'];
        }       
        
        $this->bo->voucher_id =  $this->bo->stock_id;
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $this->bo->voucher_id =  $this->bo->stock_id;
    }
}
