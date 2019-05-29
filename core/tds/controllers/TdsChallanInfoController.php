<?php

namespace app\core\tds\controllers;
class TdsChallanInfoController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\tds\tdsChallanInfo\ModelTDSChallanInfo();
        return $this->renderPartial('@app/core/tds/tdsChallanInfo/ViewTDSChallanInfo',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\tds\tdsChallanInfo\ModelTDSChallanInfo();
        $filter_array=array();
         parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\tds\tdsChallanInfo\ModelTDSChallanInfo;
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['as_on']=$postData->as_on;
        $filter_array['view_type_id']=$postData->view_type_id;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
