<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xml;

include_once '../cwf/vsla/design/CommonTypes.php';
include_once '../cwf/vsla/design/FormView.php';

use app\cwf\vsla\design\RelationType;

/**
 * This is a generic Xml loader that can be used to load any CwFramework xsd compliant xml
 * All methods in this class are written sequentially based on the xsd definition
 * Any new node included in xsd should be parsed sequentially in the parse methods
 *
 * @author girish
 */
class CwfXmlLoader {

    private static $loadedViews = array();

    /**
     * This method concatenates '../' + $modulePath + '/' + $formName + '.xml' to find and load the file
     * @param string $modulePath - The calling module path. As provided by the vsla\BaseControllers
     * @param string $formName - The FormName(xml file name) relative to the module path.
     * @return \app\cwf\vsla\design\CwFrameworkType
     */
    public static function loadFile($modulePath, $formName) {
        $xmlFilePath = '';
        if ($modulePath == '' && stripos($formName, '.xml') !== 0) {
            $xmlFilePath = \yii::getAlias($formName);
        } else {
            $xmlFilePath = \yii::getAlias($modulePath) . DIRECTORY_SEPARATOR . $formName . '.xml';
        }
        if (!isset(self::$loadedViews[$xmlFilePath])) {
            $cwfXml = simplexml_load_file($xmlFilePath);
            if (isset($cwfXml->formView)) {
                $formView = self::parseFormView($cwfXml->formView, $modulePath);
                self::$loadedViews[$xmlFilePath] = $formView;
                return $formView;
            } else if (isset($cwfXml->collectionView)) {
                $collectionView = self::parseCollectionView($cwfXml->collectionView, $modulePath);
                self::$loadedViews[$xmlFilePath] = $collectionView;
                return $collectionView;
            } else if (isset($cwfXml->allocView)) {
                $formView = self::parseAllocView($cwfXml->allocView, $modulePath);
                self::$loadedViews[$xmlFilePath] = $formView;
                return $formView;
            } else if (isset($cwfXml->reportView)) {
                $reportView = self::parseReportView($cwfXml->reportView, $modulePath);
                self::$loadedViews[$xmlFilePath] = $reportView;
                return $reportView;
            } else if (isset($cwfXml->datasetView)) {
                $datasetView = self::parseDatasetView($cwfXml->datasetView, $modulePath);
                self::$loadedViews[$xmlFilePath] = $datasetView;
                return $datasetView;
            }
        } else {
            return self::$loadedViews[$xmlFilePath];
        }
    }

    /**
     * Loads the xml collection view and returns collection design view
     * @param type $xmlFilePath
     * @return \app\cwf\vsla\design\CollectionDesignView
     */
    public static function loadFileCollectionView($xmlFilePath) {
        return self::loadFile($xmlFilePath);
    }

    private static function parseFormView(\SimpleXMLElement $xformView, $modulePath, $cwfType = \app\cwf\vsla\design\CwFrameworkType::FORM_VIEW) {
        $formView = NULL;
        switch ($cwfType) {
            case \app\cwf\vsla\design\CwFrameworkType::ALLOC_VIEW :
                $formView = new \app\cwf\vsla\design\AllocView();
                if (isset($xformView->attributes()->width)) {
                    $formView->width = (string) $xformView->attributes()->width;
                }
                break;
            case \app\cwf\vsla\design\CwFrameworkType::REPORT_VIEW :
                $formView = new \app\cwf\vsla\design\ReportView();
                break;
            case \app\cwf\vsla\design\CwFrameworkType::DATASET_VIEW :
                $formView = new \app\cwf\vsla\design\DatasetView();
                break;
            default :
                $formView = new \app\cwf\vsla\design\FormView();
        }

        // Get Form View Attributes
        $formView->id = (string) $xformView->attributes()->id;
        $formView->type = (string) $xformView->attributes()->type;
        $formView->modulePath = $modulePath;
        $formView->bindingBO = (string) $xformView->attributes()->bindingBO;
        $formView->helpLink = isset($xformView->attributes()->helpLink) ? (string) $xformView->attributes()->helpLink : '';
        $formView->exportView = isset($xformView->attributes()->exportView) ? (string) $xformView->attributes()->exportView : 'tcons';

        // Get simple elements
        $formView->header = (string) $xformView->header;
        $formView->keyField = (string) $xformView->keyField;
        if (isset($xformView->printView)) {
            $formView->printView = self::parseFormPrintView($xformView->printView, $modulePath, $formView->id);
        }
        if (isset($xformView->summaryView)) {
            $formView->summaryformName = $xformView->summaryView;
        }
        if (isset($xformView->newDocEnabled)) {
            $formView->newDocEnabled = true;
            $formView->newDocParam = self::parseNewDocParam($xformView->newDocEnabled);
        }
        if (isset($xformView->deleteDocEnabled)) {
            $formView->deleteDocEnabled = true;
        }
        if (isset($xformView->archiveEnabled)) {
            $formView->archiveEnabled = true;
        }
        if (isset($xformView->noRefreshOnClose)) {
            $formView->noRefreshOnClose = true;
        }
        if (isset($xformView->unpostDisabled)) {
            $formView->unpostDisabled = (bool) $xformView->unpostDisabled;
        }
        if (isset($xformView->clientJsCode)) {
            if ($formView->modulePath != '') {
                $formView->clientJsCode[] = $formView->modulePath . '/' . $xformView->clientJsCode;
            } else {
                $cjs = str_ireplace('../', '@app/', (string) $xformView->clientJsCode);
                $formView->clientJsCode[] = $cjs;
            }
        }
        if (isset($xformView->clientJsCodeRefs)) {
            foreach ($xformView->clientJsCodeRefs->clientJsCodeRef as $xclientJsCodeRef) {
                $clientJsCodeRef = str_ireplace('../', '@app/', (string) $xclientJsCodeRef);
                $formView->clientJsCode[] = $clientJsCodeRef;
                //array_push($formView->clientJsCode, $clientJsCodeRef);                
            }
        }
        if (isset($xformView->codeBehind)) {
            if (isset($xformView->codeBehind->className)) {
                $className = (string) $xformView->codeBehind->className;
                $formView->codeBehind = new $className();
            }
        }
        if (isset($xformView->jsEvents->afterLoadEvent)) {
            $formView->afterLoadEvent = (string) $xformView->jsEvents->afterLoadEvent;
        }
        if (isset($xformView->jsEvents->afterPostEvent)) {
            $formView->afterPostEvent = (string) $xformView->jsEvents->afterPostEvent;
        }
        if (isset($xformView->jsEvents->afterSaveEvent)) {
            $formView->afterSaveEvent = (string) $xformView->jsEvents->afterSaveEvent;
        }
        if (isset($xformView->jsEvents->afterUnpostEvent)) {
            $formView->afterUnpostEvent = (string) $xformView->jsEvents->afterUnpostEvent;
        }
        if (isset($xformView->jsEvents->afterRefreshEvent)) {
            $formView->afterRefreshEvent = (string) $xformView->jsEvents->afterRefreshEvent;
        }
        if (isset($xformView->jsEvents->beforeSaveEvent)) {
            $formView->beforeSaveEvent = (string) $xformView->jsEvents->beforeSaveEvent;
        }
        if (isset($xformView->jsEvents->beforeCloseEvent)) {
            $formView->beforeCloseEvent = (string) $xformView->jsEvents->beforeCloseEvent;
        }
        if (isset($xformView->dmFiles)) {
            $formView->dmFilesEnabled = true;
            $formView->dmFiles = self::parseDMFiles($xformView->dmFiles);
        }

        $formView->controlSection = new \app\cwf\vsla\design\FormControlSection();
        // create default edit mode. Default behaviour is allow edit
        $formView->controlSection->editMode = new \app\cwf\vsla\design\EditMode();
        $formView->controlSection->editMode->allowEdit = true;
        if (isset($xformView->controlSection->attributes()->editMode)) {
            if (strtolower((string) $xformView->controlSection->attributes()->editMode) != 'edit') {
                $formView->controlSection->editMode->allowEdit = false;
            }
        }
        $xExtnFields = self::parseExtendedView($formView->id);
        $xDataBinding = $xformView->controlSection->dataBinding;
        if (isset($xExtnFields)) {
            $xDataBinding->addChild('nextRow');
            foreach ($xExtnFields->children() as $xfld) {
                self::xml_adopt($xDataBinding, $xfld);
            }
        }
        $formView->controlSection->dataBinding = self::parseFormDataBinding($xformView->controlSection->dataBinding, $formView);
        return $formView;
    }

