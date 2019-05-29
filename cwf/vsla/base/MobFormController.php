<?php

namespace app\cwf\vsla\base;

/**
 * Description of MobFormController
 *
 * @author dev
 */
class MobFormController extends WebFormController {

    //put your code here
    public function actionCollection($formName) {
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
            $viewOption->firstStageAllowed = \app\cwf\vsla\security\AccessManager::allowFirstStage($design->bindingBO);
        }
        $viewForRender = \app\cwf\vsla\render\MobViewManager::getCompiledCollectionView($viewOption, $design);
        return $this->renderPartial('MobCollectionView', ['viewForRender' => $viewForRender]);
    }

    public function actionIndex($formName, $formParams) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $viewOption->params = $formParams;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->id, $design);
        $viewForRender = \app\cwf\vsla\render\MobViewManager::getCompiledFormView($viewOption, $design);
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

    public function actionFilterCollection($formName, $filters = '') {
        $filter_array = array();
        parse_str($filters, $filter_array);
        $viewOption = new \app\cwf\vsla\render\CollectionViewOptions();
        $viewOption->callingModulePath = $this->getModulePath();
        $viewOption->xmlViewPath = $formName;
        $design = $this->getDesign($this->getModulePath(), $formName);
        $viewOption->accessLevel = $this->checkAccess($design->bindingBO, $design);
        return \app\cwf\vsla\render\MobViewManager::getCompiledCollectionDataView($viewOption, $filter_array, $design);
    }

}
