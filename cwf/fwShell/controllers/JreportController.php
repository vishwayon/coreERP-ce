<?php

namespace app\cwf\fwShell\controllers;

use app\cwf\vsla\base\WebController;
use app\cwf\vsla\render\JReportHelper;

class JreportController extends WebController {

    protected $viewer_id;

    public function behaviors() {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'Render' => ['post'],
                ],
            ],
        ];
    }

    public function actionRenderpdf() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_PDF);
    }

    public function actionRenderMsDoc() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_DOC);
    }

    public function actionRenderMsXls() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_XLS);
    }

    public function actionRenderOpenDoc() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_ODT);
    }

    public function actionRenderOpenCalc() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_ODS);
    }

    public function actionRenderhtml() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, JReportHelper::OUTPUT_HTML);
    }

    public function actionVchreport() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_HTML);
        /*
          //        if (property_exists($vchrptparams, 'status') && property_exists($vchrptparams, 'pvoucher_id') && property_exists($vchrptparams, 'bo_id')) {
          //            $allow_print = \app\cwf\vsla\security\AccessManager::check_print_access
          //                            ($vchrptparams->bo_id, $vchrptparams->pvoucher_id, $vchrptparams->status);
          //            if ($allow_print) {
          //                \app\cwf\vsla\security\AccessManager::log_doc_print
          //                        ($vchrptparams->bo_id, $vchrptparams->pvoucher_id, $vchrptparams->status);
          //                return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_HTML);
          //            }
          //        }
          //        \Yii::$app->response->headers->add('Output-Type', 'text/html');
          //        return '<h3>Error</h3><p>Print limit has been exceeded for this document.</p>';
         */
    }

    public function actionVchreporttopdf() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_PDF);
    }

    public function actionVchreportMsDoc() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_DOC);
    }

    public function actionVchreportMsXls() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_XLS);
    }

    public function actionVchreportOpenDoc() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_ODT);
    }

    public function actionVchreportOpenCalc() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, JReportHelper::OUTPUT_ODS);
    }

    public function actionViewReport() {
        $xmlPath = \yii::$app->request->bodyParams['xmlPath'];
        $rptOptions = \yii::$app->request->bodyParams['rptOptions'];
        $result = $this->runAction('viewer', ['xmlPath' => $xmlPath, 'rptOptions' => $rptOptions]);
        return $result;
    }

    public function actionViewer($xmlPath, $rptOptions = "") {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = str_replace('../', '@app/', $xmlPath) . '.xml';
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewOption->accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($design->id);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('@app/cwf/fwShell/views/JreportViewer', ['viewForRender' => $viewForRender, 'xmlPath' => $viewOption->xmlViewPath, 'rptOptions' => $rptOptions]);
    }

    private function renderreport($params, $outputType) {
        if (array_key_exists('status', $params) && array_key_exists('pvoucher_id', $params) && array_key_exists('bo_id', $params)) {
            $allow_print = \app\cwf\vsla\security\AccessManager::check_print_access
                            ($params['bo_id'], $params['pvoucher_id'], $params['status']);
            $requested_print = \app\cwf\vsla\security\AccessManager::check_pending_print_request($params['pvoucher_id']);
            if ($allow_print || $requested_print) {
                $jr = new JReportHelper(isset(\yii::$app->params['cwf_config']['reportServer']) ? \yii::$app->params['cwf_config']['reportServer'] : []);
                $jrResult = $jr->renderReport($params, $outputType);
                \app\cwf\vsla\security\AccessManager::log_doc_print
                        ($params['bo_id'], $params['pvoucher_id'], $params['status']);
                if ($requested_print) {
                    JReportHelper::logPrintRequestCompletion($params['pvoucher_id']);
                }
                if ($jrResult['status'] == 'OK') {
                    \Yii::$app->response->headers->add('Output-Type', 'application/json');
                    return json_encode($jrResult['result']);
                } else {
                    \Yii::$app->response->headers->add('Output-Type', 'text/html');
                    return $jrResult['msg'];
                }
            } else {
                \Yii::$app->response->headers->add('Output-Type', 'text/html');
                return '<h3>Error</h3><p>Print limit has been exceeded for this document.</p>';
            }
        } else {
            $jr = new JReportHelper(isset(\yii::$app->params['cwf_config']['reportServer']) ? \yii::$app->params['cwf_config']['reportServer'] : []);
            $jrResult = $jr->renderReport($params, $outputType);
            if ($jrResult['status'] == 'OK') {
                \Yii::$app->response->headers->add('Output-Type', 'application/json');
                return json_encode($jrResult['result']);
            } else {
                \Yii::$app->response->headers->add('Output-Type', 'text/html');
                return $jrResult['msg'];
            }
        }
    }

    public function actionPrint() {
        $this->viewer_id = \yii::$app->request->queryParams['reqtime'];
        return $this->renderPartial('@app/cwf/fwShell/views/PrintView', ['reqtime' => $this->viewer_id]);
    }

    public function actionPrintData() {
        $this->viewer_id = \yii::$app->request->queryParams['reqtime'];
        $raw = \yii::$app->cache->get('rpt-' . $this->viewer_id);
        $result = unserialize($raw);
        return json_encode($result);
    }

    public function actionRequestprint($doc_id) {
        if ($doc_id != NULL && $doc_id != -1 && $doc_id != '') {
            JReportHelper::requestPrint($doc_id);
        }
        return true;
    }

    public function actionMaildata() {
        $req = \Yii::$app->request;
        if (array_key_exists('rptparams', $req->bodyParams)) {
            $vchrptparams = json_decode($req->bodyParams['rptparams'], TRUE);
        } else {
            if (is_array($req->bodyParams)) {
                $vchrptparams = $req->bodyParams;
            } else {
                $vchrptparams = json_decode($req->bodyParams, TRUE);
            }
        }
        $jr = new JReportHelper();
        $res = $jr->getMailOptions($vchrptparams);
        return json_encode($res);
    }

    public function actionMailreport() {
        $req = \Yii::$app->request;
        if (array_key_exists('rptparams', $req->bodyParams)) {
            $vchrptparams = json_decode($req->bodyParams['rptparams'], TRUE);
        } else {
            if (is_array($req->bodyParams)) {
                $vchrptparams = $req->bodyParams;
            } else {
                $vchrptparams = json_decode($req->bodyParams, TRUE);
            }
        }
        $jr = new JReportHelper(isset(\yii::$app->params['cwf_config']['reportServer']) ? \yii::$app->params['cwf_config']['reportServer'] : []);
        $jrResult = $jr->renderReport($vchrptparams, JReportHelper::OUTPUT_PDF);
        if (array_key_exists('bo_id', $vchrptparams)) {
            $boid = $vchrptparams['bo_id'];
        } else {
            $boid = '';
        }
        if ($jrResult['status'] == 'OK' && \app\cwf\vsla\utils\MailHelper::mailid_valid($vchrptparams['send_to'])) {
            $path = JReportHelper::store($jrResult);
            $company = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_name');
            $subject = '';
            if (array_key_exists('body', $vchrptparams) && $vchrptparams['body'] != '') {
                $body = $vchrptparams['body'];
            } else {
                $body = ' Greetings from ' . $company . "\n Please find the attached document for your reference." . $boid;
            }
            if (array_key_exists('body', $vchrptparams) && $vchrptparams['body'] != '') {
                $subject = $vchrptparams['subject'];
            } else {
                $subject = $company . ' Document';
            }
            $from = \yii::$app->params['cwf_config']['mailer']['username'];
            \app\cwf\vsla\utils\MailHelper::SendMailAttachment($vchrptparams['send_to'], $from, $body, $subject
                    , $vchrptparams['cc_to'], '', '', $path);
        } else {
            $jrResult['status'] = 'FAILED';
            $jrResult['msg'] = 'Invalid e-mail id. Failed to mail report.';
        }
        return json_encode($jrResult);
    }
    
    public function actionGetUserPref() {
        
    }
    
    public function actionSetUserPref() {
        $rptID = \yii::$app->request->post('rpt_id');
        $data = \yii::$app->request->post('user_pref');
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Insert Into sys.rpt_user_pref(rpt_user_pref_id, rpt_id, user_id, jdata, last_updated)
                Values(md5(:prpt_id || :pu_id)::uuid, :prpt_id, :puser_id, :pjdata, current_timestamp(0))
                On Conflict (rpt_user_pref_id)
                Do Update Set jdata = :pjdata, last_updated = current_timestamp(0);";
        $cmm->setCommandText($sql);
        $cmm->addParam('prpt_id', $rptID);
        $cmm->addParam('pu_id', (string)\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pjdata', $data);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        return 'OK';
    }

}