    private static function parseAllocView(\SimpleXMLElement $xformView, $modulePath) {
        $allocView = self::parseFormView($xformView, $modulePath, \app\cwf\vsla\design\CwFrameworkType::ALLOC_VIEW);
        return $allocView;
    }

    private static function parseReportView(\SimpleXMLElement $xreportView, $modulePath) {
        $reportView = self::parseFormView($xreportView, $modulePath, \app\cwf\vsla\design\CwFrameworkType::REPORT_VIEW);
        return $reportView;
    }
    
    private static function parseDatasetView(\SimpleXMLElement $xdatasetView, $modulePath) {
        $datasetView = self::parseFormView($xdatasetView, $modulePath, \app\cwf\vsla\design\CwFrameworkType::DATASET_VIEW);
        $datasetView->description = (string) $xdatasetView->description;
        return $datasetView;
    }

    private static function parseFormPrintView(\SimpleXMLElement $xprintView, $modulePath, $bo_id) {
        $printView = new \app\cwf\vsla\design\FormPrintView();
        $printView->rptOption = $modulePath . '/' . (string) $xprintView->attributes()->rptOption . '.xml';
        if (isset($xprintView->attributes()->rptEngine)) {
            $printView->rptEngine = (string) $xprintView->attributes()->rptEngine;
        }
        foreach ($xprintView->rptParams->children() as $nodeName => $nodeDef) {
            $id = (string) $nodeDef->attributes()->id;
            $printView->rptParams[$id] = (string) $nodeDef;
        }
        $printView->printOptions[$printView->rptOption] = 'Default';
        $export_options = \app\cwf\vsla\security\AccessManager::check_export_options($bo_id);
        if (!is_null($export_options)) {
            foreach ($printView->exportOptions as $key => $value) {
                if (!in_array($key, $export_options)) {
                    unset($printView->exportOptions[$key]);
                }
            }
        }
        if (isset($xprintView->printOptions)) {
            foreach ($xprintView->printOptions->children() as $propt) {
                $desc = '';
                if (isset($propt->attributes()->desc)) {
                    $desc = (string) $propt->attributes()->desc;
                }
                $rptopt = '';
                if (isset($propt->attributes()->rptOption)) {
                    $rptopt = $modulePath . '/' . (string) $propt->attributes()->rptOption . '.xml';
                }
                if ($desc != '' && $rptopt != '') {
                    $printView->printOptions[$rptopt] = $desc;
                }
            }
        }
        return $printView;
    }

    private static function parseNewDocParam(\SimpleXMLElement $xnewDocEnabled) {
        $newDocParam = new \app\cwf\vsla\design\NewDocParam();
        if (isset($xnewDocEnabled->docType)) {
            $newDocParam->docType = (string) $xnewDocEnabled->docType;
        }
        if (isset($xnewDocEnabled->beforeNewEvent)) {
            $newDocParam->beforeNewEvent = (string) $xnewDocEnabled->beforeNewEvent;
        }
        if (isset($xnewDocEnabled->afterNewEvent)) {
            $newDocParam->afterNewEvent = (string) $xnewDocEnabled->afterNewEvent;
        }
        if (isset($xnewDocEnabled->attributes()->wizard)) {
            $newDocParam->wizardPath = (string) $xnewDocEnabled->attributes()->wizard;
        }
        if (isset($xnewDocEnabled->attributes()->step)) {
            $newDocParam->wizardStep = (string) $xnewDocEnabled->attributes()->step;
        }
        return $newDocParam;
    }

