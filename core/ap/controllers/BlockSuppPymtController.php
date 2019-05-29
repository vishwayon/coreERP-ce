<?php

namespace app\core\ap\controllers;


class BlockSuppPymtController extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ap\blockSuppPymt\ModelBlockSuppPymt();
        return $this->renderPartial('@app/core/ap/blockSuppPymt/ViewBlockSuppPymt',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ap\blockSuppPymt\ModelBlockSuppPymt();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ap\blockSuppPymt\ModelBlockSuppPymt();
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
