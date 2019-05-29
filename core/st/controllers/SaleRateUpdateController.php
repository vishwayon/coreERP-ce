<?php

namespace app\core\st\controllers;

class SaleRateUpdateController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\st\saleRateUpdate\ModelSaleRateUpdate();
        return $this->renderPartial('@app/core/st/saleRateUpdate/ViewSaleRateUpdate', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\core\st\saleRateUpdate\ModelSaleRateUpdate();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\core\st\saleRateUpdate\ModelSaleRateUpdate();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $result = array();
        $result['jsondata'] = $model;
        $result['brule'] = array();
        $result['status'] = '';
        if (count($model->brokenrules) == 0) {
            $result['status'] = 'OK';
            
        } else {
            $result['brule'] = $model->brokenrules;
        }
        return json_encode($result);
    }

}