    private static function parseDMFiles(\SimpleXMLElement $xdmFiles) {
        $dmFiles = new \app\cwf\vsla\design\DMFiles();
        if (isset($xdmFiles->attributes()->multipleFiles)) {
            if (strtolower((string) $xdmFiles->attributes()->multipleFiles) == 'true') {
                $dmFiles->multipleFiles = true;
            }
        }
        return $dmFiles;
    }

    private static function parseFormDataBinding(\SimpleXMLElement $xdataBinding, \app\cwf\vsla\design\CwFrameworkType $formView = NULL) {
        $dataBinding = new \app\cwf\vsla\design\FormDataBinding();
        $dataBinding->dataProperty = (string) $xdataBinding->attributes()->dataProperty;
        if (isset($xdataBinding->attributes()->bindMethod)) {
            $dataBinding->bindMethod = (string) $xdataBinding->attributes()->bindMethod;
        }
        if (isset($xdataBinding->attributes()->crudOn)) {
            $dataBinding->crudOn = (string) $xdataBinding->attributes()->crudOn;
        }
        if (isset($xdataBinding->attributes()->addFirst)) {
            $dataBinding->addFirst = 'true';
        }
        foreach ($xdataBinding->children() as $nodeName => $nodeDef) {
            switch ($nodeName) {
                case 'field':
                    $dataBinding->items[] = self::parseFormField($nodeDef);
                    break;
                case 'displayField':
                    $dataBinding->items[] = self::parseDisplayField($nodeDef);
                    break;
                case 'sectionHeader':
                    $dataBinding->items[] = self::parseFormSectionHeader($nodeDef);
                    break;
                case 'nextRow':
                    $dataBinding->items[] = self::parseFormNextRow($nodeDef);
                    break;
                case 'dummy':
                    $dataBinding->items[] = self::parseFormDummy($nodeDef);
                    break;
                case 'tranSection':
                    $dataBinding->items[] = self::parseFormTranSection($nodeDef);
                    break;
                case 'cLink':
                    $dataBinding->items[] = self::parseFormCLink($nodeDef);
                    break;
                case 'cButton':
                    $dataBinding->items[] = self::parseFormCButton($nodeDef);
                    break;
                case 'addRowEvent':
                    $dataBinding->addRowEvent = (string) $nodeDef;
                    break;
                case 'customField':
                    $dataBinding->items[] = self::parseFormField($nodeDef, true);
                    break;
                case 'cHtml':
                    $dataBinding->items[] = self::parseCHtml($nodeDef);
                    break;
                case 'xdiv':
                case 'xdivEnd':
                case 'xtab':
                case 'xtabEnd':
                case 'xtabPage':
                case 'xtabPageEnd':
                    $dataBinding->items[] = self::parseIElement($nodeDef, $dataBinding->items);
                    break;
                case 'callMethod':
                    $method = self::parseIElement($nodeDef, $dataBinding->items);
                    $methodname = $method->methodName;
                    $methodclass = $formView->codeBehind;
                    if (method_exists($methodclass, $methodname)) {
                        $method->methodOutput = $methodclass->$methodname();
                    }
                    $dataBinding->items[] = $method;
                    break;
            }
        }
        return $dataBinding;
    }

    private static function parseIDataBindingItems($formView, $pnode) {
        $items = [];
        foreach ($pnode->children() as $nodeName => $nodeDef) {
            switch ($nodeName) {
                case 'field':
                    $items[] = self::parseFormField($nodeDef);
                    break;
                case 'displayField':
                    $items[] = self::parseDisplayField($nodeDef);
                    break;
                case 'sectionHeader':
                    $items[] = self::parseFormSectionHeader($nodeDef);
                    break;
                case 'nextRow':
                    $items[] = self::parseFormNextRow($nodeDef);
                    break;
                case 'dummy':
                    $items[] = self::parseFormDummy($nodeDef);
                    break;
                case 'tranSection':
                    $items[] = self::parseFormTranSection($nodeDef);
                    break;
                case 'cLink':
                    $items[] = self::parseFormCLink($nodeDef);
                    break;
                case 'cButton':
                    $items[] = self::parseFormCButton($nodeDef);
                    break;
                case 'addRowEvent':
                    $addRowEvent = (string) $nodeDef;
                    break;
                case 'customField':
                    $items[] = self::parseFormField($nodeDef, true);
                    break;
                case 'cHtml':
                    $items[] = self::parseCHtml($nodeDef);
                    break;
                case 'xdiv':
                case 'xdivEnd':
                case 'xtab':
                case 'xtabEnd':
                case 'xtabPage':
                case 'xtabPageEnd':
                    $items[] = self::parseIElement($nodeDef, $items);
                    break;
                case 'callMethod':
                    $method = self::parseIElement($nodeDef, $items);
                    $methodname = $method->methodName;
                    $methodclass = $formView->codeBehind;
                    if (method_exists($methodclass, $methodname)) {
                        $method->methodOutput = $methodclass->$methodname();
                    }
                    $items[] = $method;
                    break;
            }
        }
        return $items;
    }

