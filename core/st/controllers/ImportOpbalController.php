<?php

namespace app\core\st\controllers;
class ImportOpbalController 
    extends \app\cwf\vsla\base\WebController{
    
    public function actionIndex($viewName = null, $viewParams = null) {
        $model=new \app\core\st\importOpbal\ModelImportOpbal();
        $model->getData();
        return $this->renderPartial('@app/core/st/importOpbal/ViewImportOpbal',['model'=>$model]);
    }
    
    public function actionGetdata($params){
        $model=new \app\core\st\importOpbal\ModelImportOpbal();
        $model->getData();
        $result = array();
        $result['jsondata']=$model;
        return json_encode($result);
    }
    
    public function actionSetdata(){
        $model=new \app\core\st\importOpbal\ModelImportOpbal();
        $model->getData();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($model);
        $result = array();
        $result['jsondata']=$model;
        $result['brule']= array();
        $result['status']= '';
        if(count($model->brokenrules) == 0)
        {
            $result['status']=$model->msg;
        }
        else{
            $result['brule']=$model->brokenrules;
        }
        return json_encode($result);
    }
}
