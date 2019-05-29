<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\stmtOfAccounts;

/**
 * Description of StmtOfAccountsValidator
 *
 * @author Kaustubh
 */
class StmtOfAcBillsReceivable extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if ($rptOption->rptParams['preport_type'] == -1) {
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }

        if ($rptOption->rptParams['paccount_id'] == '' || $rptOption->rptParams['paccount_id'] == '-1') {
            array_push($rptOption->brokenRules, 'Please Select Customer.');
        }

        if ($rptOption->rptParams['pbranch_id'] == '' || $rptOption->rptParams['pbranch_id'] == '-1') {
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }

        if ($rptOption->rptParams['paccount_id'] == '0' &&
                ($rptOption->rptParams['preport_type'] == 0 || $rptOption->rptParams['preport_type'] == 1 || $rptOption->rptParams['preport_type'] == 4)) {
            array_push($rptOption->brokenRules, 'Select a Customer for SOA. Option - All is available only for Ageing');
        }

        $rptCaption = "As on " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);

        $rptOption->rptParams['preport_period'] = $rptCaption;

        //*** select rpt name to be opened as per selected report ***
        //Statement of Accounts
        if ($rptOption->rptParams['preport_type'] == 0) {
            $rptOption->rptName = 'StmtOfAcBillsReceivable';
        }
        //Statement of Accounts Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) {
            $rptOption->rptName = 'StmtOfAcBillsReceivableDetailed';
        }
        //Ageing Analysis Summary
        elseif ($rptOption->rptParams['preport_type'] == 2) {
            $rptOption->rptName = 'StmtOfAcBRAgeingAnalysis';
        }
        //Ageing Analysis Detailed
        elseif ($rptOption->rptParams['preport_type'] == 3) {
            if ($rptOption->rptParams['psub_tot']) {
                $rptOption->rptName = 'StmtOfAcBRAgeingAnalysisDetailedSubTot';
            } Else {
                $rptOption->rptName = 'StmtOfAcBRAgeingAnalysisDetailed';
            }
        }
        //Balance confrimation letter
        elseif ($rptOption->rptParams['preport_type'] == 4) {
            $rptOption->rptName = 'StmtOfAcBRBalanceConf';
        } // Set formated to_date for display
        $rptOption->rptParams['pto_date_for_display'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        $rptOption->rptParams['pbalance'] = 0;
        // Pass total balance value as parameter
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'SELECT COALESCE(sum(debit_amt - credit_amt), 0) as balance FROM ar.fn_stmt_of_ac_br_report_Detailed
                    (
                            :pcompany_id, 
                            :pbranch_id, 
                            :paccount_id,
                            :pto_date, 
                            0::smallint
                    );';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $rptOption->rptParams['pbranch_id']);
        $cmm->addParam('paccount_id', $rptOption->rptParams['paccount_id']);
        $cmm->addParam('pto_date', $rptOption->rptParams['pto_date']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $rptOption->rptParams['pbalance'] = $dt->Rows()[0]['balance'];
        }
        return $rptOption;
    }

    public function onRequestMailReport($rptOption) {
        $rptmailoption = parent::getEmailDefaults();

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select * from sys.report_preset where report_id = :preport_id';
        $cmm->setCommandText($cmmtext);
        $rptname = '';
        if ($rptOption->rptParams['preport_type'] == 0) {
            $rptname = 'StmtOfAcBillsReceivable';
        }
        //Statement of Accounts Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) {
            $rptname = 'StmtOfAcBillsReceivableDetailed';
        }
        $cmm->addParam('preport_id', $rptname);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $rptmailoption['mail_body'] = $dt->Rows()[0]['mail_body'];
        } else {
            $rptmailoption['mail_body'] = "Greetings from " . \app\cwf\vsla\security\SessionManager::getSessionVariable("company_name");
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'select email from sys.address
                        where address_id in (select address_id from ar.customer where customer_id = :pcustomer_id)';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcustomer_id', $rptOption->rptParams['paccount_id']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $rptmailoption['mail_send_to'] = $dt->Rows()[0]['email'];
        }
        $rptmailoption['mail_subject'] = 'Your SOA dated ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams['pto_date']);
        return $rptmailoption;
    }

}