    private static function parseIElement(\SimpleXMLElement $xdiv, $loadedItems) {
        $div;
        $fattrs = $xdiv->attributes();

        switch ($xdiv->getName()) {
            case 'xdiv':
                $div = new \app\cwf\vsla\design\Xdiv();
                break;
            case 'xdivEnd':
                $div = new \app\cwf\vsla\design\XdivEnd();
                break;
            case 'xtab':
                $div = new \app\cwf\vsla\design\Xtab();
                break;
            case 'xtabEnd':
                $div = new \app\cwf\vsla\design\XtabEnd();
                break;
            case 'xtabPage':
                $div = new \app\cwf\vsla\design\XtabPage();
                break;
            case 'xtabPageEnd':
                $div = new \app\cwf\vsla\design\XtabPageEnd();
                break;
            case 'callMethod':
                $div = new \app\cwf\vsla\design\CallMethod();
                break;
            default:
                $div = new \app\cwf\vsla\design\Xdiv();
                break;
        }

        if (isset($fattrs->id)) {
            $div->id = (string) $fattrs->id;
        }
        if (isset($fattrs->size)) {
            $div->size = (string) $fattrs->size;
        }
        if (isset($fattrs->class)) {
            $div->class = (string) $fattrs->class;
        }
        if (isset($fattrs->style)) {
            $div->style = (string) $fattrs->style;
        }
        if (isset($fattrs->colspan)) {
            $div->colspan = intval((string) $fattrs->colspan);
        }
        if ($div instanceof \app\cwf\vsla\design\Xdiv) {
            if (isset($fattrs['cdata-visible-on'])) {
                $div->cdata_visible_on = (string) $fattrs['cdata-visible-on'];
            }
            if (isset($fattrs['cdata-bind'])) {
                $div->cdata_bind = (string) $fattrs['cdata-bind'];
            }
        }
        if ($div instanceof \app\cwf\vsla\design\Xtab) {
            if (isset($fattrs['cdata-visible-on'])) {
                $div->cdata_visible_on = (string) $fattrs['cdata-visible-on'];
            }
            if (isset($fattrs['cdata-bind'])) {
                $div->cdata_bind = (string) $fattrs['cdata-bind'];
            }
        }
        if ($div instanceof \app\cwf\vsla\design\XtabPage) {
            if (isset($fattrs->tabid)) {
                $div->tabid = (string) $fattrs->tabid;
            }
            if (isset($fattrs->onClick)) {
                $div->onClick = (string) $fattrs->onClick;
            }
            if (isset($fattrs->label)) {
                $div->label = (string) $fattrs->label;
            } else {
                $div->label = $div->id;
            }
            if (isset($fattrs['cdata-visible-on'])) {
                $div->cdata_visible_on = (string) $fattrs['cdata-visible-on'];
            }
            if (isset($fattrs['cdata-bind'])) {
                $div->cdata_bind = (string) $fattrs['cdata-bind'];
            }
            $found = false;
            foreach ($loadedItems as $loadedItem) {
                if ($loadedItem instanceof \app\cwf\vsla\design\XtabPage) {
                    if ($div->tabid == $loadedItem->tabid) {
                        $found = true;
                    }
                }
            }
            if (!$found) {
                $div->isFirst = true;
            }
        }
        if ($div instanceof \app\cwf\vsla\design\CallMethod) {
            $div->methodName = (string) $fattrs->methodName;
        }
        return $div;
    }

