<?php

namespace app\core\ac\controllers;
class GlRecoController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\ac\glReco\ModelGlReco();
        return $this->renderPartial('@app/core/ac/glReco/ViewGlReco',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\ac\glReco\ModelGlReco();
        $filter_array=array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\ac\glReco\ModelGlReco();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array=array();
        $filter_array['account_id']=$postData->account_id;
        $filter_array['view_type_id']=$postData->view_type_id;
        $filter_array['as_on']=$postData->as_on;
        $filter_array['from_date']=$postData->from_date;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
}
