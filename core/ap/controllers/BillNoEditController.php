<?php

namespace app\core\ap\controllers;
class BillNoEditController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ap\billNoEdit\ModelBillNoEdit();
        return $this->renderPartial('@app/core/ap/billNoEdit/ViewBillNoEdit',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ap\billNoEdit\ModelBillNoEdit();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ap\billNoEdit\ModelBillNoEdit();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['account_id']=$postData->account_id;
        $filter_array['view_type_id']=$postData->view_type_id;
        $filter_array['bill_id']=$postData->bill_id;
        $filter_array['from_date']=$postData->from_date;
        $filter_array['to_date']=$postData->to_date;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
