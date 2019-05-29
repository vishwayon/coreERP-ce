<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\base;

use yii\web\Controller;
use yii\web\Request;
use yii\filters\VerbFilter;

class RestBoController extends Controller {
    //put your code here
    
    public $defaultAction = 'default';
    
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'fetch'  => ['get'],
                    'save' => ['post'],
                    'delete' => ['delete'],
                    'archive' => ['put'],
                ],
            ],
        ];
    }
    
    public function beforeAction($action) {
        // This Controller only returns JSON data
        \yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Basic Step: authenticated users only allowed
        $uInfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($uInfo->getAuthStatus() == false ) {
            throw new \yii\base\ExitException(0, 'User not authenticated');
        }

        // Step 1: Resolve HTTP VERB
        if(\Yii::$app->request->getMethod()=="POST") {
            $action->actionMethod="actionSave";
            $action->id="save";
        } elseif (\Yii::$app->request->getMethod()=="GET") {
            $action->actionMethod="actionFetch";
            $action->id="fetch";
        } elseif (\Yii::$app->request->getMethod()=="DELETE") {
            $action->actionMethod="actionDelete";
            $action->id="delete";
        } elseif (\Yii::$app->request->getMethod()=="PUT") {
            $action->actionMethod="actionArchive";
            $action->id="archive";
        }
        
        return true;
    }
    
    public function actionDefault() {
        return 'Found Nothing';
    }
    
    public function actionFetch($bo, $params, $formName='') {
        if (!isset($bo) || strlen($bo)==0) {
            throw new \yii\base\Exception('Missing Param: BO');
        }
        \yii::beginProfile('actionFetch');
        $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
        $helperOption->bo_id = $bo;
        $helperOption->modulePath = $this->getModulePath();
        $helperOption->moduleNamespace = $this->getModuleNamespace();
        $helperOption->inParam = json_decode($params, true);
        $helperOption->formName = $formName;
        
        $helper = new RestBoHelper();
        $result = $helper->actionFetch($helperOption);
        \yii::endProfile('actionFetch');
        
        // return json data
        return $result;
    }
    
    public function actionSave($bo, $params, $formName, $action='',$savewithwarnings=false) {
        if (!isset($bo) || strlen($bo)==0) {
            throw new \yii\base\Exception('Missing Param: BO');
        }
        \yii::beginProfile('actionSave');
        $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
        $helperOption->bo_id = $bo;
        $helperOption->modulePath = $this->getModulePath();
        $helperOption->moduleNamespace = $this->getModuleNamespace();
        $helperOption->inParam = json_decode($params, true);
        $helperOption->postData = json_decode(\Yii::$app->request->getRawBody());
        $helperOption->formName = $formName;
        $helperOption->action = $action;
        $helperOption->saveOnWarn = $savewithwarnings;
        
        $helper = new RestBoHelper();
        $result = $helper->actionSave($helperOption);
        \yii::endProfile('actionSave');
        
        // return json data
        return $result;
    }
    
    public function actionDelete($bo, $params, $formName){ 
        if (!isset($bo) || strlen($bo)==0) {
            throw new \yii\base\Exception('Missing Param: BO');
        }
        $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
        $helperOption->bo_id = $bo;
        $helperOption->modulePath = $this->getModulePath();
        $helperOption->moduleNamespace = $this->getModuleNamespace();
        $helperOption->inParam = json_decode($params, true);
        $helperOption->formName = $formName;
        
        $helper = new RestBoHelper();
        $result = $helper->actionDelete($helperOption);
        
        // return json data
        return $result;
    }
    
    public function actionArchive($bo, $params, $formName, $action, $msg){ 
        if (!isset($bo) || strlen($bo)==0) {
            throw new \yii\base\Exception('Missing Param: BO');
        }
        $helperOption = new \app\cwf\vsla\base\RestBoHelperOption();
        $helperOption->bo_id = $bo;
        $helperOption->modulePath = $this->getModulePath();
        $helperOption->moduleNamespace = $this->getModuleNamespace();
        $helperOption->inParam = json_decode($params, true);
        $helperOption->formName = $formName;
        
        $helper = new RestBoHelper();
        $result = $helper->actionArchive($helperOption, $action, $msg);
        
        // return json data
        return $result;
    }
    
    /**
     * Returns the full Module namespace that this controller belongs to
     * @return string
     */
    protected function getModuleNamespace() {
        return '\\'.$this->getModNs($this->module);
    }
    
    private function getModNs(\yii\base\Module $cmod) {
        $modname = '';
        if(isset($cmod->module)) {
            $modname .= $this->getModNs($cmod->module);
            
            $modname .= '\\'.$cmod->id;
        } else {
            $modname =  $cmod->id;
        }
        return $modname;
    }
    
    /**
     * Gets the physical path of the module with an alias @app. 
     * Use \yii::getAlias() to resolve complete physical path from root
     * @return string
     */
    protected function getModulePath() {
        return '@'.$this->getModPath($this->module);
    }
    
    private function getModPath(\yii\base\Module $cmod) {
        $modname = '';
        if(isset($cmod->module)) {
            $modname .= $this->getModPath($cmod->module);
            
            $modname .= '/'.$cmod->id;
        } else {
            $modname =  $cmod->id;
        }
        return $modname;
    }
}