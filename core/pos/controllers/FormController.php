<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\pos\controllers;

use app\cwf\vsla\base\WebFormController;

/**
 * Description of FormController
 *
 * @author girish
 */
class FormController extends WebFormController  {
    //put your code here
    
    public function actionGetMatInfo($barcode = '', $mat_id = -1, $vat_type_id = -1, $stock_loc_id = -1, $doc_date = null) {
        if($barcode!= '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.sp_get_matInfo(:pbar_code, :pmat_id, :pvat_type_id, :pstock_loc_id, :pdoc_date, :pfinyear);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pvat_type_id', $vat_type_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode($dtMatInfo->Rows()[0]);
        }
        return json_encode([]);
    }
    
    public function actionGetMatGstInfoSale($barcode = '', $mat_id = -1, $stock_loc_id = -1, $doc_date = null, $cust_id = -1) {
        if($barcode!= '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.fn_mat_info_sale(:pbar_code, :pmat_id, :pstock_loc_id, :pdoc_date, :pfinyear, :pcust_id);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $cmm->addParam('pcust_id', $cust_id);
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode($dtMatInfo->Rows()[0]);
        }
        return json_encode([]);
    }
    
    public function actionEodStart($tsessionid) {
        $result = [
            'status' => 'Fail',
            'msg' => ''
        ];
        // This method starts the EOD process for a tday
        // Ensure that there are no tdays open before this day. Should follow a chronological order
        $tdayWorker = new \app\core\pos\tday\TdayWorker();
        if($tdayWorker->hasPreviousOpenDays($tsessionid)) {
            $result['msg'] = 'Prior period txn. days are open. Cannot run current EOD process';
            return $result;
        }
        
        $tday_info = $tdayWorker->getTxnDayInfo($tsessionid);
        foreach($tday_info as $td_key => $td_val) {
            $result[$td_key] = $td_val;
        }
        $result['company'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_name');
        
        //Step 1: Display Day Sale Summary
        $result['inv_amt'] = $tdayWorker->getSaleSummary($tsessionid);
        
        // Display Sale Detail
        $result['dtSaleDetail'] = $tdayWorker->getSaleDetail($tsessionid);
        
        //Step 2: Display Settlement Summary
        $dtSettle = $tdayWorker->getSettleSummary($tsessionid);
        $result['dtSettle'] = $dtSettle;
        
        // Display Total Settlement
        $settle_amt = 0.00;
        foreach($dtSettle->Rows() as $dr) {
            $settle_amt += floatval($dr['settle_amt']);
        }
        $result['settle_amt'] = $settle_amt;
        
        //Step 3: Display documents in workflow
        $result['dtPendingDoc'] = $tdayWorker->getPendingDocList($tsessionid);
        //Step 3: Allow Close if no documents are unposted
        
        
        return json_encode($result);
    }
    
    public function actionEodStartHandover($tsessionid) {
        // This method closes the tsession and starts Handover
        $tdayWorker = new \app\core\pos\tday\TdayWorker();
        $result = $tdayWorker->closeTdayForHandover($tsessionid);
        return json_encode($result);
    }
}
