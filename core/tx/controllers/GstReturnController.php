<?php

namespace app\core\tx\controllers;

/**
 * GstReturnController
 * @author girishshenoy
 */
class GstReturnController extends \app\cwf\vsla\base\WebController {

    public function actionGetPendingDocView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr1/GstrPendingDocView');
    }

    public function actionGetPendingDocData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr1\Gstr1Worker::getPendingDocData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr1SummaryView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr1/Gstr1SummaryView');
    }

    public function actionGetGstr1SummaryData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr1\Gstr1Worker::getSummaryData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr1DetailView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr1Detail/Gstr1DetailView');
    }
    
    public function actionGetGstr1DetailData(int $gst_ret_id, int $detail_type) {
        $model = new \app\core\tx\gstr1Detail\Gstr1Detail();
        $gstr_data = $model->get_data($gst_ret_id, $detail_type);

        return json_encode($gstr_data);
    }
    
    public function actionGetGstr1DetailCsv(int $gst_ret_id, int $detail_type) {
        $model = new \app\core\tx\gstr1Detail\Gstr1Detail();
        $gstr_data = $model->get_data($gst_ret_id, $detail_type);
        
        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        
        if($detail_type == 4) {
            $fileName = 'gstr1_b2b_' . date('d_m_Y_').time() . '.csv';
            $source = $gstr_data['b2b'];
        } elseif ($detail_type == 7) {
            $fileName = 'gstr1_b2cs_' . date('d_m_Y_').time() . '.csv';
            $source = $gstr_data['b2cs'];
        } elseif ($detail_type == 8) {
            $fileName = 'gstr1_exempt_' . date('d_m_Y_').time() . '.csv';
            $source = $gstr_data['exemp'];
        }
        
        // prepare columns
        foreach($source->getColumns() as $col) {
            $cols[] = $col->columnName;
        }
        //open file
        $fhandle = fopen($pathName . $fileName, 'w');
        fputcsv($fhandle, $cols, ',', '"');
        foreach($source->Rows() as $dr) {
            fputcsv($fhandle, $dr, ',', '"');
        }
        fclose($fhandle);

        $fresult = [
            'status' => 'OK',
            'filePath' => $virtualPath . $fileName,
            'fileName' => $fileName,
        ];
        return json_encode($fresult);
    }

    public function actionGetGstr1DetailFile(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $gstr_data = \app\core\tx\gstr1\Gstr1Worker::getDetailData($dataParams);

        $jdata = json_encode($gstr_data);
        $cmm = New \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into tx.gst_ret_data(gst_ret_id, jdata, last_updated)
                                Values(:pret_id, :pjdata::jsonb, current_timestamp(0))
                                Returning gst_ret_data_id");
        $cmm->addParam("pret_id", $dataParams->gst_ret_id);
        $cmm->addParam('pjdata', $jdata);
        $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        $fileName = 'gstr1_' . $dataParams->gst_state_id . '_' . $dataParams->ret_period . '_' .
                $gstr_data['gstin'] . '_' . $dt_id->Rows()[0]['gst_ret_data_id'] . '.json';
        $fhandle = fopen($pathName . $fileName, 'w');
        fwrite($fhandle, $jdata);

        // validate ctin errors
        $invalidCtins = [];
        foreach ($gstr_data['b2b'] as $b2b) {
            if (!\app\core\tx\gstr1\Gstr1Worker::is_ctin_valid($b2b->ctin)) {
                $invalidCtins[] = $b2b;
            }
        }
        // Has errors, return errors and data
        if (count($invalidCtins) > 0) {
            $fresult = [
                'status' => 'ERROR',
                'invalidctin' => $invalidCtins,
                'filePath' => $virtualPath . $fileName,
                'fileName' => $fileName,
            ];
        } else {
            $fresult = [
                'status' => 'OK',
                'filePath' => $virtualPath . $fileName,
                'fileName' => $fileName,
            ];
        }
        return json_encode($fresult);
    }

    public function actionGetGstr2PendingDocView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2/GstrPendingDocView');
    }

    public function actionGetGstr2PendingDocData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getPendingDocData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr2RecoView() {
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2/Gstr2RecoView');
    }

    public function actionGetGstr2RecoData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getRecoData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr2SummaryView() {
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        return \yii::$app->controller->renderPartial('@app/core/tx/gstr2/Gstr2SummaryView', ['res' => $res]);
    }

    public function actionGetGstr2SummaryData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getSummaryData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr2DetailData(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getDetailData($dataParams);
        return json_encode($result);
    }

    public function actionGetGstr2DetailFile(string $jsonParams) {
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getDetailData($dataParams);

        $jdata = json_encode($result);
        $cmm = New \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into tx.gst_ret_data(gst_ret_id, jdata, last_updated)
                                Values(:pret_id, :pjdata::jsonb, current_timestamp(0))
                                Returning gst_ret_data_id");
        $cmm->addParam("pret_id", $dataParams->gst_ret_id);
        $cmm->addParam('pjdata', $jdata);
        $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        $fileName = 'gstr2_' . $dataParams->gst_state_id . '_' . $dataParams->ret_period . '_' .
                $result['gstin'] . '_' . $dt_id->Rows()[0]['gst_ret_data_id'] . '.json';
        $fhandle = fopen($pathName . $fileName, 'w');
        fwrite($fhandle, $jdata);

        return json_encode(
                ['filePath' => $virtualPath . $fileName,
                    'fileName' => $fileName
        ]);
    }

    public function actionGstrRespView() {
        return $this->renderPartial('@app/core/tx/gstrResp/GstrRespView', []);
    }

    public function actionGstr2aRespView() {
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        return $this->renderPartial('@app/core/tx/gstr2aResp/Gstr2aRespView', ['res' => $res]);
    }

    public function actionGetGstr2aMatchView() {
        return $this->renderPartial('@app/core/tx/gstr2aReco/Matched2aView');
    }

    public function actionGstrRespParse() {
        if (count($_FILES) == 1) {
            $file = $_FILES['gstr_resp_file'];
            $fileContents = file_get_contents($file['tmp_name']);
            /*
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', $fileContents);
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, b2b->>'ctin' supp_ctin, to_date(inv->>'idt', 'DD-MM-YYYY') inv_dt, inv->>'inum' inv_num, inv->>'pos' pos, 
                                                inv->>'rchrg' rchrg, inv->>'inv_typ' inv_typ, Sum((inv_itms->'itm_det'->>'txval')::Numeric) Over (Partition by inv->>'inum') taxable_val,
                                                b2b->>'error_cd' error_cd, b2b->>'error_msg' error_msg
                                        From tx.gstr_resp a, jsonb_array_elements(jdata->'error_report'->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv, 
                                                jsonb_array_elements(inv->'itms') inv_itms
                                )
                                Select *
                                From inv_info
                                Where gstr_resp_id = :pid
                                Order by supp_ctin, inv_dt");
            $cmm->addParam("pid", $dt_id->Rows()[0]['gstr_resp_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode(['status' => 'OK', 'dt' => ['b2b' => $dt]]);
            */
            $dt= \app\core\tx\gstrResp\GstrRespHelper::getGstrErrors($fileContents);
            return json_encode(['status' => 'OK', 'dt' => $dt]);
        } else {
            return json_encode(['status' => 'ERROR']);
        }
    }

    public function actionGstr2aRespParse() {
        if (count($_FILES) == 1) {
            $file = $_FILES['gstr_resp_file'];
            $fileContents = file_get_contents($file['tmp_name']);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', $fileContents);
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, b2b->>'ctin' supp_ctin, to_date(inv->>'idt', 'DD-MM-YYYY') inv_dt, inv->>'inum' inv_num, inv->>'pos' pos, 
                                                inv->>'rchrg' rchrg, inv->>'inv_typ' inv_typ, 
                                                (inv->>'val')::Numeric inv_val,(inv_itms->'itm_det'->>'rt')::Numeric rt,
                                                (inv_itms->'itm_det'->>'txval')::Numeric taxable_val,
                                                COALESCE((inv_itms->'itm_det'->>'samt')::Numeric,0) sgst,
                                                COALESCE((inv_itms->'itm_det'->>'camt')::Numeric,0) cgst,
                                                COALESCE((inv_itms->'itm_det'->>'iamt')::Numeric,0) igst
                                        From tx.gstr_resp a, jsonb_array_elements(jdata->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv, 
                                                jsonb_array_elements(inv->'itms') inv_itms

                                ),
                                supp_ctin 
                                as 
                                ( select a.annex_info->'satutory_details'->>'gstin' ctin, min(a.supplier) supp_name 
                                 	from ap.supplier a
                                 	group by a.annex_info->'satutory_details'->>'gstin'
                                   ) 
                                Select a.*, case when b.supp_name is null then '---NOT-FOUND---' else b.supp_name end
                                From inv_info a
                                Left Join supp_ctin b on a.supp_ctin = b.ctin
                                Where gstr_resp_id = :pid
                                Order by supp_name, supp_ctin, inv_dt");
            $cmm->addParam("pid", $dt_id->Rows()[0]['gstr_resp_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode(['status' => 'OK', 'dt' => ['b2b' => $dt]]);
        } else {
            return json_encode(['status' => 'ERROR']);
        }
    }

    public function actionGetGstr2aReco() {
        if (count($_FILES) == 1) {
            $file = $_FILES['gstr_resp_file'];
            $fileContents = file_get_contents($file['tmp_name']);

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', $fileContents);
            $dt_saved = \app\cwf\vsla\data\DataConnect::getData($cmm);

            $gstr2a_data = json_decode($fileContents);
            $dataParams = new \stdClass();
            $dataParams->gst_state_id = \yii::$app->request->getBodyParam('gst_state_id');
            $dataParams->ret_period_from = \app\cwf\vsla\utils\FormatHelper::GetDBDate(\yii::$app->request->getBodyParam('ret_period_from'));
            $dataParams->ret_period_to = \app\cwf\vsla\utils\FormatHelper::GetDBDate(\yii::$app->request->getBodyParam('ret_period_to'));
            $b2b_data = \app\core\tx\gstr2\Gstr2Worker::getB2BDataforReco($dataParams);

            $result = \app\core\tx\gstr2aReco\Gstr2aRecoHelper::tryToMatch($gstr2a_data, $b2b_data);
            return json_encode(['status' => 'OK', 'reco_data' => $result, 'gstr_resp_id' => $dt_saved->Rows()[0]['gstr_resp_id']]);
        } else {
            return json_encode(['status' => 'ERROR']);
        }
    }

    public function actionGstnReqView() {
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        return $this->renderPartial('@app/core/tx/gstn/GstnRequest', ['res' => $res]);
    }

    public function actionGstnReqOtp() {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = \yii::$app->request->getBodyParams();
        $gst_user = $params['username'];
        $statecd = $params['statecd'];

        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $apiaccess->ipaddress,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/authentication/otprequest?email=' .
                urlencode($apiaccess->useremail), $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        return json_encode($res);
    }

    public function actionGstnAuthToken() {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = \yii::$app->request->getBodyParams();
        $otp = $params['gstn_otp'];
        $txn = $params['gstn_txn'];
        $gst_user = $params['gstn_username'];
        $statecd = $params['gstn_statecd'];
        $ipaddress = $params['gstn_ip'];

        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/authentication/authtoken?email=' .
                urlencode($apiaccess->useremail) . '&otp=' . $otp, $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            //$this->storeGstnSession(json_encode($result));
            \app\core\tx\gstIN\GstINWorker::storeGstnSession(json_encode($result));
        }
        return json_encode($res);
    }
    
    public function actionGstnRefreshToken() {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = \yii::$app->request->getBodyParams();
        $txn = $params['gstn_txn'];
        $gst_user = $params['gstn_username'];
        $statecd = $params['gstn_statecd'];
        $ipaddress = $params['gstn_ip'];

        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/authentication/refreshtoken?email=' .
                urlencode($apiaccess->useremail) , $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            //$this->storeGstnSession(json_encode($result));
            \app\core\tx\gstIN\GstINWorker::storeGstnSession(json_encode($result));
        }else{
            \app\core\tx\gstIN\GstINWorker::removeGstnSession();
        }
        return json_encode($res);
    }

    public function actionGstnReqGstr2a($data) {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = json_decode($data, true);
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        $txn = $res->txn;
        $gst_user = $res->username;
        $statecd = $res->statecd;
        $gstin = $params['gstin'];
        $retperiod = $params['retperiod'];
        $ipaddress = $res->ipaddress;

        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/gstr2a/b2b?email=' . urlencode($apiaccess->useremail) .
                '&gstin=' . $gstin . '&retperiod=' . $retperiod, $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', json_encode($result->data));
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("With inv_info
                                As
                                (	Select a.gstr_resp_id, b2b->>'ctin' supp_ctin, to_date(inv->>'idt', 'DD-MM-YYYY') inv_dt, inv->>'inum' inv_num, inv->>'pos' pos, 
                                                inv->>'rchrg' rchrg, inv->>'inv_typ' inv_typ, 
                                                (inv->>'val')::Numeric inv_val,(inv_itms->'itm_det'->>'rt')::Numeric rt,
                                                (inv_itms->'itm_det'->>'txval')::Numeric taxable_val,
                                                COALESCE((inv_itms->'itm_det'->>'samt')::Numeric,0) sgst,
                                                COALESCE((inv_itms->'itm_det'->>'camt')::Numeric,0) cgst,
                                                COALESCE((inv_itms->'itm_det'->>'iamt')::Numeric,0) igst
                                        From tx.gstr_resp a, jsonb_array_elements(jdata->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv, 
                                                jsonb_array_elements(inv->'itms') inv_itms

                                )
                                Select a.*, case when b.supplier is null then '---NOT-FOUND---' else b.supplier end
                                From inv_info a
                                Left Join ap.supplier b on a.supp_ctin = b.annex_info->'satutory_details'->>'gstin'
                                Where gstr_resp_id = :pid
                                Order by supplier, supp_ctin, inv_dt");
            $cmm->addParam("pid", $dt_id->Rows()[0]['gstr_resp_id']);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode(['status' => 'OK', 'dt' => ['b2b' => $dt]]);
        } else {
            return json_encode(['status' => 'ERROR', 'message' => 'Request failed.', 'error' => $res['desc']]);
        }
    }

    public function actionGstnGstr2aReco($data) {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = json_decode($data, true);
        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        $txn = $res->txn;
        $gst_user = $res->username;
        $statecd = $res->statecd;
        $gstin = $params['gstin'];
        $retperiod = $params['retperiod'];
        $ipaddress = $res->ipaddress;
        $recfrom = $params['recfrom'];
        $recto = $params['recto'];

        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/gstr2a/b2b?email=' . urlencode($apiaccess->useremail) .
                '&gstin=' . $gstin . '&retperiod=' . $retperiod, $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', json_encode($result->data));
            $dt_saved = \app\cwf\vsla\data\DataConnect::getData($cmm);

            $dataParams = new \stdClass();
            $dataParams->gst_state_id = $statecd;
            $dataParams->ret_period_from = \app\cwf\vsla\utils\FormatHelper::GetDBDate($recfrom);
            $dataParams->ret_period_to = \app\cwf\vsla\utils\FormatHelper::GetDBDate($recto);
            $b2b_data = \app\core\tx\gstr2\Gstr2Worker::getB2BDataforReco($dataParams);

            $result = \app\core\tx\gstr2aReco\Gstr2aRecoHelper::tryToMatch($result->data, $b2b_data);
            return json_encode(['status' => 'OK', 'reco_data' => $result, 'gstr_resp_id' => $dt_saved->Rows()[0]['gstr_resp_id']]);
        } else {
            return json_encode(['status' => 'ERROR', 'message' => 'Request failed.', 'error' => $res['desc']]);
        }
    }

    public function actionGstr2aRecoSave() {
        $mdata = \yii::$app->request->getBodyParam('mdata');
        $gstr_resp_id = \yii::$app->request->getBodyParam('gstr_resp_id');
        $gst_ret_id = \yii::$app->request->getBodyParam('gst_ret_id');

        $sql = "Select * From tx.sp_gstr2a_reco_add_update(:pgst_ret_id, :pgstr_resp_id, :pjdata) as gstr2a_reco_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pgst_ret_id', $gst_ret_id);
        $cmm->addParam('pgstr_resp_id', $gstr_resp_id);
        $cmm->addParam('pjdata', $mdata);
        $dt_res = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = [];
        if (count($dt_res->Rows()) == 1) {
            if ($dt_res->Rows()[0]['gstr2a_reco_id'] != -1) {
                $result['status'] = "OK";
                $result['gstr2a_reco_id'] = $dt_res->Rows()[0]['gstr2a_reco_id'];
            } else {
                $result['status'] = "FAIL";
                $result['msg'] = "Server Error, trying to save reco";
            }
        } else {
            $result['status'] = "FAIL";
            $result['msg'] = "Server Error, trying to save reco";
        }
        return json_encode($result);
    }

    public function actionGetGstr2aSavedReco($gst_ret_id) {
        $sql = "Select jdata From tx.gstr2a_reco Where gst_ret_id = :pgst_ret_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pgst_ret_id', $gst_ret_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            return $dt->Rows()[0]['jdata'];
        } else {
            return json_encode('');
        }
    }

    public function actionUploadGstnGstr2($jsonParams) {
        //step 1 - save to db for reference        
        $dataParams = json_decode($jsonParams);
        $result = \app\core\tx\gstr2\Gstr2Worker::getDetailData($dataParams);

        $jdata = json_encode($result);
        $cmm = New \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert Into tx.gst_ret_data(gst_ret_id, jdata, last_updated)
                                Values(:pret_id, :pjdata::jsonb, current_timestamp(0))
                                Returning gst_ret_data_id");
        $cmm->addParam("pret_id", $dataParams->gst_ret_id);
        $cmm->addParam('pjdata', $jdata);
        $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);

        //step 2 - actual upload
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }

        $res = \app\core\tx\gstIN\GstINWorker::getGstnSession();
        $txn = $res->txn;
        $gst_user = $res->username;
        $statecd = $res->statecd;
        $gstin = $result['gstin'];
        $retperiod = $result['fp'];
        $ipaddress = $res->ipaddress;

        $header = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'gstin' => $gstin,
            'ret_period' => $retperiod,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('PUT', 'http://api.mastergst.com/gstr2/retsave?email=' . urlencode($apiaccess->useremail), $header, $jdata);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', json_encode($result->data));
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $ref_id = $result->data->reference_id;

            $cmmRet = new \app\cwf\vsla\data\SqlCommand();
            $cmmRet->setCommandText("Update tx.gst_ret 
                                        Set annex_info = jsonb_set(annex_info, '{gstr2a_reco_info, gstn_ret_ref_id}', :pref_id::jsonb, true) 
                                        where gst_ret_id = :pgst_ret_id");
            $cmmRet->addParam('pref_id', $ref_id);
            $cmmRet->addParam('pgst_ret_id', $dataParams->gst_ret_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmmRet);

            return json_encode(['status' => 'OK', 'ref' => $ref_id]);
        } else {
            return json_encode(['status' => 'ERROR', 'message' => 'Request failed.', 'error' => $res['desc']]);
        }
    }

    public function actionGstnRetStatus($jsonParams) {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $dataParams = json_decode($jsonParams);


        $header = [
            'Accept' => 'application/json',
            'gst_username' => $gst_user,
            'state_cd' => $statecd,
            'ip_address' => $ipaddress,
            'txn' => $txn,
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/gstr/retstatus?gstin=' . $gstin . '&returnperiod=' . $retperiod .
                '&refid=' . $dataParams->gstn_ret_ref_id . '&email' . urlencode($apiaccess->useremail), $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);
        $res = \app\core\tx\gstIN\GstINWorker::processGstnResponse($result);
        if ($res['status'] == 'success') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', json_encode($result->data));
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $retstatus = $result->data->status_cd;
            $retstatusinfo = 'Error';
            switch ($retstatus) {
                case 'P':
                    $retstatusinfo = 'P : Request processed.';
                    break;
                case 'PE':
                    $retstatusinfo = 'PE : Request processed but request data has error.';
                    break;
                case 'ER':
                    $retstatusinfo = 'ER : Error occured while processing request.';
                    break;
                default:
                    $retstatusinfo = 'Error';
                    break;
            }
            return json_encode(['status' => 'OK', 'retstatusinfo' => $retstatusinfo]);
        } else {
            return json_encode(['status' => 'ERROR', 'message' => 'Request failed.', 'error' => $res['desc']]);
        }
    }

    public function actionValidGstin() {
        $apiaccess = \app\core\tx\gstIN\GstINWorker::getGstnApiInfo();
        if ($apiaccess == NULL) {
            return json_encode(['status' => 'API access not configured.']);
        }
        $params = \yii::$app->request->getBodyParams();
        $header = [
            'Accept' => 'application/json',
            'client_id' => $apiaccess->clientid,
            'client_secret' => $apiaccess->clientsecret
        ];

        $req = new \GuzzleHttp\Psr7\Request('GET', 'http://api.mastergst.com/public/search?email='
                . urlencode($apiaccess->useremail) . '&gstin=' . $params['cgstin'], $header);
        $client = new \GuzzleHttp\Client();
        $resp = $client->send($req);
        $result = json_decode((string) $resp->getBody());
        \app\core\tx\gstIN\GstINWorker::logGstnSession($result);

        if ($result->status_cd == 1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Insert Into tx.gstr_resp(jdata) Values(:pjson_data) Returning gstr_resp_id");
            $cmm->addParam('pjson_data', json_encode($result->data));
            $dt_id = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode(['status' => 'OK', 'gstin_info' => $result]);
        } else {
            return json_encode(['status' => 'ERROR', 'gstin_info' => $result]);
        }
    }

}
