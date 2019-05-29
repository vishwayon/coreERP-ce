<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierPayment;
/**
 *
 * @author Priyanka
 */
class SupplierPaymentChequeDetailPrint extends \app\cwf\fwShell\base\ReportBase {
    
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
        return $rptOption;
    }
}
