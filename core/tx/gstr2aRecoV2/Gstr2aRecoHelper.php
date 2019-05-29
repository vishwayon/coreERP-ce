<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\gstr2aRecoV2;

/**
 * Description of Gstr2aRecoHelper
 *
 * @author girishshenoy
 */
class Gstr2aRecoHelper {

    //put your code here

    public function download2aView() {
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aRecoV2/Download2aView', ['res' => $res]);
    }

    public function upload2aView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aRecoV2/Upload2aView');
    }

    public function matched2aView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aRecoV2/Matched2aView');
    }

    public static function tryToMatch(int $gst_ret_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select gst_ret_id, gst_ret_type_id, gst_state_id, ret_period, ret_period_from, ret_period_to
                From tx.gst_ret
                Where gst_ret_id = :pgrt_id");
        $cmm->addParam("pgrt_id", $gst_ret_id);
        $dtGstRet = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtGstRet->Rows()) == 0) {
            throw new \Exception("Invalid gst return id [$gst_ret_id]");
        }
        
        // First extract data from Purchase Register
        $stateBrId = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 500000 
                + intval(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select supplier, gstin, doc_date, voucher_id, 
                    bill_no, bill_date, 
                    Sum(bt_amt) bt_amt, Sum(sgst_amt+cgst_amt+igst_amt) gst_amt,
                    supplier_id
                From ap.fn_purchase_register_report(:pcomp_id, :pbranch_id, :psupp_id, :pfrom_date, :pto_date, 
                    0, :pgroup_path, false)
                Where length(gstin) > 2
                    And voucher_id Not In ( Select x.voucher_id 
                                            From tx.gstr2a x
                                            Where x.voucher_id != '')
                                            --Inner Join tx.gst_ret y On x.gst_ret_id = y.gst_ret_id
                                            --Where y.gst_state_id = :pgst_state_id )
                Group by supplier_id, supplier, gstin, doc_date, voucher_id, bill_no, bill_date
                Order by supplier, gstin, doc_date, voucher_id");
	$cmm->addParam('pcomp_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $stateBrId);
        $cmm->addParam('psupp_id', 0);
        $cmm->addParam('pfrom_date', $dtGstRet->Rows()[0]['ret_period_from']);
        $cmm->addParam('pto_date', $dtGstRet->Rows()[0]['ret_period_to']);
        //$cmm->addParam('pgst_state_id', $dtGstRet->Rows()[0]['gst_state_id']);
        $cmm->addParam('pgroup_path', 'All');
        $dtPRG = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.gstr2a_id, a.supp_gstin, a.txn_type, a.pos, a.bill_no, a.bill_dt, a.base_amt, 
                (a.sgst_amt+a.cgst_amt+a.igst_amt) gst_amt, a.bill_amt, a.chksum, a.ref_bill_no, a.ref_bill_dt, a.bill_info
            From tx.gstr2a a
            Inner Join tx.gst_ret b On a.gst_ret_id = b.gst_ret_id
            Where a.voucher_id = ''
                And b.gst_state_id = :pgst_state_id");
        $cmm->addParam("pgst_state_id", \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
        $dt2a = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        $reco_result = [];
        
        $prg_gstins = $dtPRG->asArray('gstin', ['supplier_id', 'supplier', 'doc_date', 'voucher_id', 'bill_no', 'bill_date', 'bt_amt', 'gst_amt']);
        foreach($prg_gstins as $prg_gstin => $prg_bills) {
            $gstr2a_bills = $dt2a->findRows("supp_gstin", $prg_gstin);
            // try to match the data
            $try_mrs = self::match2a_item($prg_gstin, $prg_bills, $gstr2a_bills);
            // Define structures to populate
            $m_info = new \stdClass();
            $m_info->ctin = $prg_gstin;
            $m_info->supplier = $prg_bills[0]['supplier'];
            $m_info->supplier_id = $prg_bills[0]['supplier_id'];
            $m_info->matched = $try_mrs->matched;
            $m_info->prg_missed = $try_mrs->prg_missed;
            $m_info->gstr2a_missed = $try_mrs->gstr2a_missed;
            $reco_result[] = $m_info;
        }
        return $reco_result;
    }

    /**
     * Tries to match data for the requested gstin from the two arrays
     * Ensure that all array entries belong to the same gstin before passing 
     * parameter values
     * @param string $prg_gstin
     * @param array $prg_bills
     * @param array $gstr2a_bills
     * @return stdClass Returns class with<br>
     * status => match/unmatch<br>
     * result => An array of prg_bill + gstr2a_bill
     */
    private static function match2a_item(string $prg_gstin, array $prg_bills, array $gstr2a_bills) {
        $trms = new \stdClass();
        $trms->matched = [];
        $trms->prg_missed = [];
        $trms->gstr2a_missed = [];
        
        // We will try to match based on various similarities
        foreach($prg_bills as $prg_bill) {
            // Loop and try to match
            for($i=count($gstr2a_bills)-1;$i>=0;$i--) {
                $gstr2a_bill = $gstr2a_bills[$i];
                if (preg_replace('/[^A-Za-z0-9]/', '', $prg_bill['bill_no']) == preg_replace('/[^A-Za-z0-9]/', '', $gstr2a_bill['bill_no']) 
                        && ($prg_bill['gst_amt'] - $gstr2a_bill['gst_amt'] == 0)) {
                    $trms->matched[] = ['prg_bill' => $prg_bill, 'gstr2a_bill' => $gstr2a_bill, 'match_by' => 'S'];
                    array_splice($gstr2a_bills, $i, 1);
                }
            }
        }
        // remove matched items from prg_bills
        for($k=count($prg_bills)-1;$k>=0;$k--) {
            $prg_bill = $prg_bills[$k];
            foreach($trms->matched as $m) {
                if($prg_bill['voucher_id'] == $m['prg_bill']['voucher_id']) {
                    array_splice($prg_bills, $k, 1);
                }
            }
        }
        
        // For items remaining, we populate in missed
        $trms->prg_missed = $prg_bills;
        $trms->gstr2a_missed = $gstr2a_bills;
        
//        
//        
//        
//        
//        $b2b_match = [];
//        $b2binvs = $b2b_data->findRows("supplier_gstin", $gstr2a_item->ctin);
//        foreach ($b2binvs as $b2binv) {
//            foreach ($gstr2a_item->inv as &$inv2a) {
//                if(!isset($inv2a->voucher_id)) {
//                    $bdt = \DateTime::createFromFormat("d-m-Y", $inv2a->idt);
//                    if (strtotime($bdt->format("Y-m-d")) == strtotime($b2binv['bill_date']) 
//                            && $inv2a->inum == $b2binv['bill_no'] 
//                            && (floatval($inv2a->val) - $b2binv['bill_amt']) >= -1 && (floatval($inv2a->val) - $b2binv['bill_amt']) <= 1) {
//                        // matches bill date with amt
//                        $inv2a->cfs = $gstr2a_item->cfs;
//                        $inv2a->voucher_id = $b2binv['voucher_id'];
//                        $inv2a->flag = ($inv2a->cfs == "Y" ? "A" : "AM");
//                        $inv2a->matched_by = "system";
//                        $b2b_match[] = [
//                            'gstin' => $gstr2a_item->ctin,
//                            'supplier' => self::gstSupplier($gstr2a_item->ctin),
//                            'gstr2a' => $inv2a,
//                            'b2b' => $b2binv
//                        ];
//                    } else if (strtotime($bdt->format("Y-m-d")) == strtotime($b2binv['bill_date']) 
//                            && preg_replace('/[^A-Za-z0-9]/', '', $inv2a->inum) == preg_replace('/[^A-Za-z0-9]/', '', $b2binv['bill_no']) 
//                            && (floatval($inv2a->val) - $b2binv['bill_amt']) >= -1 && (floatval($inv2a->val) - $b2binv['bill_amt']) <= 1) {
//                        // matches bill date with amt
//                        $inv2a->cfs = $gstr2a_item->cfs;
//                        $inv2a->voucher_id = $b2binv['voucher_id'];
//                        $inv2a->flag = ($inv2a->cfs == "Y" ? "A" : "AM");
//                        $inv2a->matched_by = "system";
//                        $b2b_match[] = [
//                            'gstin' => $gstr2a_item->ctin,
//                            'supplier' => self::gstSupplier($gstr2a_item->ctin),
//                            'gstr2a' => $inv2a,
//                            'b2b' => $b2binv
//                        ];
//                    }
//                }
//            }
//        }
//        // Remove reconciled row from source if match found
//        foreach ($gstr2a_item->inv as $invItem) {
//            if (isset($invItem->voucher_id)) {
//                $index = $b2b_data->getRowIndex("voucher_id", $invItem->voucher_id);
//                if ($index != -1) {
//                    $b2b_data->removeRow($index);
//                }
//            }
//        }
        // Return result
        return $trms;
    }

    private static $dt_supplier;

    private static function gstSupplier($gstin) {
        if (!isset(self::$dt_supplier)) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("With supp_info
                As
                (	Select supplier, annex_info->'satutory_details'->>'gstin' gstin From ap.supplier
                        Union All
                        Select supplier, br_addr->>'gstin' gstin 
                        From ap.supplier a, jsonb_array_elements(a.annex_info->'branch_addrs') br_addr
                )
                Select gstin, max(supplier) as supplier
                From supp_info
                Group by gstin
                Order by gstin");
            self::$dt_supplier = \app\cwf\vsla\data\DataConnect::getData($cmm);
        }
        $supp = self::$dt_supplier->findRow('gstin', $gstin);
        if (count($supp) > 0) {
            return $supp['supplier'];
        } else {
            return '--Not Found--';
        }
    }

}
