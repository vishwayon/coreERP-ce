<?php

/* This Class will parse the gstr2a json received from GSTN 
 * and populate the data in table -> tx.gstr_2a
 */

namespace app\core\tx\gstr2aRecoV2;

class Gstr2aParser {
    
    /**
     * @var string Contains the Json File Uploaded path
     */
    private $fpath;
    
    /**
     * @var int Contains the gst_return_id for the corresponding file
     */
    private $gst_ret_id;
    
    /**
     * @var stdClass Contains an instance of the Json data in a standard class
     */
    private $gstr2a_data;
    
    /**
     * Contains string representation of file data
     * @var string 
     */
    private $file_data;
    
    /**
     * Sets the path of the file that needs to be uploaded.
     * Also conducts basic validations on the file to match
     * - GST State of connected branch
     * - File Format for b2b/cdn data
     * - File period to ensure relevant gstr2 is available in tx.gst_ret
     * 
     * @param string $fpath The path of the uploaded json file
     */
    public function setFile(string $fpath) {
        return $this->validateFile($fpath);
    }
    
    public function saveToDB() {
        // First store to db cache
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
        $cmm->addParam('pjson_data', $this->file_data);
        $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $resp_id = $dt_id->Rows()[0]['gstr_resp_id'];
        
        $cmmInsert = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With raw_data
                As
                (   Select b2b->>'ctin' ctin, inv->>'pos' pos, inv->>'inum' inum, to_date(inv->>'idt', 'DD-MM-YYYY') idt, inv->>'val' inv_val, 
                        inv->>'chksum' chksum, inv bill_info,
                        md5(b2b->>'ctin' || '/' || to_date(inv->>'idt', 'DD-MM-YYYY')::Text || '/' || (inv->>'inum')::Text)::uuid row_id
                    From tx.gstr_resp a, jsonb_array_elements(jdata->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv
                    Where gstr_resp_id = :presp_id
                )
                Insert Into tx.gstr2a(gstr2a_id, gst_ret_id, gstr_resp_id, supp_gstin, txn_type, pos, bill_no, bill_dt,
                    base_amt, sgst_amt, cgst_amt, igst_amt, bill_amt, chksum, 
                    ref_bill_no, ref_bill_dt, bill_info)
                Select row_id, :pgst_ret_id, :presp_id, raw_data.ctin, 'b2b',
                    raw_data.pos, raw_data.inum, raw_data.idt, 0, 0, 0, 0, raw_data.inv_val::Numeric, raw_data.chksum, 
                    '', '1970-01-01', raw_data.bill_info
                From raw_data
                Where row_id Not In (Select gstr2a_id From tx.gstr2a)";
        $cmmInsert->setCommandText($sql);
        $cmmInsert->addParam('presp_id', $resp_id);
        $cmmInsert->addParam('pgst_ret_id', $this->gst_ret_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmmInsert);
        
        // Calculate and update base_amt, tax information
        $cmmUpdate = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With bill_tax
                As
                (   Select gstr2a_id, 
                        Sum(Coalesce((itms->'itm_det'->>'txval')::Numeric, 0)) txval,
                        Sum(Coalesce((itms->'itm_det'->>'samt')::Numeric, 0)) samt, 
                        Sum(Coalesce((itms->'itm_det'->>'camt')::Numeric, 0)) camt, 
                        Sum(Coalesce((itms->'itm_det'->>'iamt')::Numeric, 0)) iamt 
                    From tx.gstr2a, jsonb_array_elements(bill_info->'itms') itms
                    Where gst_ret_id = :pgst_ret_id
                        -- And gstr_resp_id = :presp_id
                    Group by gstr2a_id
                )
                Update tx.gstr2a a
                Set base_amt = b.txval, 
                    sgst_amt = b.samt, 
                    cgst_amt = b.camt, 
                    igst_amt = b.iamt
                From bill_tax b
                Where a.gstr2a_id = b.gstr2a_id";
        $cmmUpdate->setCommandText($sql);
        //$cmmUpdate->addParam('presp_id', $resp_id);
        $cmmUpdate->addParam('pgst_ret_id', $this->gst_ret_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmmUpdate);
    }
    
    /**
     * 
     * @param string $fpath The absolute file path to read from
     * @return object Returns result with<br>
     * status -> OK or FAIL<br>
     * msg -> contains the validation message on Failure only<br>
     */
    private function validateFile(string $fpath) {
        $result = new \stdClass();
        $result->status = 'FAIL';
        $file_data = file_get_contents($fpath);
        $jdata = json_decode($file_data);
        
        // Validate return period. This should be a period that the GSTR2 has been 
        // created and saved for the branch
        if (isset($jdata->fp) && isset($jdata->gstin) && strlen($jdata->fp) == 6) {
            if($jdata->gstin != \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']) {
                $result->msg = 'GSTIN of JSON file does not match with connected branch GST Info';
                return $result;
            }
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select gst_ret_id From tx.gst_ret
                    Where gst_ret_type_id = 102 And company_id = :pcomp_id
                        And gst_state_id = :pgst_state_id
                        And ret_period = :pret_period");
            $cmm->addParam('pcomp_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pgst_state_id', \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
            $cmm->addParam('pret_period', $jdata->fp);
            $dtRP = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtRP->Rows()) != 1) {
                $result->msg = "GST Return period $jdata->fp not found in GSTR2 returns. Please save the GSTR2 return before processing 2a";
                return $result;
            } else {
                $this->gst_ret_id = $dtRP->Rows()[0]['gst_ret_id'];
            }
        } else {
            $result->msg = 'Json file does not contain Return Period [fp]';
            return $result;
        }
        
        // Validate b2b and cdn nodes.
        // Should contain atleast one node
        if(!isset($jdata->b2b)) {
            if(!isset($jdata->cdn)) {
                $result->msg = 'Could not find b2b/cdn nodes in Json file';
                return $result;
            }
        }
        
        // All Validations passed without fail
        $result->status = 'OK';
        $this->file_data = $file_data;
        $this->gstr2a_data = $jdata;
        return $result;
    }
}
