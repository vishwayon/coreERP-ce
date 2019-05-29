<?php

namespace app\cwf\sys\controllers;

/**
 * Description of WfApprovalController
 *
 * @author dev
 */
class WfApprovalController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\cwf\sys\wfApproval\ModelWfApproval();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return $this->renderPartial('@app/cwf/sys/wfApproval/ViewWfApproval', ['model' => $model]);
    }

    public function actionMob() {
        $model = new \app\cwf\sys\wfApproval\ModelWfApproval();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return $this->renderPartial('@app/cwf/sys/wfApproval/ViewWfApprovalMob', ['model' => $model]);
    }

    public function actionGetData() {
        $model = new \app\cwf\sys\wfApproval\ModelWfApproval();
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }
    
    public function actionGetDocData($doc_id) {
        $model = new \app\cwf\sys\wfApproval\ModelWfApproval();
        $model->getDocData($doc_id);
        $result = array();
        $result['jsondata'] = $dtResult;
        return json_encode($result);
    }

    public function actionSetData($params) {
        $param_array = json_decode($params, true);
        $model = new \app\cwf\sys\wfApproval\ModelWfApproval();
        $model->wf_ar_id = $param_array['wf_ar_id'];
        $model->doc_id = $param_array['doc_id'];
        $model->wf_approved = (bool) $param_array['wf_approved'];
        $model->wf_comment = $param_array['wf_comment'];
        $model->closeWfApproval($model);
        $result['brokenrules'] = $model->brokenrules;
        $result['status'] = $model->status;
        return json_encode($result);
    }

    public function actionRequestApproval($params) {
        $param_array = json_decode($params, true);
        $model = \app\cwf\sys\wfApproval\ModelWfApproval::create($param_array);
        if (count($model->brokenrules) == 0) {
            $model->addWfApproval();
        }
        $result['brokenrules'] = $model->brokenrules;
        $result['status'] = $model->status;
        return json_encode($result);
    }
    
    public function actionGetWfArData($doc_id){
        $dtResult = \app\cwf\sys\wfApproval\WfApprovalHelper::getWfApprData($doc_id);
        $result = array();
        $result['dt_wf_ar'] = $dtResult;
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionValidateWfArData($customer_id, $order_val){
        $dtResult = \app\cwf\sys\wfApproval\WfApprovalHelper::validateApproval($customer_id, $order_val);
        $result = array();
        $result = $dtResult;
        $result['status'] = 'ok';
        return json_encode($result);
    }
}
