<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\purchaseReturnGst;

/**
 * Description of TestInspHelper
 *
 * @author Priyanka
 */
class PurchaseReturnGstHelper {
    //put your code here
    
    public function mnuSpgForPrvCount(){
        
        $today = new \DateTime();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select count(*) as cnt 
                        From 
                        (Select a.material_id, c.account_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                a.stock_id, a.stock_tran_id, sum(b.reject_qty) as reject_qty
                            From st.stock_tran a 
                            Inner Join st.stock_tran_qc b On a.stock_tran_id = b.stock_tran_id
                            Inner join st.stock_control c on a.stock_id = c.stock_id
                            where COALESCE((c.annex_info->>'dcn_ref_id')::varchar, '') = ''
                                    And c.company_id = {company_id}
                                    And c.branch_id = {branch_id}
                                    And c.doc_type = 'SPG'
                                    And c.doc_date <= :pto_date
                            group by a.material_id, c.account_id, a.stock_location_id, a.rate, a.uom_id, a.issued_qty, 	
                                        a.stock_id, a.stock_tran_id
                            Having sum(b.reject_qty) > 0) a");
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0]['cnt'];
        }
        return 0;
    }            
}
