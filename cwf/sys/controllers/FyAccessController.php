<?php

namespace app\cwf\sys\controllers;

/**
 * Description of FyAccessController
 *
 * @author dev
 */
class FyAccessController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\cwf\sys\fyAccess\ModelFyAccess();
        return $this->renderPartial('@app/cwf/sys/fyAccess/ViewFyAccess', ['model' => $model]);
    }

    public function actionGetdata() {
        $model = new \app\cwf\sys\fyAccess\ModelFyAccess();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\cwf\sys\fyAccess\ModelFyAccess();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

}
