<?php

namespace app\cwf\sys\controllers;

class PrintRequestController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\cwf\sys\printRequest\ModelPrintRequest();
        return $this->renderPartial('@app/cwf/sys/printRequest/ViewPrintRequest', ['model' => $model]);
    }

    public function actionGetdata() {
        $model = new \app\cwf\sys\printRequest\ModelPrintRequest();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\cwf\sys\printRequest\ModelPrintRequest();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

}
