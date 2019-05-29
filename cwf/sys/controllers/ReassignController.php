<?php

namespace app\cwf\sys\controllers;

class ReassignController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\cwf\sys\reAssign\ModelReassign();
        return $this->renderPartial('@app/cwf/sys/reAssign/ViewReassign', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\cwf\sys\reAssign\ModelReassign();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\cwf\sys\reAssign\ModelReassign();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->doc_bo_id = $postData->doc_bo_id;
        $model->from_user_id = $postData->from_user_id;
        $model->to_user_id = $postData->to_user_id;
        $model->find_vch_id = $postData->find_vch_id;
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }
    
    public function actionRoleUsers($role_id, $branch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select false as selected, a.user_id, a.full_user_name, a.email 
                From sys.user a
                Inner Join sys.user_branch_role b On a.user_id=b.user_id
                Where b.role_id = Any (:prole_id::BigInt[])
                    And b.branch_id = :pbranch_id
                Order By a.full_user_name';
        $cmm->setCommandText($sql);
        $cmm->addParam('prole_id', '{' . $role_id . '}');
        $cmm->addParam('pbranch_id', $branch_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = ['user_list' => $dt, 'doc_sender_comment' => ''];
        return json_encode($result);
    }
    
    public function actionPullDoc(){
        $model = new \app\cwf\sys\reAssign\ModelPullDoc();
        return $this->renderPartial('@app/cwf/sys/reAssign/ViewPullDoc', ['model' => $model]);
    }
    
    public function actionGetPulldata($params) {
        $model = new \app\cwf\sys\reAssign\ModelPullDoc();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }
    
    public function actionSetPulldata() {
        $model = new \app\cwf\sys\reAssign\ModelPullDoc();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->doc_bo_id = $postData->doc_bo_id;
        $model->from_user_id = $postData->from_user_id;
        $model->to_user_id = $postData->to_user_id;
        $model->find_vch_id = $postData->find_vch_id;
        $model->setData($postData);
        $model->getData();
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }
    
    public function actionRoleUser($role_id, $branch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select false as selected, a.user_id, a.full_user_name, a.email 
                From sys.user a
                Inner Join sys.user_branch_role b On a.user_id=b.user_id
                Where b.role_id = Any (:prole_id::BigInt[])
                    And b.branch_id = :pbranch_id
                    And b.user_id = :puser_id
                Order By a.full_user_name';
        $cmm->setCommandText($sql);
        $cmm->addParam('prole_id', '{' . $role_id . '}');
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = ['user_list' => $dt, 'doc_sender_comment' => ''];
        return json_encode($result);
    }

}
