<?php

namespace app\core\pos\gir;

/**
 * Description of InvValidator
 *
 * @author girish
 */
class GstInvRetValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase {
    public function validateGstInvRetEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // ensure line items
        if(count($this->bo->inv_tran->Rows())==0) {
            $this->bo->addBRule('Atleast one stock item required to save/complete Sales Return');
        }
        
        // Validate Excess Returns
        $this->validateExcessReturn();
        
        
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
    
    private function validateExcessReturn() {
        // Ensure that excess returns are not allowed
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With net_qty
            As
            (   Select inv_tran_id, issued_qty 
                From pos.inv_tran Where inv_id=:pinv_id
                Union All
                Select ref_tran_id, -received_qty
                From pos.inv_tran a
                Inner Join pos.inv_control b On a.inv_id=b.inv_id
                Where b.annex_info->>'origin_inv_id' = :pinv_id
                    And b.inv_id != :ppsr_id
            )
            Select inv_tran_id, Sum(issued_qty) as issued_qty 
            From net_qty
            Group by inv_tran_id;");
        $cmm->addParam('pinv_id', $this->bo->annex_info->Value()->origin_inv_id);
        $cmm->addParam('ppsr_id', $this->bo->inv_id);
        $dtOI = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $oi = $dtOI->asArray('inv_tran_id', 'issued_qty');
        foreach ($this->bo->inv_tran->Rows() as $drTran) {
            if(floatval($drTran['received_qty']) > floatval($oi[$drTran['ref_tran_id']])) {
                $this->bo->addBRule('Returned Qty for Sl# '.$drTran['sl_no'].' is greater than issued qty ['.$oi[$drTran['ref_tran_id']].'] in Invoice');
            }
        }
    }
    
    public function validateBeforePost() {
        $this->validateExcessReturn();
        // Remove all blank rows
        for($i=count($this->bo->inv_tran->Rows())-1; $i>=0; $i--) {
            if(floatval($this->bo->inv_tran->Rows()[$i]['received_qty']) == 0.00) {
                $this->bo->inv_tran->removeRow($i);
            }
        }
    }
    
    public function validateBeforeUnpost() {
        $this->bo->addBRule('Sales Return unpost is not allowed.');
    }
}
