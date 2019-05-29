<?php

namespace app\cwf\sys\controllers;

class WidgetController extends \app\cwf\vsla\base\WebController {

    public function actionWidgetlist() {
        $model = new \app\cwf\sys\requestWidget\ModelRequestWidget();
        return $this->renderPartial('@app/cwf/sys/requestWidget/ViewRequestWidget' , ['model' => $model]);
    }

    public function actionGetwidget() {
        $model = new \app\cwf\sys\requestWidget\ModelRequestWidget();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetwidget() {
        $model = new \app\cwf\sys\requestWidget\ModelRequestWidget();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionRequestlist() {
        $model = new \app\cwf\sys\widgetRequest\ModelWidgetRequest();
        return $this->renderPartial('@app/cwf/sys/widgetRequest/ViewWidgetRequest' , ['model' => $model]);
    }

    public function actionGetrequest() {
        $model = new \app\cwf\sys\widgetRequest\ModelWidgetRequest();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetrequest() {
        $model = new \app\cwf\sys\widgetRequest\ModelWidgetRequest();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

}
