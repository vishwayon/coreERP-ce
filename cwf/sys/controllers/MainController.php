<?php

namespace app\cwf\sys\controllers;

use app\cwf\vsla\base\WebController;

class MainController extends WebController {

    public function actionAudittrail($formName, $formParams, $formUrl) {
        $viewX = simplexml_load_file('../' . $formName . '.xml');
        $auditTrailParser = new \app\cwf\sys\auditTrail\AuditTrailParser($viewX, $formParams, $formUrl);
        //return $this->rend($this->ModulePath.'/views/MastView.php', ['viewParser' => $viewParser]);
        return $this->renderPartial('AuditTrailView', ['auditTrailParser' => $auditTrailParser, 'formName' => $formName]);
    }

    public function actionGetdata($params) {
        $model = new \app\cwf\sys\auditTrail\ModelAuditTrail();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;

        return json_encode($result);
    }

    public function actionTest() {
        return $this->render('TestTwigTemplate.html.twig');
    }

    public function actionBuildDocId() {
        $docModel = new \app\cwf\sys\buildDocID\DocIdModel();
        return $this->renderPartial('@app/cwf/sys/buildDocID/DocIdView', ['model' => $docModel]);
    }

    public function actionBuildDocIdUpdate() {
        $docModel = new \app\cwf\sys\buildDocID\DocIdModel();
        $result = [];
        if ($docModel->load(\yii::$app->request->post())) {
            try {
                $docModel->commitBuild();
                $result['status'] = 'OK';
            } catch (\Exception $ex) {
                $result['status'] = 'Failed';
                $result['errors'][] = $ex->getMessage();
            }
        } else {
            $result['status'] = 'Failed';
            $result['errors'] = 'Only POST supported';
        }
        return json_encode($result);
    }

    public function actionSearchDoc($docid) {
        $res = \app\cwf\sys\voucherSearch\VoucherSearch::openVoucher($docid);
        return json_encode($res);
    }

    public function actionViewAuditTrail() {
        return $this->renderPartial('@app/cwf/sys/auditTrail/ViewAuditTrail');
    }

    public function actionGetAuditTrail($docid) {
        $res = \app\cwf\sys\auditTrail\ModelAuditTrail::getAuditTrail($docid);
        return json_encode($res);
    }

}
