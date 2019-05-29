<?php

namespace app\core\ac\gstCashPymt;

use YaLinqo\Enumerable;

/**
 * GstCashPymtValidator
 * @author Priyanka
 */
class GstCashPymtValidator extends \app\core\ac\gstPymt\GstPymtValidator {

    public function validateGstCashPymtEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        parent::validateBusinessRules();

        // Validate Cash Account Limit 
        // 
        // Validate the cash limit if limit specified in settings 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select value from sys.settings where key = 'ac_payc_limit'");
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['value'] > 0 && $result->Rows()[0]['value'] < $this->bo->annex_info->Value()->bill_amt) {
                $this->bo->addBRule('Bill amount cannot be greater than Cash limit ' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($result->Rows()[0]['value']) . '.');
            }
        }
        
//        // Validate negative balance for cash account        
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('
//            gl_bal
//            As
//            (	
//                Select a.account_id, sum(a.debit_amt-a.credit_amt) as balance_amt
//                From ac.general_ledger a
//                where account_id = :paccount_id
//                group by a.account_id
//                Union All -- allocations in current voucher
//                Select :paccount_id, -1 * :pbill_amt
//            )
//            Select a.rl_pl_id, b.voucher_id, Sum(a.balance_amt)
//            From gl_bal a 
//            Having Sum(a.balance_amt) < 0;');
//        $cmm->addParam('paccount_id', $this->bo->account_id);
//        $cmm->addParam('pbill_amt', $this->bo->bill_amt);
//        $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        if(count($dtExcess->Rows())>0) {
//            $this->bo->addBRule('Bill settlement(s) exceed balance available for ['.$dtExcess->Rows()[0]['voucher_id'].']. Kindly resettle the bill.');
//        }
    }

    public function validateBeforePost() {
        parent::validateBeforePost();
        
        $amt = parent::validateCashAccLimitOnPost($this->bo->annex_info->Value()->bill_amt, $this->bo->account_id, $this->bo->doc_date);
        if($amt > 0){
            $this->bo->addBRule('Bill amount cannot be greater than balance limit ' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($amt) . ' for selected account.');
        }
    }
}
