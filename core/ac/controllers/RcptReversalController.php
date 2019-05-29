<?php

namespace app\core\ac\controllers;

class RcptReversalController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\ac\rcptReversal\ModelRcptReversal();
        return $this->renderPartial('@app/core/ac/rcptReversal/ViewRcptReversal', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\core\ac\rcptReversal\ModelRcptReversal();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\core\ac\rcptReversal\ModelRcptReversal();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

}
