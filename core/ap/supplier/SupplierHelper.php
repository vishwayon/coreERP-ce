<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplier;

class SupplierHelper {

    public static function getSuppAddr($supplier_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.annex_info->'satutory_details'->>'gst_state_id' as gst_state_id, 
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    a.annex_info->'satutory_details'->>'gstin' as gstin,
                    (a.annex_info->'satutory_details'->>'is_ctp')::Boolean as is_ctp,
                    b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
                        || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as addr
                From ap.supplier a
                Inner Join sys.address b On a.address_id = b.address_id
                Inner Join tx.gst_state c On (a.annex_info->'satutory_details'->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.supplier_id = :psupp_id 
                Limit 1";
        $cmm->setCommandText($sql);
        $cmm->addParam('psupp_id', $supplier_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

}
