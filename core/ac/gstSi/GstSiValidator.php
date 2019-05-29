<?php

namespace app\core\ac\gstSi;
/**
 * GstSiValidator
 * @author Girish
 */
class GstSiValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGstSiEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        
        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }
        
        // Set Amt In Words   
        If ($this->bo->credit_amt > 0) {
            $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->credit_amt);
            $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
        }
        
        foreach($this->bo->si_tran->Rows() as &$drsi) {
            $drsi['gtt_rc_sec_id'] = $this->bo->annex_info->Value()->gst_rc_info->rc_sec_id;
        }
        
        foreach($this->bo->si_tran->Rows() as $dr) {
            if($dr['gtt_bt_amt'] == 0 || $dr['account_id'] == -1) {
                $this->bo->addBRule('Incomplete line item Sl# '.$dr['sl_no']);
            }
        }
    }
    
    public function validateBeforeUnPost() {
        if(boolval($this->bo->annex_info->Value()->si_info->is_auto)) {
            $this->bo->addBRule('Unposting not allowed for Auto-generated Self-Invoice');
        }
    }
    
    public function validateBeforePost() {
        // Do nothing for the time being
    }
}
