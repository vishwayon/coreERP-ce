<?php

namespace app\core\tx\gstrResp;

/**
 * Description of GstrRespHelper
 *
 * @author dev
 */
class GstrRespHelper {

    public static function getGstrErrors($fileContents) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
        $cmm->addParam('pjson_data', $fileContents);
        $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $resp_id = $dt_id->Rows()[0]['gstr_resp_id'];
        $resp_dt['b2b'] = self::getB2bErrors($resp_id);
        $resp_dt['b2cs'] = self::getB2csErrors($resp_id);
        $resp_dt['at'] = self::getAtErrors($resp_id);
        $resp_dt['cdnr'] = self::getCdnrErrors($resp_id);
        return $resp_dt;
    }

    private static function getB2bErrors($resp_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, b2b->>'ctin' supp_ctin, to_date(inv->>'idt', 'DD-MM-YYYY') inv_dt, inv->>'inum' inv_num, inv->>'pos' pos, 
                                                inv->>'rchrg' rchrg, inv->>'inv_typ' inv_typ, Sum((inv_itms->'itm_det'->>'txval')::Numeric) Over (Partition by inv->>'inum') taxable_val,
                                                b2b->>'error_cd' error_cd, b2b->>'error_msg' error_msg
                                        From tx.gstr_resp a, jsonb_array_elements(jdata->'error_report'->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv, 
                                                jsonb_array_elements(inv->'itms') inv_itms
                                        Where a.gstr_resp_id = :pid
                                )
                                Select *
                                From inv_info
                                Where gstr_resp_id = :pid
                                Order by supp_ctin, inv_dt");
        $cmm->addParam("pid", $resp_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    private static function getB2csErrors($resp_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, b2cs->>'pos' pos, b2cs->>'sply_ty' sply_ty,
                                                b2cs->>'typ' typ,  (b2cs->>'rt')::Numeric as rt,
                                                (b2cs->>'txval')::Numeric as txval,
                                                b2cs->>'error_cd' error_cd, b2cs->>'error_msg' error_msg
                                        From tx.gstr_resp a, 
                                                jsonb_array_elements(jdata->'error_report'->'b2cs') b2cs
                                )
                                Select *
                                From inv_info
                                Where gstr_resp_id = :pid");
        $cmm->addParam("pid", $resp_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    private static function getAtErrors($resp_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, at->>'pos' pos, at->>'sply_ty' sply_ty, 
                                                inv_itms->>'rt' rt,((inv_itms->>'ad_amt')::Numeric) ad_amt,
                                                at->>'error_cd' error_cd, at->>'error_msg' error_msg
                                        From tx.gstr_resp a,jsonb_array_elements(jdata->'error_report'->'at') at, 
                                                jsonb_array_elements(at->'itms') inv_itms
                                        Where a.gstr_resp_id = :pid
                                )
                                Select *
                                From inv_info
                                Where gstr_resp_id = :pid");
        $cmm->addParam("pid", $resp_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    private static function getCdnrErrors($resp_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, cdnr->>'ctin' supp_ctin, 
                                                to_date(nt->>'idt', 'DD-MM-YYYY') nt_idt, to_date(nt->>'nt_dt', 'DD-MM-YYYY') nt_dt,
                                                nt->>'inum' nt_num, nt->>'nt_num' nt_inum, 
                                                nt->>'rsn' rsn, nt->>'ntty' ntty, 
                                                cdnr->>'error_cd' error_cd, cdnr->>'error_msg' error_msg
                                       From tx.gstr_resp a, jsonb_array_elements(jdata->'error_report'->'cdnr') cdnr, jsonb_array_elements(cdnr->'nt') nt
                                        Where a.gstr_resp_id = :pid
                                )
                                Select *
                                From inv_info
                                Where gstr_resp_id = :pid
                                Order by supp_ctin, nt_idt");
        $cmm->addParam("pid", $resp_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

}
