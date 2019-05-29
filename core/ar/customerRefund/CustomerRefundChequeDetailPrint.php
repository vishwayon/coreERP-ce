<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerRefund;
/**
 *
 * @author Priyanka
 */
class CustomerRefundChequeDetailPrint extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        $rptOption->rptParams["pamt_in_words"] = '';
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select status from ar.rcpt_control where voucher_id = :pvoucher_id');
        $cmm->addParam('pvoucher_id', $rptOption->rptParams["pvoucher_id"]);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {        
            If ($result->Rows()[0]['status'] != 5) {
                $rptOption->brokenRules[] = 'Only posted voucher\'s cheque can be printed.';
                return $rptOption;
            }
        }    
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.customer,c.bank_name, c.bank_branch, c.address as bank_addr, c.other_bank_info
                                from ar.customer a
                                inner join ar.rcpt_control b on a.customer_id = b.customer_account_id
                                inner join ar.customer_bank_info c on a.customer_id = c.customer_id and c.default_bank = true
                                where b.voucher_id = :pvoucher_id');
        $cmm->addParam('pvoucher_id', $rptOption->rptParams["pvoucher_id"]);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) == 0) {   
                $rptOption->brokenRules[] = 'Bank details are not available for Customer.';
                return $rptOption;
        }   
        
        return $rptOption;
    }
}
