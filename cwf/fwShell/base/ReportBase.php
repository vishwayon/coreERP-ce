<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\fwShell\base;

/**
 * Description of ValidatorBase
 *
 * @author girish
 */
abstract class ReportBase {

    public $rptOption;
    protected $reportID;
    protected $allowConsolidated = FALSE;

    public function initialise($reportID) {
        $this->reportID = $reportID;
        $accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($reportID);
        if ($accessLevel == \app\cwf\vsla\security\AccessLevels::CONSOLIDATED) {
            $this->allowConsolidated = TRUE;
        } else {
            $this->allowConsolidated = FALSE;
        }
    }

    public function onRequestReport($rptOption) {
        
    }

    public function onRequestMailReport($rptOption) {
        return $this->getEmailDefaults();
    }

    /*
     * returns array with
     * [
            'mail_send_to' => '',
            'mail_cc_to' => '',
            'mail_subject' => '',
            'mail_body' => ''
        ]
     */
    protected function getEmailDefaults() {
        $emailOptions = [
            'mail_send_to' => '',
            'mail_cc_to' => '',
            'mail_subject' => '',
            'mail_body' => ''
        ];
        return $emailOptions;
    }

    public function getModel() {
        throw new \Exception('Must override getModel() in ReportBase for proper implementation. Do not call this method from inherited class');
    }

}
