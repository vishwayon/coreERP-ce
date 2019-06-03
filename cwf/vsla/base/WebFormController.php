<?php

namespace app\cwf\vsla\base;

use yii\web\Controller;

class WebFormController extends Controller {

    public function beforeAction($action) {
        // Basic Step: authenticated users only allowed
        $uInfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($uInfo->getAuthStatus() == false) {
            throw new \yii\base\ExitException(0, 'User not authenticated');
        } else {
            return parent::beforeAction($action);
        }
    }

    public function actionIndex($formName, $formParams) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $viewOption->params = $formParams;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->id, $design);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        $temp = json_decode($formParams, TRUE);
        if (\app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('company_id') != -1 &&
                $temp != NULL && is_array($temp) && count($temp) > 0) {
            $doc_id = reset($temp);
            if ($doc_id != -1 || $doc_id != '-1') {
                $bo_id = $design->id;
                \app\cwf\vsla\security\AccessManager::log_doc_view($doc_id, $bo_id);
            }
        }
        return $this->renderPartial('FormView', ['viewForRender' => $viewForRender]);
    }

    public function actionSummary($formName, $formParams) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $viewOption->params = $formParams;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->id, $design);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledSummaryView($viewOption, $design);
        $temp = json_decode($formParams, TRUE);
        if (\app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('company_id') != -1 &&
                $temp != NULL && is_array($temp) && count($temp) > 0) {
            $doc_id = reset($temp);
            if ($doc_id != -1 || $doc_id != '-1') {
                $bo_id = $design->id;
                \app\cwf\vsla\security\AccessManager::log_doc_view($doc_id, $bo_id);
            }
        }
        return $this->renderPartial('FormView', ['viewForRender' => $viewForRender]);
    }

    public function actionWizard($operation = NULL) {
        if (\Yii::$app->request->getIsPost()) { // Called by wizard step
            $formName = \Yii::$app->request->getBodyParam('formName');
            $step = \Yii::$app->request->getBodyParam('step');
            $oldFormParams = \Yii::$app->request->getBodyParam('oldStepData', []);
            $formParams = json_decode(\Yii::$app->request->getBodyParam('formdata'));
            $operation = \Yii::$app->request->getBodyParam('operation');
        } else { // First call from menu click
            $formName = \Yii::$app->request->get('formName');
            $step = \Yii::$app->request->get('step');
            $oldFormParams = []; // This is the first step hence empty array
            $formParams = json_decode(\Yii::$app->request->get('formdata'));
        }

        $viewXpath = \yii::getAlias($this->getModulePath() . DIRECTORY_SEPARATOR . $formName . '.xml');
        $viewX = simplexml_load_file($viewXpath);
        $wizparser = new \app\cwf\vsla\ui\wizardparser($viewX, $this->getModulePath(), $formParams, $step, $operation);
        $currdata = NULL;
        if ($operation == 'next') {
            if ($formParams) {
                $wizparser->processStepData($wizparser->currentStep, $formParams, $oldFormParams);
            }
            $wizparser->setSteps($wizparser->currentStep, $operation);
            if ($wizparser->codeBehind->status === 'OK') {
                if ($wizparser->xsteps[$wizparser->currentStep]->final) {
                    //$this->XmlViewPath=$wizparser->xsteps[$wizparser->currentStep]->path;
                    $wizStepData = $wizparser->stepData;
                    $wiz_cache_id = \app\cwf\vsla\render\WizardHelper::cacheWizData($wizStepData);
                    $finalPath = $wizparser->xsteps[$wizparser->currentStep]->finalPath;
                    $pathData = $wizparser->xsteps[$wizparser->currentStep]->newparams;
                    $pathData['wiz_cache_id'] = $wiz_cache_id;
                    return $this->actionIndex($finalPath, json_encode($pathData));
                }
            } else {
                $currdata = $formParams;
                $wizparser->currentStep = $wizparser->prevStep;
                $wizparser->setSteps($wizparser->currentStep, NULL);
            }
        } else if ($operation == 'prev') {
            $currdata = $oldFormParams;
            $wizparser->currentStep = $wizparser->prevStep;
            $wizparser->setSteps($wizparser->currentStep, NULL);
        } else {
            $wizparser->setSteps($step, NULL);
        }

        return $this->renderPartial('@app/cwf/vsla/base/WebWizardView.php', ['wizparser' => $wizparser, 'step' => $wizparser->currentStep,
                    'formName' => $formName, 'currentdata' => $currdata]);
    }

    public function actionCollection($formName) {
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
            $viewOption->firstStageAllowed = \app\cwf\vsla\security\AccessManager::allowFirstStage($design->bindingBO);
        }
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledCollectionView($viewOption, $design);
        return $this->renderPartial('CollectionView', ['viewForRender' => $viewForRender]);
    }

    public function actionPartialcollection($formName) {
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledCollectionView($viewOption, $design);
        return $this->renderPartial('CollectionView', ['viewForRender' => $viewForRender]);
    }

    public function actionTree($formName) {
        $viewXpath = \yii::getAlias($this->getModulePath() . DIRECTORY_SEPARATOR . $formName . '.xml');
        $viewX = simplexml_load_file($viewXpath);
        // Tree uses the old parser. Hence provide only simple module path (Temp code. Needs change when tree is upgraded)
        $smpath = str_replace('@app/', '', $this->getModulePath());
        $treeviewparser = new \app\cwf\vsla\ui\treeviewparser($viewX, $formName, $smpath, null);
        $treeviewrenderer = new \app\cwf\vsla\ui\treeviewrenderer($treeviewparser);
        return $this->renderPartial('@app/cwf/vsla/base/WebTreeView.php', ['treeviewrenderer' => $treeviewrenderer]);
    }

    public function actionCustom($className, $params) {
        $rndr = new $className($params);
        if (\Yii::$app->request->getIsPost()) {
            if ($rndr instanceof \app\cwf\vsla\xmlbo\CustomBase) {
                $rndr->save();
            }
        }
        return $this->renderPartial('@app/cwf/vsla/base/WebBasicView.php', ['renderer' => $rndr]);
    }

    /**
     * Action Method returns JSON data for Collection after applying 
     * all filters
     * @param string $formName    The name of the CollectionForm
     * @param string $filters     The filters to be applied to data (e.g: date, status, etc.) Should be comma separated
     * @return JSON               The JSON result set with {cols, def, data}.
     */
    public function actionFilterCollection($formName, $filters) {
        $filter_array = array();
        parse_str($filters, $filter_array);
        // Fix Date Formats When call is not from api
        if (!array_key_exists('forapi', $filter_array)) {
            if (array_key_exists('from_date', $filter_array)) {
                $filter_array['from_date'] = \app\cwf\vsla\utils\FormatHelper::GetDBDate($filter_array['from_date']);
            }
            if (array_key_exists('to_date', $filter_array)) {
                $filter_array['to_date'] = \app\cwf\vsla\utils\FormatHelper::GetDBDate($filter_array['to_date']);
            }
        }
        \yii::info($filter_array, 'inAction-filters');
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        $design->option = $viewOption;
        // Return Raw data from collection (already in JSON string). Yii will not interfere with this content
        // Hence, add a custom header for content type
        \yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        \yii::$app->response->getHeaders()->set('Content-Type', "application/json");
        return \app\cwf\vsla\render\CollectionHelper::getCollection($design, $filter_array);
    }

    public function actionGetCollData($formName, $filters) {
        $filter_array = array();
        parse_str($filters, $filter_array);
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        \yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        if ($design->option->accessLevel <= AccessLevels::NOACCESS) {
            \yii::$app->response->setStatusCode(401); // unauthorised access
            return ['fail-msg' => 'Requested data not available to logon user.'];
        }
    }

    public function getViewPath() {
        return $this->getModulePath() . '/views';
    }

    public function actionPopupview($alloc) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $alloc;
        $design = $this->getDesign($this->getModulePath(), $alloc);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledAllocView($viewOption, $design);
        $frm = $viewForRender->getForm();
        $frm = '<input id="pheader" name="pheader" type="hidden" value="' . $viewForRender->getHeader() . '"></title>' . $frm;
        return $frm;
    }

    protected function getDesign($modulePath, $viewPath) {
        return \app\cwf\vsla\xml\CwfXmlLoader::loadFile($modulePath, $viewPath);
    }

    protected function checkAccess($bo_id, &$design) {
        return \app\cwf\vsla\security\AccessManager::verifyAccess($bo_id);
    }

    /**
     * Returns the full Module namespace that this controller belongs to
     * @return string
     */
    protected function getModuleNamespace() {
        return '\\' . $this->getModNs($this->module);
    }

    private function getModNs(\yii\base\Module $cmod) {
        $modname = '';
        if (isset($cmod->module)) {
            $modname .= $this->getModNs($cmod->module);

            $modname .= '\\' . $cmod->id;
        } else {
            $modname = $cmod->id;
        }
        return $modname;
    }

    /**
     * Gets the physical path of the module with an alias @app. 
     * Use \yii::getAlias() to resolve complete physical path from root
     * @return string
     */
    protected function getModulePath() {
        return '@' . $this->getModPath($this->module);
    }

    private function getModPath(\yii\base\Module $cmod) {
        $modname = '';
        if (isset($cmod->module)) {
            $modname .= $this->getModPath($cmod->module);

            $modname .= '/' . $cmod->id;
        } else {
            $modname = $cmod->id;
        }
        return $modname;
    }

}
