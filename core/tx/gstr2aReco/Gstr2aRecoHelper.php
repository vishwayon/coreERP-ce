<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\gstr2aReco;

/**
 * Description of Gstr2aRecoHelper
 *
 * @author girishshenoy
 */
class Gstr2aRecoHelper {

    //put your code here

    public function download2aView() {
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aReco/Download2aView', ['res' => $res]);
    }

    public function upload2aView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aReco/Upload2aView');
    }

    public function matched2aView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2aReco/Matched2aView');
    }

    public static function tryToMatch($gstr2a_data, \app\cwf\vsla\data\DataTable $b2b_data) {
        $gstr2a_match = [];
        $gstr2a_unmatched = [];

        
        // First find matching data
        foreach ($gstr2a_data->b2b as $gstr2a_item) {
            $gstr2a_match_result = self::match2a_item($gstr2a_item, $b2b_data);
            $gstr2a_match = array_merge($gstr2a_match, $gstr2a_match_result);

            // Populate 2a unmatched items
            $unmatched_cache = new \stdClass();
            $unmatched_cache->ctin = $gstr2a_item->ctin;
            $unmatched_cache->supplier = self::gstSupplier($gstr2a_item->ctin);
            $unmatched_cache->unmatched_inv2a = [];
            $unmatched_cache->missing_b2b = [];
            foreach ($gstr2a_item->inv as $inv2a) {
                if (!isset($inv2a->voucher_id)) {
                    $inv2a->cfs = $gstr2a_item->cfs;
                    $inv2a->flag = ($inv2a->cfs == "Y" ? "P" : "AM");
                    $inv2a->select = false;
                    $inv2a->voucher_id = "";
                    $unmatched_cache->unmatched_inv2a[] = $inv2a;
                }
            }
            if(count($unmatched_cache->unmatched_inv2a)>0) {
                $gstr2a_unmatched[] = $unmatched_cache;
            }
        }
        
        // Second find unmatched in b2b missed
        foreach ($gstr2a_unmatched as &$g2aum) {
            $b2brows = $b2b_data->findRows('supplier_gstin', $g2aum->ctin);
            foreach($b2brows as &$b2brow) {
                $b2brow['select'] = false;
                $b2brow['show'] = true;
            }
            $g2aum->missing_b2b = $b2brows;
            // Remove copied rows from source
            foreach ($b2brows as $invItem) {
                $index = $b2b_data->getRowIndex("voucher_id", $invItem['voucher_id']);
                if ($index != -1) {
                    $b2b_data->removeRow($index);
                }
            }
        }
        
        // third b2b missed
        $gstr2a_missing = $b2b_data->Rows();
        foreach($gstr2a_missing as &$gm) {
            $gm['flag'] = "I";
            $gm['supplier'] = self::gstSupplier($gm['supplier_gstin']);
        }
        

        return [
            'gstr2a_match' => $gstr2a_match,
            'gstr2a_unmatched' => $gstr2a_unmatched,
            'gstr2a_missing' => $gstr2a_missing
        ];
    }

    private static function match2a_item($gstr2a_item, \app\cwf\vsla\data\DataTable $b2b_data) {
        $b2b_match = [];
        $b2binvs = $b2b_data->findRows("supplier_gstin", $gstr2a_item->ctin);
        foreach ($b2binvs as $b2binv) {
            foreach ($gstr2a_item->inv as &$inv2a) {
                if(!isset($inv2a->voucher_id)) {
                    $bdt = \DateTime::createFromFormat("d-m-Y", $inv2a->idt);
                    if (strtotime($bdt->format("Y-m-d")) == strtotime($b2binv['bill_date']) 
                            && $inv2a->inum == $b2binv['bill_no'] 
                            && (floatval($inv2a->val) - $b2binv['bill_amt']) >= -1 && (floatval($inv2a->val) - $b2binv['bill_amt']) <= 1) {
                        // matches bill date with amt
                        $inv2a->cfs = $gstr2a_item->cfs;
                        $inv2a->voucher_id = $b2binv['voucher_id'];
                        $inv2a->flag = ($inv2a->cfs == "Y" ? "A" : "AM");
                        $inv2a->matched_by = "system";
                        $b2b_match[] = [
                            'gstin' => $gstr2a_item->ctin,
                            'supplier' => self::gstSupplier($gstr2a_item->ctin),
                            'gstr2a' => $inv2a,
                            'b2b' => $b2binv
                        ];
                    } else if (strtotime($bdt->format("Y-m-d")) == strtotime($b2binv['bill_date']) 
                            && preg_replace('/[^A-Za-z0-9]/', '', $inv2a->inum) == preg_replace('/[^A-Za-z0-9]/', '', $b2binv['bill_no']) 
                            && (floatval($inv2a->val) - $b2binv['bill_amt']) >= -1 && (floatval($inv2a->val) - $b2binv['bill_amt']) <= 1) {
                        // matches bill date with amt
                        $inv2a->cfs = $gstr2a_item->cfs;
                        $inv2a->voucher_id = $b2binv['voucher_id'];
                        $inv2a->flag = ($inv2a->cfs == "Y" ? "A" : "AM");
                        $inv2a->matched_by = "system";
                        $b2b_match[] = [
                            'gstin' => $gstr2a_item->ctin,
                            'supplier' => self::gstSupplier($gstr2a_item->ctin),
                            'gstr2a' => $inv2a,
                            'b2b' => $b2binv
                        ];
                    }
                }
            }
        }
        // Remove reconciled row from source if match found
        foreach ($gstr2a_item->inv as $invItem) {
            if (isset($invItem->voucher_id)) {
                $index = $b2b_data->getRowIndex("voucher_id", $invItem->voucher_id);
                if ($index != -1) {
                    $b2b_data->removeRow($index);
                }
            }
        }
        // Return result
        return $b2b_match;
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
