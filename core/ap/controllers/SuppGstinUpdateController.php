<?php

namespace app\core\ap\controllers;


class SuppGstinUpdateController extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ap\suppGstinUpdate\ModelSuppGstinUpdate();
        return $this->renderPartial('@app/core/ap/suppGstinUpdate/ViewSuppGstinUpdate',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ap\suppGstinUpdate\ModelSuppGstinUpdate();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ap\suppGstinUpdate\ModelSuppGstinUpdate();
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
