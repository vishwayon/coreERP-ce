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
class SupplierPaymentPrint extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        $rptOption->rptParams["pdebit_tot_words"] = '';
        // Amount in words for task print
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select (sum(a.debit_amt) + b.other_adj) as debit_amt_tot'
                . ' from ap.fn_payable_ledger_alloc_report(:pvoucher_id) a, ap.fn_pymt_report(:pvoucher_id) b group by b.other_adj ;');
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
            If ($result->Rows()[0]['debit_amt_tot'] > 0) {
                $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $result->Rows()[0]['debit_amt_tot']);
                $rptOption->rptParams["pdebit_tot_words"] = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);
                $rptOption->rptParams["pdebit_tot"] = $result->Rows()[0]['debit_amt_tot'];
            }
        }

        return $rptOption;
    }

    public function onRequestMailReport($rptOption) {
        $rptmailoption = parent::getEmailDefaults();

        $rptmailoption['mail_body'] = "Greetings from " . \app\cwf\vsla\security\SessionManager::getSessionVariable("company_name");

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'select
                        a.supplier_account_id,b.supplier,c.email
                        from ap.pymt_control a
                        inner join ap.supplier b on a.supplier_account_id=b.supplier_id
                        inner join sys.address c on b.address_id=c.address_id
                        where voucher_id=:pvoucher_id';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pvoucher_id', $rptOption->rptParams['pvoucher_id']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $rptmailoption['mail_send_to'] = $dt->Rows()[0]['email'];
        }
        //$rptmailoption['mail_subject'] = 'Your payment dated ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams['pto_date']);
        return $rptmailoption;
    }

}
