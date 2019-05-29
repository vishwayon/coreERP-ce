<?php

namespace app\core\pos\inv;
/**
 * Description of InvEventhandler
 *
 * @author Girish Shenoy
 */
class InvEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase implements \app\cwf\vsla\xmlbo\ISequence {        
    
    public function generateNewSeqID(\PDO $cn) {
        $vch_id = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From pos.sp_get_pos_doc_id(:pdoc_type, :ptday_session_id, :pnew_doc_id)');
        $cmm->addParam('pdoc_type', $this->bo->doc_type);
        $cmm->addParam('ptday_session_id', $this->bo->tday_session_id);
        $cmm->addParam('pnew_doc_id', "", \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        $vch_id = $cmm->getParamValue('pnew_doc_id');
        return $vch_id;
    }
    
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
                    Where a.user_id=:puser_id and a.company_id=:pcompany_id And a.tday_status=0
                        And a.finyear = :pfinyear");
            $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
            $cmm->addParam('pfinyear', $this->bo->finyear);
            
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
                $this->bo->vat_type_id = 101; // Local Sales Always
                //$this->bo->annex_info->Value()->order_date = '1970-01-01';
                // Default Stock Location 
                $this->bo->default_sl = [
                        'stock_location_id' => $dr['stock_location_id'], 
                        'stock_location_name' => $dr['stock_location_name']
                    ];
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

    

}
