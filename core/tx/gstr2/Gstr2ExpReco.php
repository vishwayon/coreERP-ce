<?php

namespace app\core\tx\gstr2;

/**
 * Gstr2 Expense Reco
 *
 * @author Girish
 */
class Gstr2ExpReco extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        if ($this->allowConsolidated && ($rptOption->rptParams['pbranch_id'] == '' || $rptOption->rptParams['pbranch_id'] == '-1')) {
            array_push($rptOption->brokenRules, 'Please Select Branch.');
        }

        if ($this->allowConsolidated && $rptOption->rptParams['pbranch_id'] == 0) {
            if ($rptOption->rptParams['pfilter_gst_state']) {
                $newBrId = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 500000;
                $newBrId = $newBrId + intval(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
                $rptOption->rptParams['pbranch_id'] = $newBrId;
            }
        }

        if (strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])) {
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }

        if ($rptOption->rptParams['paccount_id'] == '' || $rptOption->rptParams['paccount_id'] == '-1' || $rptOption->rptParams['paccount_id'] == '-99') {
            $rptOption->rptParams['paccount_id'] = 0;
        }

        if ($rptOption->rptParams['pgroup_path'] == 'All') {
            $rptOption->rptParams['pgroup_path'] = '{A005%,A006%}';
        } else {
            $rptOption->rptParams['pgroup_path'] = '{' . $rptOption->rptParams['pgroup_path'] . '%}';
        }

        $rptCaption = "Between " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"]) . " And " .
                \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        $rptOption->rptParams['preport_period'] = $rptCaption;

        return $rptOption;
    }

}
