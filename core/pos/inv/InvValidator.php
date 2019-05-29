<?php

namespace app\core\pos\inv;

/**
 * Description of InvValidator
 *
 * @author girish
 */
class InvValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase {
    public function validateInvEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Ensure max date cut-off for GST
        if(strtotime($this->bo->doc_date) > strtotime('2017-06-30')) {
            $this->bo->addBRule('VAT Invoice not allowed after 01 Jul, 2017');
        }
        
        // ensure line items
        if(count($this->bo->inv_tran->Rows())==0) {
            $this->bo->addBRule('Atleast one stock item required to save/complete invoice');
        }
        
        
        if($this->action == \app\cwf\vsla\workflow\DocWorkflow::WF_SEND || $this->action == \app\cwf\vsla\workflow\DocWorkflow::WF_POST) {
            $inv_settle = $this->bo->inv_settle->Rows()[0];
            if($inv_settle['is_cash'] == false && $inv_settle['is_cheque'] == false && $inv_settle['is_card'] == false && $inv_settle['is_customer'] == false) {
                $this->bo->addBRule('Select atleast one form of settlement before completing the Invoice');
            }
            if($inv_settle['is_cash'] && ($inv_settle['cash_account_id'] == -1 || $inv_settle['cash_amt'] == 0)) {
                $this->bo->addBRule('Cash Account or amount missing for cash settlement');
            }
            if($inv_settle['is_cheque'] && ($inv_settle['cheque_account_id'] == -1 || $inv_settle['cheque_amt'] == 0 || $inv_settle['cheque_no'] == '')) {
                $this->bo->addBRule('Cheque Account/Amount/Number missing for cheque settlement');
            }
            if($inv_settle['is_card'] && ($inv_settle['cc_mac_id'] == -1 || $inv_settle['card_amt'] == 0 || $inv_settle['card_no'] == '')) {
                $this->bo->addBRule('Credit Card Settlement machine ref. or amount missing for card settlement');
            }
            if($inv_settle['is_customer'] && ($inv_settle['customer_id'] == -1 || $inv_settle['customer_amt'] == 0)) {
                $this->bo->addBRule('Customer information or amount missing for customer settlement');
            }
            
            
            if($this->bo->inv_amt != ($inv_settle['cash_amt'] + $inv_settle['cheque_amt'] + $inv_settle['card_amt'] + $inv_settle['customer_amt'])) {
                $this->bo->addBRule('Partial invoice settlements not allowed');
            } 
        }
        
        $currency='';
        $subCurrency='';
        $currency_system='';
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows())>0){
            $currency=$dtbr->Rows()[0]['currency'];
            $subCurrency=$dtbr->Rows()[0]['sub_currency'];
            $currency_system=$dtbr->Rows()[0]['currency_system'];
        }

        // Set Amt In Words   
        If($this->bo->inv_amt > 0){
            $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->inv_amt);
            $this->bo->amt_in_words =  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        }
        
    }
    
    public function validateBeforePost() {
        // Validate mat war info
        $cmmMatInfo = new \app\cwf\vsla\data\SqlCommand();
        $cmmMatInfo->setCommandText("Select 
                    material_id,
                    coalesce((annex_info->'war_info'->>'has_war')::boolean, false) has_war,
                    coalesce((annex_info->'supp_info'->>'has_batch')::boolean, false) has_batch
                From st.material a
                Where a.material_id = Any (:pmat_ids::BigInt[])");
        $mats = $this->bo->inv_tran->select('material_id');
        $matids = implode(', ', $mats);
        $cmmMatInfo->addParam('pmat_ids', '{'.$matids.'}');
        $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmmMatInfo);
        $matInfo = $dtMatInfo->asArray('material_id', ['has_war', 'has_batch']);
        // Validate War Info
        $row_cnt = 0;
        foreach ($this->bo->inv_tran->Rows() as $tran_row) {
            $row_cnt += 1;
            $mat_id = intval($tran_row['material_id']);
            if($matInfo[$mat_id][0]['has_war']) {
                foreach ($tran_row['stock_tran_war']->Rows() as $war_row) {
                    if ($war_row['mfg_serial'] == '') {
                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Serial No. is required');
                    }
//  This code is commented as the user is not aware of the mfg. date in all cases
//                    if (strtotime($war_row['mfg_date']) > strtotime($this->bo->doc_date)) {
//                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Date is greater than Document Date');
//                    }
//                    if (strtotime($war_row['mfg_date']) == strtotime('1970-01-01')) {
//                        $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': MFG Date is Required');
//                    }
                }

                $war_row_cnt = count($tran_row['stock_tran_war']->Rows());
                if (intval($tran_row['issued_qty']) != $war_row_cnt) {
                    $this->bo->addBRule('Stock Items - Sl# ' . $row_cnt . ': Warranty Serial count does not match with issued qty.');
                }
            } else {
                // clear all invalid entries
                // This code is commented as the user can enter war information for items of his choice.
                //$tran_row['stock_tran_war']->removeAll();
            }
        }
    }
    
    public function validateBeforeUnpost() {
        
    }
}
