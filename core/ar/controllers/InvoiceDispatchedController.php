<?php

namespace app\core\ar\controllers;


class InvoiceDispatchedController extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ar\invoiceDispatched\ModelInvoiceDispatched();
        return $this->renderPartial('@app/core/ar/invoiceDispatched/ViewInvoiceDispatched',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ar\invoiceDispatched\ModelInvoiceDispatched();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ar\invoiceDispatched\ModelInvoiceDispatched();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['from_date']=$postData->from_date;
        $filter_array['to_date']=$postData->to_date;
        $filter_array['customer_id']=$postData->customer_id;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
