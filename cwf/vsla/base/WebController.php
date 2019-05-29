<?php

namespace app\cwf\vsla\base;

use yii\web\Controller;

class WebController extends Controller {
    
    protected $ModulePath = '';
    protected $XmlViewPath = '';
    
    
    public function init() {
        parent::init();
        $viewName = \yii::$app->request->get('viewName');
        $this->ModulePath = '../';
        if (isset($this->module->module)) {
            $this->ModulePath .=  $this->module->module->id;
        }
        $this->ModulePath .= $this->module->id;
        $this->XmlViewPath = $this->ModulePath.'/'.$viewName.'.xml';
    }
    
    public function beforeAction($action) {
        // Basic Step: authenticated users only allowed
        if (!\app\cwf\vsla\security\SessionManager::getAuthStatus()) {
            throw new \yii\base\ExitException(0, 'User not authenticated');
        } else {
            return parent::beforeAction($action);
        }
    }
    
    public function actionIndex($viewName, $viewParams) {
        $viewX = simplexml_load_file($this->XmlViewPath);
        $viewParser = new \app\cwf\vsla\ui\viewparser($viewX);
        //return $this->rend($this->ModulePath.'/views/MastView.php', ['viewParser' => $viewParser]);
        return $this->render('MastView', ['viewParser' => $viewParser] );  
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
