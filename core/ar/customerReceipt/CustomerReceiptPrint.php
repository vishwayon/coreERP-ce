<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerReceipt;
/**
 *
 * @author Priyanka
 */
class CustomerReceiptPrint extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        $rptOption->rptParams["pcredit_tot_words"] = '';
        // Amount in words for task print
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from ar.fn_rcpt_report(:pvoucher_id);');
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
            If ($result->Rows()[0]['net_settled'] > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $result->Rows()[0]['net_settled']);
                $rptOption->rptParams["pcredit_tot_words"] = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
            }
        }
        
        return $rptOption;
    }
}
