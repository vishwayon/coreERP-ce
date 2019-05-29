<?php

namespace app\core\st\controllers;

class WarrantyInfoController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\st\warrantyInfo\ModelWarrantyInfo();
        return $this->renderPartial('@app/core/st/warrantyInfo/ViewWarrantyInfo', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\core\st\warrantyInfo\ModelWarrantyInfo();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

}
