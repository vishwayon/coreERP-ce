<?php

namespace app\core\ar\controllers;


class CustGstinUpdateController extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ar\custGstinUpdate\ModelCustGstinUpdate();
        return $this->renderPartial('@app/core/ar/custGstinUpdate/ViewCustGstinUpdate',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ar\custGstinUpdate\ModelCustGstinUpdate();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ar\custGstinUpdate\ModelCustGstinUpdate();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['view_type_id']=$postData->view_type_id;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
