<?php

namespace app\core\tx\controllers;

/**
 * Description of EwbController
 *
 * @author dev
 */
class EwbController extends \app\cwf\vsla\base\WebController {

    public function actionGetEwbJson($jsonParams) {
        $dataParams = json_decode($jsonParams);
        $jdata = \app\core\tx\ewb\EwbWorker::getJsonFile($dataParams);
        return json_encode($jdata);
    }

    public function actionUpdateEwbInDoc($jsonParams) {
        $dataParams = json_decode($jsonParams);
        \app\core\tx\ewb\EwbWorker::updateEwbInDoc($dataParams);
        return json_encode(['status' => 'OK']);
    }

}