    private static function parseFormField(\SimpleXMLElement $xfield, $isCustomField = false) {
        $field = new \app\cwf\vsla\design\FormField();
        if ($isCustomField) {
            $field = new \app\cwf\vsla\design\CustomFormField();
        }
        $fattrs = $xfield->attributes();
        // Compulsory Attributes
        $field->id = (string) $fattrs->id;
        $field->label = (string) $fattrs->label;
        $field->type = (string) $fattrs->type;
        $field->control = (string) $fattrs->control;

        // Optional Attributes start from here
        if (isset($fattrs->tranLabel)) {
            $field->tranLabel = (string) $fattrs->tranLabel;
        }
        if (isset($fattrs->size)) {
            $field->size = (string) $fattrs->size;
        }
        if (isset($fattrs->class)) {
            $field->class = (string) $fattrs->class;
        }
        if (isset($fattrs->style)) {
            $field->style = (string) $fattrs->style;
        }
        if (isset($fattrs->range)) {
            $field->range = (string) $fattrs->range;
        }
        if (isset($fattrs->maxLength)) {
            $field->maxLength = intval((string) $fattrs->maxLength);
        }
        if (isset($fattrs->isOptional)) {
            if (strtolower((string) $fattrs->isOptional) == 'true') {
                $field->isOptional = true;
            }
        }
        if (isset($fattrs->readOnly)) {
            if (strtolower((string) $fattrs->readOnly) == 'true') {
                $field->readOnly = 'readonly';
            } elseif (strtolower((string) $fattrs->readOnly) == 'onedit') {
                $field->readOnly = 'onEdit';
            }
        }
        if (isset($fattrs->scale)) {
            $field->scale = (string) $fattrs->scale;
        }

        if (isset($fattrs->allowNegative)) {
            $field->allowNegative = (bool) strtolower((string) $fattrs->allowNegative);
        }

        if (isset($fattrs->maxVal)) {
            $field->maxVal = (string) $fattrs->maxVal;
        }

        if (isset($fattrs->colspan)) {
            $field->colspan = (string) $fattrs->colspan;
        }

        if (isset($fattrs->forStatus)) {
            if (strtolower((string) $fattrs->forStatus) == 'true') {
                $field->forStatus = true;
            }
        }

        if (isset($fattrs['cdata-enable-on'])) {
            $field->cdata_enable_on = (string) $fattrs['cdata-enable-on'];
        }
        if (isset($fattrs['cdata-visible-on'])) {
            $field->cdata_visible_on = (string) $fattrs['cdata-visible-on'];
        }
        if (isset($fattrs->rows)) {
            $field->rows = intval((string) $fattrs->rows);
        }
        if (isset($fattrs->smartText)) {
            $field->smartText = (string) $fattrs->smartText;
        }
        if (isset($fattrs['cdata-bind'])) {
            $field->cdata_bind = (string) $fattrs['cdata-bind'];
        }
        if (isset($fattrs['cell-data-bind'])) {
            $field->cell_data_bind = (string) $fattrs['cell-data-bind'];
        }
        if (isset($fattrs['header-data-bind'])) {
            $field->header_data_bind = (string) $fattrs['header-data-bind'];
        }
        if (isset($fattrs['data-fc-dependent'])) {
            $field->data_fc_dependent = (string) $fattrs['data-fc-dependent'];
        }
        if (isset($fattrs['mdata-event'])) {
            $field->mdata_events = (string) $fattrs['mdata-event'];
        }
        if (isset($fattrs['exchDisable'])) {
            $field->exchDisable = (bool) (strtolower((string) $fattrs->exchDisable));
        }
        if (isset($fattrs['inline'])) {
            $field->inline = TRUE;
        }

        if (isset($fattrs['placeholder'])) {
            $field->placeholder = (string) $fattrs['placeholder'];
            ;
        }
        // Field Extenders start from here
        if (isset($xfield->value)) {
            $field->value = self::parseReportParam($xfield->value);
        }

        if (isset($xfield->lookup)) {
            $field->lookup = self::parseFieldLookup($xfield->lookup);
        } elseif (isset($xfield->options)) {
            $field->options = self::parseFieldOptions($xfield->options);
        }

        if (isset($xfield->multiple)) {
            $field->multiple = (bool) (strtolower($xfield->multiple));
        }

        if (isset($xfield->computedField)) {
            if (isset($fattrs->forceCalOnPost)) {
                $field->forceCalOnPost = (bool) (strtolower((string) $fattrs->forceCalOnPost));
            }
            $field->computedField = (string) $xfield->computedField;
//            // used for smart listbox
//            if(isset($xfield->lookup)) {
//                $field->lookup = self::parseFieldLookup($xfield->lookup);
//            }
        }

        if (isset($fattrs->fwdAction)) {
            $field->fwdAction = (string) $fattrs->fwdAction;
        }
        if (isset($fattrs->revAction)) {
            $field->revAction = (string) $fattrs->revAction;
        }

        if (isset($fattrs->forConsolidated)) {
            if ((bool) (strtolower((string) $fattrs->forConsolidated))) {
                $field->forConsolidated = TRUE;
            }
        }

        if (isset($fattrs->toggleOn)) {
            $field->toggleOn = (string) $fattrs->toggleOn;
        }

        if (isset($fattrs->toggleOff)) {
            $field->toggleOff = (string) $fattrs->toggleOff;
        }
        if (isset($fattrs['select-all-event'])) {
            $field->select_all_event = (string) $fattrs['select-all-event'];
        }
        if (isset($fattrs['on-change-event'])) {
            $field->on_change_event = (string) $fattrs['on-change-event'];
        }
        if (isset($fattrs['noRender'])) {
            $field->noRender = filter_var((string) $fattrs['noRender'], FILTER_VALIDATE_BOOLEAN);
        }

        return $field;
    }

    private static function parseFormSectionHeader(\SimpleXMLElement $xSectionHeader) {
        $sectionHeader = new \app\cwf\vsla\design\sectionHeader();
        if (isset($xSectionHeader->attributes()->label)) {
            $sectionHeader->label = (string) $xSectionHeader->attributes()->label;
        }
        return $sectionHeader;
    }

    private static function parseFormNextRow(\SimpleXMLElement $xNextRow) {
        $nextRow = new \app\cwf\vsla\design\NextRow();
        if (isset($xNextRow->attributes()->style)) {
            $nextRow->style = (string) $xNextRow->attributes()->style;
        }
        return $nextRow;
    }

    private static function parseFormDummy(\SimpleXMLElement $xDummy) {
        $dummy = new \app\cwf\vsla\design\Dummy();
        if (isset($xDummy->attributes()->style)) {
            $dummy->style = (string) $xDummy->attributes()->style;
        }
        if (isset($xDummy->attributes()->size)) {
            $dummy->size = (string) $xDummy->attributes()->size;
        }
        return $dummy;
    }

    private static function parseFormCLink(\SimpleXMLElement $xcLink) {
        $clink = new \app\cwf\vsla\design\CLink();
        $clink->id = (string) $xcLink->attributes()->id;
        $clink->onClick = (string) $xcLink->attributes()->onClick;
        $clink->label = (string) $xcLink->attributes()->label;
        if (isset($xcLink->attributes()->size)) {
            $clink->size = (string) $xcLink->attributes()->size;
        }
        if (isset($xcLink->attributes()->style)) {
            $clink->style = (string) $xcLink->attributes()->style;
        }
        /* $fattrs = $xcLink->attributes();
          if(isset($fattrs['cdata-enable-on'])) {
          $xcLink->cdata_enable_on = (string)$fattrs['cdata-enable-on'];
          }
          if(isset($fattrs['cdata-visible-on'])) {
          $xcLink->cdata_visible_on = (string)$fattrs['cdata-visible-on'];
          } */
        return $clink;
    }

