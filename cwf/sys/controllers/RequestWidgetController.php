<?php

namespace app\cwf\sys\controllers;
class RequestWidgetController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\cwf\sys\requestWidget\ModelRequestWidget();
        return $this->renderPartial('@app/cwf/sys/requestWidget/ViewRequestWidget',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\cwf\sys\requestWidget\ModelRequestWidget();
        $model->GetRequestWidgetData();
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
//        $model=new \app\core\ac\bankReco\ModelBankReco();
//        $postData = json_decode(\Yii::$app->request->getRawBody());
//        $model->setData($postData);
//        $filter_array=array();
//        $filter_array['account_id']=$postData->account_id;
//        $filter_array['view_type_id']=$postData->view_type_id;
//        $filter_array['as_on']=$postData->as_on;
//        $model->setFilters($filter_array);
//        $result = array();
//        $result['jsondata']=$model;
//        return json_encode($result);
    }
}
