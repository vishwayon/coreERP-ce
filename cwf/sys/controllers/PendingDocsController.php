<?php

namespace app\cwf\sys\controllers;

class PendingDocsController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\cwf\sys\pendingDocs\ModelPendingDocs();
        return $this->renderPartial('@app/cwf/sys/pendingDocs/ViewPendingDocs', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\cwf\sys\pendingDocs\ModelPendingDocs();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionGetWfDashboard() {
        $model = new \app\cwf\sys\wfDashboard\ModelWfDashboard();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return $this->renderPartial('@app/cwf/sys/wfDashboard/ViewWfDashboard', ['model' => $model]);
    }

    public function actionGetWfdata() {
        $model = new \app\cwf\sys\wfDashboard\ModelWfDashboard();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetWfdata($params) {
        $param_array = json_decode($params,true);
        $worker = new \app\cwf\sys\wfDashboard\WfWorker();
        $worker->wf_notification_id = $param_array['wf_notification_id'];
        $worker->doc_id = $param_array['doc_id'];
        $worker->wf_approved = (bool) $param_array['wf_approved'];
        $worker->wf_comment = $param_array['wf_comment'];
        $worker->closeWfNotification($worker);
        $result['brokenrules'] = $worker->brokenrules;
        $result['status'] = $worker->status;
        return json_encode($result);
    }

    public function actionAddWfNotification($params) {
        $param_array = json_decode($params,true);        
        $worker = \app\cwf\sys\wfDashboard\WfWorker::createWorker($param_array);
        $worker->addWfNotification();        
        $result['brokenrules'] = $worker->brokenrules;
        $result['status'] = $worker->status;
        return json_encode($result);
    }

}