    private static function parseFormCButton(\SimpleXMLElement $xcButton) {
        $cButton = new \app\cwf\vsla\design\CButton();
        $cButton->id = (string) $xcButton->attributes()->id;
        $cButton->onClick = (string) $xcButton->attributes()->onClick;
        $cButton->label = (string) $xcButton->attributes()->label;
        if (isset($xcButton->attributes()->size)) {
            $cButton->size = (string) $xcButton->attributes()->size;
        }
        $fattrs = $xcButton->attributes();
        if (isset($fattrs['cdata-bind'])) {
            $cButton->cdata_bind = (string) $fattrs['cdata-bind'];
        }
        if (isset($fattrs['cdata-enable-on'])) {
            $cButton->cdata_enable_on = (string) $fattrs['cdata-enable-on'];
        }
        if (isset($fattrs['cdata-visible-on'])) {
            $cButton->cdata_visible_on = (string) $fattrs['cdata-visible-on'];
        }
        if (isset($fattrs['inline'])) {
            $cButton->inline = strtolower($fattrs['inline']) === 'true' ? true : false;
        }
        if (isset($fattrs['icon'])) {
            $cButton->icon = (string) $fattrs['icon'];
        }
        if (isset($fattrs['tooltip'])) {
            $cButton->tooltip = (string) $fattrs['tooltip'];
        }
        if (isset($fattrs['nolabel'])) {
            $cButton->nolabel = strtolower($fattrs['nolabel']) === 'true' ? true : false;
        }
        if (isset($fattrs['has-header'])) {
            $cButton->hasHeader = strtolower($fattrs['has-header']) === 'true' ? true : false;
        }
        if (isset($fattrs['ignore-edit'])) {
            $cButton->ignoreEdit = strtolower($fattrs['ignore-edit']) === 'true' ? true : false;
        }
        return $cButton;
    }

    private static function parseCHtml(\SimpleXMLElement $xcHtml) {
        $cHtml = new \app\cwf\vsla\design\CHtml();
        $cHtml->html = $xcHtml;
        return $cHtml;
    }

    private static function parseFormTranSection(\SimpleXMLElement $xtranSection) {
        $tranSection = new \app\cwf\vsla\design\FormTranSection();
        $tranSection->label = (string) $xtranSection->attributes()->label;
        $tranSection->editMode = new \app\cwf\vsla\design\EditMode();
        if (isset($xtranSection->attributes()->editMode)) {
            $evals = (string) $xtranSection->attributes()->editMode;
            if (strpos($evals, 'Add') !== FALSE) {
                $tranSection->editMode->allowAdd = true;
            }
            if (strpos($evals, 'Edit') !== FALSE) {
                $tranSection->editMode->allowEdit = true;
            }
            if (strpos($evals, 'Delete') !== FALSE) {
                $tranSection->editMode->allowDelete = true;
            }
        }
        if (isset($xtranSection->attributes()->editMethod)) {
            $tranSection->editMethod = (string) $xtranSection->attributes()->editMethod;
        }
        if (isset($xtranSection->attributes()->beforeAddMethod)) {
            $tranSection->beforeAddMethod = (string) $xtranSection->attributes()->beforeAddMethod;
        }
        if (isset($xtranSection->attributes()->beforeDeleteMethod)) {
            $tranSection->beforeDeleteMethod = (string) $xtranSection->attributes()->beforeDeleteMethod;
        }
        if (isset($xtranSection->attributes()->afterDeleteMethod)) {
            $tranSection->afterDeleteMethod = (string) $xtranSection->attributes()->afterDeleteMethod;
        }
        if (isset($xtranSection->attributes()->fixedWidth)) {
            $tranSection->fixedWidth = (int) $xtranSection->attributes()->fixedWidth;
            // tw is applicable only when fixedwidth exists
            // load if found, else fallback to fixedwidth
            if (isset($xtranSection->attributes()->tw)) {
                $tranSection->tw = (int) $xtranSection->attributes()->tw;
            } else {
                $tranSection->tw = (int) $xtranSection->attributes()->fixedWidth;
            }
        }
        if (isset($xtranSection->attributes()->fixedHeight)) {
            $tranSection->fixedHeight = (int) $xtranSection->attributes()->fixedHeight;
        }
        if (isset($xtranSection->attributes()->size)) {
            $tranSection->size = (int) $xtranSection->attributes()->size;
        }
        if (isset($xtranSection->attributes()->noColHeader)) {
            $tranSection->noColHeader = TRUE;
        }

        $sattrs = $xtranSection->attributes();
        // set data relation for tran section
        if (isset($xtranSection->attributes()->dataRelation)) {
            $tranSection->dataRelation = (string) $xtranSection->attributes()->dataRelation;
        }
        if (isset($sattrs['cdata-enable-on'])) {
            $tranSection->cdata_enable_on = (string) $sattrs['cdata-enable-on'];
        }
        if (isset($sattrs['cdata-visible-on'])) {
            $tranSection->cdata_visible_on = (string) $sattrs['cdata-visible-on'];
        }
        if (isset($sattrs['mdata-event'])) {
            $tranSection->mdata_events = (string) $sattrs['mdata-event'];
        }
        if (isset($sattrs['noRender'])) {
            $tranSection->noRender = filter_var((string) $sattrs['noRender'], FILTER_VALIDATE_BOOLEAN);
        }
        $tranSection->dataBinding = self::parseFormDataBinding($xtranSection->dataBinding);
        return $tranSection;
    }

    private static function parseReportParam(\SimpleXMLElement $xrptParam) {
        $rptParam;
        if (isset($xrptParam->session)) {
            $rptParam = new \app\cwf\vsla\design\BaseParamSession();
            $rptParam->id = (string) $xrptParam->attributes()->id;
            $rptParam->sessionType = (string) $xrptParam->session;
        } elseif (isset($xrptParam->text)) {
            $rptParam = new \app\cwf\vsla\design\BaseParamText();
            $rptParam->id = (string) $xrptParam->attributes()->id;
            $rptParam->text = (string) $xrptParam->text;
        } elseif (isset($xrptParam->currentDate)) {
            $rptParam = new \app\cwf\vsla\design\BaseParamCurrentDate();
            $rptParam->id = (string) $xrptParam->attributes()->id;
            if(isset($xrptParam->currentDate->attributes()->offsetMonth)) {
                if(\app\cwf\vsla\utils\ValidationHelper::validateDuration((string)($xrptParam->currentDate->attributes()->offsetMonth))) {
                    $rptParam->offsetMonth = (string)($xrptParam->currentDate->attributes()->offsetMonth);
                } else {
                    $rptParam->offsetMonth = 'P1M';
                }
            } elseif (isset($xrptParam->currentDate->attributes()->offsetDate)) {
                if(\app\cwf\vsla\utils\ValidationHelper::validateDuration((string)($xrptParam->currentDate->attributes()->offsetDate))) {
                    $rptParam->offsetDate = (string)($xrptParam->currentDate->attributes()->offsetDate);
                } else {
                    $rptParam->offsetDate = 'P1M';
                }
            }
        } elseif (isset($xrptParam->preset)) {
            $rptParam = new \app\cwf\vsla\design\ReportParamPreset();
            $rptParam->id = (string) $xrptParam->attributes()->id;
        } elseif (isset($xrptParam->dateFormat)) {
            $rptParam = new \app\cwf\vsla\design\ReportParamDateFormat();
            $rptParam->id = (string) $xrptParam->attributes()->id;
        } elseif (isset($rptParam->numberFormat)) {
            $rptParam = new \app\cwf\vsla\design\ReportParamNumberFormat();
            $rptParam->id = (string) $xrptParam->attributes()->id;
        }
        if (!isset($rptParam)) {
            throw new \Exception('Unknown rptParamType supplied for node ' . (string) $xrptParam);
        }
        return $rptParam;
    }

