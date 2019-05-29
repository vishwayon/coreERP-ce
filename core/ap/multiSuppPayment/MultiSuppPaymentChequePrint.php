<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\multiSuppPayment;
/**
 *
 * @author Priyanka
 */
class MultiSuppPaymentChequePrint extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        $rptOption->rptParams["pamt_in_words"] = '';
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select status from ap.pymt_control where voucher_id = :pvoucher_id');
        $cmm->addParam('pvoucher_id', $rptOption->rptParams["pvoucher_id"]);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {        
            If ($result->Rows()[0]['status'] != 5) {
                $rptOption->brokenRules[] = 'Only posted voucher\'s cheque can be printed.';
                return $rptOption;
            }
        }     
        
        // Amount in words for task print
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select sum(a.credit_amt) as credit_amt
                                from ap.fn_pymt_info_for_gl_post(:pvoucher_id) a
                                inner join ac.account_head b on a.account_id = b.account_id
                                where b.account_type_id = 1');
        $cmm->addParam('pvoucher_id', $rptOption->rptParams["pvoucher_id"]);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
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
            If ($result->Rows()[0]['credit_amt'] > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $result->Rows()[0]['credit_amt']);
                $rptOption->rptParams["pamt_in_words"] = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
            }
        }
        
        return $rptOption;
    }
}
