<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\advanceSupplierPayment;

/**
 * Description of TestInspHelper
 *
 * @author girishshenoy
 */
class AdvSuppHelper {
    //put your code here
    
    public function mnuAdvForPOCount(){
        
        $today = new \DateTime();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select count(*) as cnt 
                        From 
                        (Select * from ap.fn_po_for_adv_req(:pcompany_id, :pbranch_id)) a");
        $cmm->addParam("pcompany_id", \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam("pbranch_id", \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0]['cnt'];
        }
        return 0;
    }            
}