    private static function parseFieldLookup(\SimpleXMLElement $xlookup) {
        $lookup = new \app\cwf\vsla\design\FieldLookupType();
        $lookup->valueMember = (string) $xlookup->valueMember;
        $lookup->displayMember = (string) $xlookup->displayMember;
        $lookup->namedLookup = (string) $xlookup->namedLookup;
        if (isset($xlookup->filter)) {
            $lookup->filter = (string) $xlookup->filter;
        }
        if (isset($xlookup->filterEvent)) {
            $lookup->filterEvent = (string) $xlookup->filterEvent;
        }
        return $lookup;
    }

    private static function parseFieldOptions(\SimpleXMLElement $xoptions) {
        $options = new \app\cwf\vsla\design\FieldOptionType();
        if (isset($xoptions->attributes()->defaultValue)) {
            $options->defaultValue = $xoptions->attributes()->defaultValue;
        }
        $options->choices[-1] = 'Select an option';
        foreach ($xoptions->children() as $nodeName => $nodeDef) {
            $value = (string) $nodeDef->attributes()->value;
            $options->choices[$value] = (string) $nodeDef;
        }
        return $options;
    }

    private static function parseCollectionView(\SimpleXMLElement $xcollectionView, $modulePath) {
        $collectionView = new \app\cwf\vsla\design\CollectionDesignView();
        // parse Attributes
        $collectionView->id = (string) $xcollectionView->attributes()->id;
        $collectionView->type = (string) $xcollectionView->attributes()->type;
        $collectionView->bindingBO = (string) $xcollectionView->attributes()->bindingBO;
        $collectionView->editView = (string) $xcollectionView->attributes()->editView;
        // If the Edit view exists, then load newDocEnabled
        if ($collectionView->editView != '') {
            $xeditForm = simplexml_load_file(\yii::getAlias($modulePath) . DIRECTORY_SEPARATOR . $collectionView->editView . '.xml');
            if (isset($xeditForm->formView->newDocEnabled)) {
                $collectionView->newDocEnabled = true;
                $collectionView->newDocParam = self::parseNewDocParam($xeditForm->formView->newDocEnabled);
            }
            if (isset($xeditForm->formView->keyField)) {
                $collectionView->keyField = (string) $xeditForm->formView->keyField;
            }
            if (isset($xeditForm->formView->jsEvents->afterLoadEvent)) {
                $collectionView->afterLoadEvent = (string) $xeditForm->formView->jsEvents->afterLoadEvent;
            }
        }

        // parse elements
        $collectionView->header = (string) $xcollectionView->header;
        if (isset($xcollectionView->ovrride)) {
            $collectionView->ovrrideClass = (string) $xcollectionView->ovrride->attributes()->className;
            $collectionView->ovrrideMethod = (string) $xcollectionView->ovrride->attributes()->method;
        }
        if (isset($xcollectionView->filter)) {
            $collectionView->filter = self::parseIDataBindingItems($collectionView, $xcollectionView->filter);
        }
        if (isset($xcollectionView->clientJsCode)) {
            if ($modulePath != '') {
                $collectionView->clientJsCode[] = $modulePath . '/' . $xcollectionView->clientJsCode;
            } else {
                $cjs = str_ireplace('../', '@app/', (string) $xcollectionView->clientJsCode);
                $collectionView->clientJsCode[] = $cjs;
            }
        }
        if (isset($xcollectionView->clientJsCodeRefs)) {
            foreach ($xcollectionView->clientJsCodeRefs->clientJsCodeRef as $xclientJsCodeRef) {
                $clientJsCodeRef = str_ireplace('../', '@app/', (string) $xclientJsCodeRef);
                $collectionView->clientJsCode[] = $clientJsCodeRef;
            }
        }
        $collectionView->collectionSection = self::parseCollectionSection($xcollectionView->collectionSection, $modulePath);
        return $collectionView;
    }

    private static function parseCollectionSection(\SimpleXMLElement $xcollectionSection, $modulePath) {
        $collectionSection = new \app\cwf\vsla\design\CollectionSection();
        if (isset($xcollectionSection->connectionType->companyDB)) {
            $collectionSection->connectionType = \app\cwf\vsla\data\DataConnect::COMPANY_DB;
        } elseif (isset($xcollectionSection->connectionType->mainDB)) {
            $collectionSection->connectionType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
        } else {
            throw new \Exception("Invalid Connection Type mentioned for Collection Section");
        }
        $collectionSection->sql = self::parseSqlCommand($xcollectionSection->sql);
        foreach ($xcollectionSection->displayFields->children() as $nodeName => $nodeDef) {
            $collectionSection->displayFields[] = self::parseDisplayField($nodeDef);
        }
        if (isset($xcollectionSection->detailView)) {
            $collectionSection->detailView = self::parseDetailView($xcollectionSection->detailView, $modulePath);
        }
        if (isset($xcollectionSection->editNotAllowed)) {
            if (isset($xcollectionSection->editNotAllowed->attributes()->field)) {
                $collectionSection->editNotAllowed = (string) $xcollectionSection->editNotAllowed->attributes()->field;
            }
        }
        if (isset($xcollectionSection->afterFetch)) {
            $collectionSection->afterFetch = (string) $xcollectionSection->afterFetch;
        }
        return $collectionSection;
    }

