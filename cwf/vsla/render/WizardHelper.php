<?php
namespace app\cwf\vsla\render;
/**
 * Description of WizardHelper
 *
 * @author dev
 */
use yii\helpers\Html;
use app\cwf\vsla\design;

class WizardHelper {
    public static function setSteps(design\WizardView &$wizView, $step, $operation){
        if($step!==NULL){
            if($operation==='next'){
                $wizView->prevStep = $step;
                $wizView->currentStep = self::setNextStep($wizView, $step);
                $wizView->nextStep = self::setNextStep($wizView, $wizView->currentStep);
            }elseif ($operation==='prev') {
                $wizView->nextStep = $step;
                $wizView->currentStep = self::setPrevStep($wizView, $step);
                $wizView->prevStep = self::setPrevStep($wizView, $wizView->currentStep);
            }else{
                $wizView->currentStep = $step;
                $wizView->nextStep = self::setNextStep($wizView, $step);
                $wizView->prevStep = self::setPrevStep($wizView, $step);
            }
        }
    }

    private static function setNextStep(design\WizardView $wizView, $step){
        for($i = 0; $i < count($wizView->xrootview->wizardStep); $i++) {
            if((string)$wizView->xrootview->wizardStep[$i]['id'] === $step) {
                if(($i+1) < count($wizView->xrootview->wizardStep)) {
                    return (string)$wizView->xrootview->wizardStep[$i+1]['id'];
                }
            }
        }
        return (string)$wizView->xrootview->postWizard['id'];
    }

    private static function setPrevStep(design\WizardView $wizView, $step){
        for($i = 0; $i < count($wizView->xrootview->wizardStep); $i++) {
            if((string)$wizView->xrootview->wizardStep[$i]['id'] === $step) {
                if(($i-1) >= 0) {
                    return (string)$wizView->xrootview->wizardStep[$i-1]['id'];
                }
            }
        }
    }
    
    public static function processStepData(design\WizardView &$wizView, $step, $data, $oldStepData){
        $wizView->codeBehind->setData($step,$data,$oldStepData);
        $wizView->stepData = $wizView->codeBehind->getData();
        $wizView->formParams = $wizView->codeBehind->getData();
    }

    public static function getParamValue(design\WizardView $wizView, $wizparam){
        $parent=  $wizView->stepData[(string)$wizparam['step']];
        $temp=(string)$wizparam;
        $res=null;
        if(gettype($parent) == 'array'){
            if(key_exists($temp, $parent)){
                $res=$parent[$temp];   
            }
        }
        else if(property_exists($temp, $parent)){
            $res=$parent->$temp;
        }
        return $res;
    }
    
    public static function getNewOptions($mpath){
        $cwFramework = simplexml_load_file($mpath);
        $boxml= $cwFramework->formView;
        $res=array();
        if(isset($boxml->keyField)){
            $res[(string)$boxml->keyField]=-1;
        }
        if(isset($boxml->newDocEnabled)){
            $res['doc_type']=  isset($boxml->newDocEnabled->docType)?
                    (string)$boxml->newDocEnabled->docType:'';
            foreach ($boxml->newDocEnabled->param as $param) {
                if((string)$param['name']==='DocType'){
                    $res['doc_type']=(string)$param;
                }else{
                    $res[(string)$param['name']]=(string)$param;
                }
            }
        }
        return $res;
    }
    
    public static function addButtons(design\WizardView $wizView){
        $res='<div class="form-group">';
        if($wizView->prevStep!==NULL){
            $res.= '<button id="cmdprev" class="btn btn-primary" style="font-size:12px;float:left;" 
                 name="wprev-button" data-bind="click:coreWebApp.wizPrev">
                 <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> Prev
                 </button>';
        }
        if($wizView->nextStep!==NULL){
            $res.= '<button id="cmdnext" class="btn btn-primary" style="font-size:12px;float:right;" 
                 name="wnext-button" data-bind="click:coreWebApp.wizNext">
                 <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> Next
                 </button>';
        }else{

        }
        $res.='</div>';
        return $res;
    }
    
    public static function wizHeader(design\WizardView $wizView) {
        $res = '<div id="collheader" class="row cformheader"><h3>'.$wizView->name.'</h3></div>';
        $res .= '<div><h4>'.$wizView->wizardStep[$wizView->currentStep]->header.'</h4></div>';
        return $res;
    }
    
    public static function wizData(design\WizardView &$wizardView) {
        $wStep = $wizardView->wizardStep[$wizardView->currentStep];
        foreach ($wStep->wizSection as $sec) {
            if($sec instanceof design\FormView) {
                $temp=array();
                foreach ($sec->fields as $fld) {
                    $temp[$fld->fieldName]= FormHelper::setDefaults($fld->viewField->type);
                }
                $wizardView->wizardStep[$wizardView->currentStep]->stepWizData[$sec->id] = $temp;
            } else if($sec instanceof design\CollectionDesignView) {
                $wizardView->wizardStep[$wizardView->currentStep]->stepWizData[$sec->id] = CollectionHelper::getData($sec, NULL);
            }
        }
    }
    
    /** Caches any serializable object and returns cache id. */
    public static function cacheWizData($wizData) {
        $wiz_cache_id = uniqid(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID().'_');
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Insert Into sys.wiz_cache(wiz_cache_id, wiz_data, last_updated) 
                Values(md5(:pwiz_cache_id)::uuid, :pwiz_data, current_timestamp(0))');
        $cmm->addParam('pwiz_cache_id', $wiz_cache_id);
        $cmm->addParam('pwiz_data', json_encode($wizData, JSON_HEX_APOS));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        return $wiz_cache_id;
    }
    
    /** REtreives the cached wizData and returns instance of object */
    public static function getCachedWizData($wiz_cache_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select wiz_data From sys.wiz_cache Where wiz_cache_id=md5(:pwiz_cache_id)::uuid');
        $cmm->addParam('pwiz_cache_id', $wiz_cache_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            $data = json_decode($dt->Rows()[0]['wiz_data'], true);
            return $data;
        }
        return [];
    }
    
}
