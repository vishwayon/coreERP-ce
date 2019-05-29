<?php

namespace app\core\pos\gir;
/**
 * Description of InvEventhandler
 *
 * @author Girish Shenoy
 */
class GstInvRetEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {        
    
    function afterFetch($criteriaparam) {
        // For a new Invoice
        if($this->bo->inv_id == "" or $this->bo->inv_id == "-1") {
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
            $this->bo->inv_id = '';
            $this->bo->status = 0;
            
            // Validate for open tday by the user
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select a.tday_date, a.tday_session_id, a.terminal_id, 
                        b.sale_account_id, b.cash_account_id, b.cheque_account_id, b.cc_mac_id, 
                        coalesce((b.annex_info->>'customer_id')::BigInt, -1) as customer_id,
                        b.stock_location_id, c.stock_location_name
                    From pos.tday a
                    Inner Join pos.terminal b On a.terminal_id=b.terminal_id
                    Left Join st.stock_location c On b.stock_location_id = c.stock_location_id
                    Where a.user_id=:puser_id and a.company_id=:pcompany_id And a.tday_status=0");
            $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
            
            $dtTday = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtTday->Rows())!=1) {
                // Block Usage of document
                $this->bo->status = -1;
                $this->bo->inv_id = 'Txn. Date Failure';
            } else {
                $dr = $dtTday->Rows()[0];
                $this->bo->doc_date = $dr['tday_date'];
                $this->bo->tday_session_id = $dr['tday_session_id'];
                $this->bo->terminal_id = $dr['terminal_id'];
                $this->bo->sale_account_id = $dr['sale_account_id'];
                $this->bo->vat_type_id = 301; // SGST/CGST Local Sales Always
                $this->bo->annex_info->Value()->gst_output_info->cust_state_id = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id'];
                
                // Fetch Origin Invoice information
                $this->fillReturn($criteriaparam);
                
                 // Add new settlement row
                $new_settle_row = $this->bo->inv_settle->NewRow();
                $new_settle_row['cash_account_id'] = $dr['cash_account_id'];
                $new_settle_row['cheque_account_id'] = $dr['cheque_account_id'];
                $new_settle_row['cc_mac_id'] = $dr['cc_mac_id'];
                $new_settle_row['customer_id'] = $dr['customer_id'];
                $this->bo->inv_settle->addRow($new_settle_row);
            }
            
            $this->bo->merge_status = 0;
            
            // Walk-in Customer
            $this->bo->cust_name = 'Walk-in Customer';
            $this->bo->cust_tin = 'N.A.';
            $this->bo->cust_address = 'N.A.';            
        }
    }
    
    public function afterApplySecurity() {
        // Add POS Service User Info
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id from sys.user Where user_name=:puser_name;');
        $cmm->addParam('puser_name', 'POSServiceUser');
        $dtuser = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtuser->Rows())==1) {
            $this->bo->setNextUserID((int)$dtuser->Rows()[0]['user_id']);
        }     
    }
    
    private function fillReturn($criteriaparam) {
        $origin_inv_id = $criteriaparam['formData']['SelectOriginInv']['origin_inv_id'];
        // Fill Control data
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select inv_id, doc_date From pos.inv_control Where inv_id=:pinv_id");
        $cmm->addParam('pinv_id', $origin_inv_id);
        $dtInv = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtInv->Rows())==1) {
            $this->bo->annex_info->Value()->origin_inv_id = $dtInv->Rows()[0]['inv_id'];
            $this->bo->annex_info->Value()->origin_inv_date = $dtInv->Rows()[0]['doc_date'];
            // Fill Line Items
            $cmmOI = new \app\cwf\vsla\data\SqlCommand();
            $cmmOI->setCommandText('Select * From pos.inv_tran Where inv_id=:pinv_id');
            $cmmOI->addParam('pinv_id', $origin_inv_id);
            $dtOI = \app\cwf\vsla\data\DataConnect::getData($cmmOI);
            $sl_no = 0; 
            foreach($dtOI->Rows() as $drOI) {
                $drNew = $this->bo->inv_tran->NewRow();
                $drNew['sl_no'] = $sl_no++;
                $drNew['inv_tran_id'] = $sl_no;
                $drNew['bar_code'] = $drOI['bar_code'];
                $drNew['material_type_id'] = $drOI['material_type_id'];
                $drNew['material_id'] = $drOI['material_id'];
                $drNew['stock_location_id'] = $drOI['stock_location_id'];
                $drNew['uom_id'] = $drOI['uom_id'];
                // Issued is now received
                $drNew['received_qty'] = $drOI['issued_qty'];
                $drNew['bal_qty'] = $drOI['issued_qty'];
                // Rate should be after discount
                $drNew['rate'] = round($drOI['bt_amt'] / $drOI['issued_qty'], 3);
                $drNew['bt_amt'] = $drOI['bt_amt'];
                $drNew['tax_amt'] = $drOI['tax_amt'];
                $drNew['item_amt'] = $drOI['item_amt'];
                $drNew['ref_tran_id'] = $drOI['inv_tran_id'];
                $this->bo->inv_tran->addRow($drNew);
            }
            
            // Fetch GST Information
            $cmmGst = new \app\cwf\vsla\data\SqlCommand();
            $cmmGst->setCommandText("Select * From tx.gst_tax_tran Where voucher_id=:pstock_id");
            $cmmGst->addParam('pstock_id', $this->bo->annex_info->Value()->origin_inv_id);
            $dtGst = \app\cwf\vsla\data\DataConnect::getData($cmmGst);
            foreach($this->bo->inv_tran->Rows() as &$drTran) {
                $drGst = $dtGst->findRow('gst_tax_tran_id', $drTran['ref_tran_id']);
                if(count($drGst)>1) {
                    foreach($dtGst->getColumns() as $col) {
                        $drTran['gtt_'.$col->columnName] = $drGst[$col->columnName];
                    }
                }
                // Reset with previously clculated values
                $drTran['gtt_sgst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_sgst_amt'], \app\cwf\vsla\Math::$amtScale);
                $drTran['gtt_cgst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cgst_amt'], \app\cwf\vsla\Math::$amtScale);
                $drTran['gtt_igst_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_igst_amt'], \app\cwf\vsla\Math::$amtScale);
                $drTran['gtt_cess_amt'] = round(($drTran['bt_amt'] / $drTran['gtt_bt_amt']) * $drTran['gtt_cess_amt'], \app\cwf\vsla\Math::$amtScale);
                $drTran['gtt_bt_amt'] = $drTran['bt_amt'];
                $drTran['tax_amt'] = $drTran['gtt_sgst_amt'] + $drTran['gtt_cgst_amt'] + $drTran['gtt_igst_amt'] + $drTran['gtt_cess_amt'];
            }
        }
        
    }
}


