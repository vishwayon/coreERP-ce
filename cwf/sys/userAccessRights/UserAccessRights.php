<?php

namespace app\cwf\sys\userAccessRights;
/**
 *
 * @author Valli
 */
class UserAccessRights extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if ($rptOption->rptParams['puser_id']=='' || $rptOption->rptParams['puser_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please Select User');
        } 
        
        if ($rptOption->rptParams['prole_id']=='' || $rptOption->rptParams['prole_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please Select Role');
        } 
         
        if ($rptOption->rptParams['pmenu_type']=='' || $rptOption->rptParams['pmenu_type']=='-1'){
            array_push($rptOption->brokenRules, 'Please Select Menu Type');
        } 
        
        if ($rptOption->rptParams['pmenu_id']=='' || $rptOption->rptParams['pmenu_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please Select Menu');
        }
        return $rptOption;
    }
}
