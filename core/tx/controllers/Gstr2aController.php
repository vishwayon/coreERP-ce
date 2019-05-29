<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\controllers;

/**
 * Description of Gstr2aController
 *
 * @author girishshenoy
 */
class Gstr2aController extends \app\cwf\vsla\base\WebController {

    public function actionUpload2a() {
        if (count($_FILES) == 1) {
            $file = $_FILES['gstr_resp_file'];
            $gstr2aParser = new \app\core\tx\gstr2aRecoV2\Gstr2aParser();
            $result = $gstr2aParser->setFile($file['tmp_name']);
            if ($result->status == 'OK') {
                $gstr2aParser->saveToDB();
            }
        }
        return json_encode($result);
    }

    public function actionGetGstr2aMatchView() {
        return $this->renderPartial('@app/core/tx/gstr2aRecoV2/Matched2aView');
    }

    public function actionGetGstr2aRecoData(int $gst_ret_id) {
        $reco_data = \app\core\tx\gstr2aRecoV2\Gstr2aRecoHelper::tryToMatch($gst_ret_id);

        // Fetch saved Data also
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.gstr2a_id, a.supp_gstin, 
            case when a.supplier_id=99 then 'Various Suppliers' else coalesce(b.supplier,'') end as supplier, 
            a.voucher_id, a.doc_date, a.bill_no, a.bill_dt, a.base_amt, (a.sgst_amt+a.cgst_amt+a.igst_amt) gst_amt, a.match_by
                From tx.gstr2a a
                Left Join ap.supplier b On a.supplier_id = b.supplier_id
                Where a.gst_ret_id = :pgst_ret_id
                    And a.voucher_id != ''");
        $cmm->addParam("pgst_ret_id", $gst_ret_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $savedItems = $dt->asArray('supp_gstin', ['supplier', 'voucher_id', 'doc_date', 'bill_no', 'bill_dt', 'base_amt', 'gst_amt', 'match_by']);
        $saved_data = [];
        foreach ($savedItems as $k => $bills) {
            $s_info = new \stdClass();
            $s_info->supp_gstin = $k;
            $s_info->supplier = $bills[0]['supplier'];
            $s_info->bill_data = $bills;
            $saved_data[] = $s_info;
        }

        return json_encode(['status' => 'OK', 'reco_data' => $reco_data, 'saved_data' => $saved_data]);
    }

    public function actionGstr2aRecoSave() {
        $reco_data = json_decode(\yii::$app->request->getBodyParam('mdata'));
        $gst_ret_id = \yii::$app->request->getBodyParam('gst_ret_id');

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Update tx.gstr2a
                Set voucher_id = :pvch_id, 
                    doc_date = :pdoc_date, 
                    voucher_amt = :pvch_amt, 
                    match_by = :pmatch_by,
                    supplier_id = :psupp_id
                Where gstr2a_id = :pgstr2a_id");
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        $cmm->addParam("pvch_id", "");
        $cmm->addParam("pdoc_date", "1970-01-01");
        $cmm->addParam("pvch_amt", 0);
        $cmm->addParam("pmatch_by", "");
        $cmm->addParam("psupp_id", -1);
        $cmm->addParam("pgstr2a_id", "");

        $cn->beginTransaction();
        try {
            foreach ($reco_data as $reco_item) {
                foreach ($reco_item->matched as $mitem) {
                    $cmm->setParamValue("pvch_id", $mitem->prg_bill->voucher_id);
                    $cmm->addParam("pdoc_date", $mitem->prg_bill->doc_date);
                    $cmm->addParam("pvch_amt", $mitem->prg_bill->bt_amt);
                    $cmm->addParam("pmatch_by", $mitem->match_by);
                    $cmm->addParam("psupp_id", $mitem->prg_bill->supplier_id);
                    $cmm->addParam("pgstr2a_id", $mitem->gstr2a_bill->gstr2a_id);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
            }
            $cn->commit();
        } catch (\Exception $ex) {
            $cn->rollBack();
            return json_encode(['status' => 'FAIL', 'msg' => $ex->getMessage()]);
        }
        return json_encode(['status' => 'OK', 'msg' => 'Success']);
    }

    public function actionUnmatchGstr2a() {
        $res = '';
        try {
            $voucher_id = \yii::$app->request->getBodyParam('voucher_id');
            $bill_no = \yii::$app->request->getBodyParam('bill_no');
            $gst_ret_id = \yii::$app->request->getBodyParam('gst_ret_id');
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Update tx.gstr2a
                Set voucher_id = '', 
                    doc_date = :pdoc_date, 
                    voucher_amt = 0, 
                    match_by = '',
                    supplier_id = -1
                Where gst_ret_id = :pgst_ret_id and bill_no=:pbill_no and voucher_id=:pvoucher_id");
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            $cmm->addParam("pdoc_date", "1970-01-01");
            $cmm->addParam("pgst_ret_id", $gst_ret_id);
            $cmm->addParam("pbill_no", $bill_no);
            $cmm->addParam("pvoucher_id", $voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            $res = json_encode(['status' => 'OK']);
        } catch (\Exception $ex) {
            $res = json_encode(['status' => 'FAIL', 'msg' => $ex->getMessage()]);
        }
        return $res;
    }

}
