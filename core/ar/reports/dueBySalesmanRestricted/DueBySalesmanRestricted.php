<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\reports\dueBySalesmanRestricted;
/**
 * Description of CustomerDueBySalesman
 *
 * @author Priyanka
 */
class DueBySalesmanRestricted extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        if($rptOption->rptParams['preport_type'] == -1){
            array_push($rptOption->brokenRules, 'Please Select Report Type.');
        }
        $rptOption->rptParams['psalesman_id'] = -1;
        

        // Select Salesman_ID for logged in user
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select salesman_id from ar.salesman where user_id = :puser_id');
        $cmm->addParam('puser_id',  \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {  
             $rptOption->rptParams['psalesman_id'] = $result->Rows()[0]['salesman_id'];
        }     
        
        $rptCaption = "As on ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        
        $rptOption->rptParams['preport_period'] = $rptCaption;
        $rptOption->rptParams['pin_reporting_sm'] = true;
        
        //*** select rpt name to be opened as per selected report ***
        
        //Salesman Outstanding Summary
        if($rptOption->rptParams['preport_type'] == 0)
        {
            $rptOption->rptName='SalesmanOutstandingSummary';  
        }
        //Salesman Outstanding Detailed
        elseif ($rptOption->rptParams['preport_type'] == 1) 
        {
            $rptOption->rptName='SalesmanOutstandingDetailed';
        }
        //Ageing Analysis Summary
        elseif ($rptOption->rptParams['preport_type'] == 2) 
        {
            $rptOption->rptName='AgeingAnalysis';
        }
        //Ageing Analysis Detailed
        elseif ($rptOption->rptParams['preport_type'] == 3) 
        {           
            if ($rptOption->rptParams['psub_tot']) {
                $rptOption->rptName = 'AgeingAnalysisDetailedSubTot';
            } Else {
                $rptOption->rptName = 'AgeingAnalysisDetailed';
            }
        }
        return $rptOption;
    }
}
