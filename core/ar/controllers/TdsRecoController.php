<?php

namespace app\core\ar\controllers;
class TdsRecoController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ar\tdsReco\ModelTDSReco();
        return $this->renderPartial('@app/core/ar/tdsReco/ViewTDSReco',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ar\tdsReco\ModelTDSReco();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ar\tdsReco\ModelTDSReco();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['customer_id']=$postData->customer_id;
        $filter_array['view_type_id']=$postData->view_type_id;
        $filter_array['as_on']=$postData->as_on;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