    private static function parseDisplayField(\SimpleXMLElement $xdisplayField) {
        $displayField = new \app\cwf\vsla\design\DisplayFieldType();
        $displayField->columnName = (string) $xdisplayField->attributes()->columnName;
        $displayField->displayName = (string) $xdisplayField->attributes()->displayName;
        if (isset($xdisplayField->attributes()->format)) {
            $displayField->format = (string) $xdisplayField->attributes()->format;
        }
        if (isset($xdisplayField->attributes()->size)) {
            $displayField->size = (string) $xdisplayField->attributes()->size;
        }
        if (isset($xdisplayField->attributes()->scale)) {
            $displayField->scale = (string) $xdisplayField->attributes()->scale;
        }
        if (isset($xdisplayField->attributes()->wrapIn)) {
            $displayField->wrapIn = (string) $xdisplayField->attributes()->wrapIn;
        }
        if (isset($xdisplayField->attributes()->style)) {
            $displayField->style = (string) $xdisplayField->attributes()->style;
        }
        if (isset($xdisplayField->attributes()->class)) {
            $displayField->class = (string) $xdisplayField->attributes()->class;
        }
        return $displayField;
    }

    private static function parseSqlCommand($xsqlCommand) {
        $sqlCommand = new \app\cwf\vsla\design\SqlCommandType();
        $sqlCommand->command = (string) $xsqlCommand->command;
        if (isset($xsqlCommand->params)) {
            foreach ($xsqlCommand->params->children() as $nodeName => $nodeDef) {
                if (isset($nodeDef->session)) {
                    $sqlParam = new \app\cwf\vsla\design\BaseParamSession();
                    $sqlParam->id = (string) $nodeDef->attributes()->id;
                    $sqlParam->sessionType = (string) $nodeDef->session;
                } elseif (isset($nodeDef->text)) {
                    $sqlParam = new \app\cwf\vsla\design\BaseParamText();
                    $sqlParam->id = (string) $nodeDef->attributes()->id;
                    $sqlParam->text = (string) $nodeDef->text;
                } elseif (isset($nodeDef->currentDate)) {
                    $sqlParam = new \app\cwf\vsla\design\BaseParamCurrentDate();
                    $sqlParam->id = (string) $nodeDef->attributes()->id;
                    if (isset($nodeDef->currentDate->attributes()->offsetMonth)) {
                        if (\app\cwf\vsla\utils\ValidationHelper::validateDuration((string) ($nodeDef->currentDate->attributes()->offsetMonth))) {
                            $sqlParam->offsetMonth = (string) ($nodeDef->currentDate->attributes()->offsetMonth);
                        } else {
                            $sqlParam->offsetMonth = 'P1M';
                        }
                    } elseif (isset($nodeDef->currentDate->attributes()->offsetDate)) {
                        if (\app\cwf\vsla\utils\ValidationHelper::validateDuration((string) ($nodeDef->currentDate->attributes()->offsetDate))) {
                            $sqlParam->offsetDate = (string) ($nodeDef->currentDate->attributes()->offsetDate);
                        } else {
                            $sqlParam->offsetDate = 'P1M';
                        }
                    }
                }
                $sqlCommand->params[] = $sqlParam;
            }
        }
        return $sqlCommand;
    }

    private static function parseDetailView($xdetailview, $modulePath) {
        $detailView = new \app\cwf\vsla\design\CollectionDetailView();
        if (isset($xdetailview->partialAction)) {
            $detailView->viewType = \app\cwf\vsla\design\CollectionDetailType::PARTIALACTION;
            $detailView->partialActionPath = $xdetailview->partialAction;
        } else if (isset($xdetailview->partialView)) {
            $detailView->viewType = \app\cwf\vsla\design\CollectionDetailType::PARTIALVIEW;
            $detailView->partialViewPath = $xdetailview->partialView;
            $detailView->partialView = self::parseFormView($xdetailview, $modulePath, \app\cwf\vsla\design\CwFrameworkType::ALLOC_VIEW);
        } else if (isset($xdetailview->tranView)) {
            $detailView->viewType = \app\cwf\vsla\design\CollectionDetailType::TRANVIEW;
            foreach ($xdetailview->tranView->children() as $nodeName => $nodeDef) {
                $detailView->tranView[] = self::parseDisplayField($nodeDef);
            }
        }
        return $detailView;
    }

    private static function parseExtendedView($bo_id) {
        $xextn = NULL;
        $cn = \app\cwf\vsla\security\SessionManager::getSessionVariable('companyDB');
        if (isset($cn) && $cn != '') {
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText('Select extn_info From sys.entity_extn Where bo_id = :pbo_id::uuid And company_id = :pcompany_id');
            $cmd->addParam('pbo_id', md5($bo_id));
            $cmd->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmd);
            if (count($dt->Rows()) == 1) {
                $xextn = simplexml_load_string($dt->Rows()[0]['extn_info']);
            }
        }
        return $xextn;
    }

    private static function xml_adopt(&$root, $new) {
        $node = $root->addChild($new->getName(), (string) $new);
        foreach ($new->attributes() as $attr => $value) {
            $node->addAttribute($attr, $value);
        }
        foreach ($new->children() as $ch) {
            self::xml_adopt($node, $ch);
        }
    }

}
