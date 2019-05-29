<?php

namespace app\core\tx\controllers;

use app\cwf\vsla\base\WebFormController;


class FormController extends WebFormController {
    
    
    public function actionCalculatetax($tax_schedule_id, $base_amt, $qty, $tax_detail_temp, $isnew){        
        $tax_detail_temp_new = json_decode($tax_detail_temp, true);
        $dtTaxApplied = \app\core\tx\taxSchedule\worker\TaxScheduleCalculator::CalculateTax($tax_schedule_id, $base_amt, $qty, $tax_detail_temp_new, $isnew);
        $tax_schedule_name ='';
        
        //if(count($dtTaxApplied->Rows()) > 0){
            $tax_schedule_name = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/TaxSchedule.xml', 'tax_schedule', 'tax_schedule_id', $dtTaxApplied->Rows()[0]['tax_schedule_id']);
       // }
        
        $resultTaxDetail = array();
        $resultTaxDetail['tax_applied']=$dtTaxApplied;
        $resultTaxDetail['tax_schedule_name'] = $tax_schedule_name;
        $resultTaxDetail['status']='ok';
        return json_encode($resultTaxDetail);
    }
    
    public function actionGetGstRates() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From tx.gst_rate Where company_id = :pcomp_id');
        $cmm->addParam('pcomp_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return json_encode($dt->Rows());
    }
    
    public function actionGetHsnGstInfo($hsn_sc_id){
        $dt = \app\core\tx\gstIN\HsnScHelper::GetGstHSNInfo($hsn_sc_id);
        if(count($dt->Rows())>0) {
            return $dt->Rows()[0]['gst_hsn_info'];
        }
        return json_encode([]);
    }
    
    public function actionGetHsnList() {
        $dt = \app\core\tx\gstIN\HsnScHelper::GetGstHSNList();
        return json_encode($dt);
    }
}
