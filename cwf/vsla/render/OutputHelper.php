<?php

namespace app\cwf\vsla\render;

use yii\helpers\Html;
use yii\helpers\BaseHtml;
use app\cwf\vsla\design;
use app\cwf\vsla\security\AccessLevels;

include_once '../cwf/fwShell/models/MenuTree.php';
include_once '../cwf/vsla/design/CommonTypes.php';

use app\cwf\vsla\design\RelationType;

class OutputHelper {

    private static $trans = [];

    public static function output_CwFrameworkType(design\CwFrameworkType $inputView) {
        switch ($inputView->getType()) {
            case design\CwFrameworkType::FORM_VIEW :
                return self::output_FORM_VIEW($inputView);
            case design\CwFrameworkType::COLLECTION_VIEW :
                return self::output_COLLECTION_VIEW($inputView);
            case design\CwFrameworkType::REPORT_VIEW :
                return self::output_REPORT_VIEW($inputView);
            case design\CwFrameworkType::ALLOC_VIEW :
                return self::output_FormBody($inputView);
            case design\CwFrameworkType::WIZARD_VIEW :
                return self::output_WIZARD_VIEW($inputView);
        }
    }

    private static function output_FORM_VIEW(design\FormView $formView) {
        $form = '';

        $form .= Html::hiddenInput('bindingBO', '?r=' . str_replace('@app/', '', $formView->modulePath) . '&bo=' . $formView->bindingBO, ['id' => 'bindingBO']);
        if (strlen($formView->formParams) > 0) {
            $form .= Html::hiddenInput('formParams', $formView->formParams, ['id' => 'formParams']);
        }
        $form .= Html::hiddenInput('formName', Html::encode($formView->formName), ['id' => 'formName']);
        if ($formView->summaryformName != '') {
            $form .= Html::hiddenInput('summaryformName', Html::encode($formView->summaryformName), ['id' => 'summaryformName']);
            $form .= Html::hiddenInput('formHeader', $formView->header, ['id' => 'formHeader']);
        }
        $form .= Html::hiddenInput('formModulePath', Html::encode(str_replace('@app/', '', $formView->modulePath)), ['id' => 'formModulePath']);
        $form .= Html::hiddenInput('upBit', '0', ['id' => 'upBit']);
        $form .= '<div id="cformmain" name="cformmain" class="cformmain">
                        <div id="cboformheader">'
                . FormHelper::output_FormOptions($formView)
                . '</div><!--cboformheader-->'
                . self::output_FormBody($formView)
                . '</div><!--cformmain-->
                  <div id="cdialog" style="display: none;">
                  </div>';
        $form .= '<div id="actionhooks" style="display: none;">';
        if ($formView->beforeSaveEvent != '') {
            $form .= '<input type="hidden" id="hkBeforeSaveEvent" value="' . $formView->beforeSaveEvent . '"/>';
        }
        if ($formView->beforeCloseEvent != '') {
            $form .= '<input type="hidden" id="hkBeforeCloseEvent" value="' . $formView->beforeCloseEvent . '"/>';
        }
        if ($formView->afterLoadEvent != '') {
            $form .= '<input type="hidden" id="hkAfterLoadEvent" value="' . $formView->afterLoadEvent . '"/>';
        }
        $form .= '</div>';
        if ($formView->printViewExists() &&
                $formView->accessLevel > AccessLevels::NOACCESS) {
            $form .= FormHelper::addExtendedPrint($formView->printView);
        }

        return $form;
    }

    private static function output_FormBody(design\CwFrameworkType $formView) {
        $str_width = '';
        if ($formView instanceof design\AllocView) {
            $str_width = 'width:' . $formView->width . ';';
        }
        $form = '<div id="cboformwrapper" class="row"><div id="cboformbody" class="col-md-12" style="overflow-y: auto;' . $str_width . ';padding:0 10px 0 0;">
                    <div id="cboformbodyin" style="padding:0;">
                        <div class="row">
                            <div id="divbrule" name="divbrule" style="display:none;">
                                <ul id="brokenrules" style="color: #a94442;padding-left:12px;"></ul>
                            </div>
                        </div>';
        $content = '<div class="row">';
        foreach ($formView->controlSection->dataBinding->items as $item) {
            if ($item instanceof design\IElementItem) {
                $content .= self::output_IElementItem($item, $formView);
            } else {
                $content .= self::output_IDataBindingItem($item, FALSE, $formView->accessLevel);
            }
        }
        $content .= '</div>';
//        $content .= self::output_transripts();
        $form .= $content;
        $commentspan = '';
        if ($formView->type == design\BusinessObject::TYPE_DOCUMENT) {
            $commentspan = FormHelper::addComments();
        }
        $form .= '</div><!--cboformbodyin-->' .
                '</div><!--cboformbody-->' .
                $commentspan .
                '</div><!--cboformwrapper-->';
        if (property_exists($formView, 'clientJsCode')) {
            foreach ($formView->clientJsCode as $clientjscode) {
                if ($clientjscode != '') {
                    $form .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($clientjscode) . '"></script>';
                }
            }
        }

        return $form;
    }

    private static function output_COLLECTION_VIEW(design\CollectionDesignView $collectionView) {
        $filters = '';
        foreach ($collectionView->filter as $item) {
            if ($item instanceof design\IElementItem) {
                $filters .= self::output_IElementItem($item, $collectionView);
            } else {
                $filters .= self::output_IDataBindingItem($item, FALSE, AccessLevels::CONSOLIDATED);
            }
        }
        $collection = '<div id="collheader" class="row cformheader">' .
                CollectionHelper::getHeader($collectionView) .
                '</div>
                        <div id="collfilter" class="row" id="headerfilter">' .
                CollectionHelper::getFilter($collectionView, $filters) .
                '</div>
                        <div id="collectiondata" name="collectiondata" style="margin-top: 10px;">' .
                /* '<table id="thelist" class="row-border hover"></table>'.
                  CollectionHelper::getCollection($collectionView, NULL). */
                '</div>';
        if (property_exists($collectionView, 'clientJsCode')) {
            foreach ($collectionView->clientJsCode as $clientjscode) {
                if ($clientjscode != '') {
                    $collection .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($clientjscode) . '"></script>';
                }
            }
        }
        if (isset($collectionView->collectionSection)) {
            $collection .= Html::hiddenInput('after_fetch', (string) $collectionView->collectionSection->afterFetch, ['id' => 'after_fetch']);
        }
        return $collection;
    }

    private static function output_REPORT_VIEW(design\CwFrameworkType $reportView) {
        $options = self::output_FormBody($reportView);
        // By Girish: Temporary Commented needs to be fixed
        // as it causes binding errors on modelbind during report double click drill downs
        //$options .= self::output_reportSubscr($reportView);
        return $options;
    }

    private static function output_reportSubscr(design\CwFrameworkType $reportView) {
        $content = '<div id="subscrOptions" class="row" style="display:none;margin:10px;">'
                . '<input type="hidden" id="rptname" value=""/>'
                . '<div style="background-color:white;padding:10px 15px;border-radius:10px">'
                . '<strong>Subscription options</strong><div class="row">';
        foreach ($reportView->controlSection->dataBinding->items as $item) {
            if ($item instanceof design\IElementItem) {
                $content .= self::output_IElementItem($item, $reportView);
            } else {
                if ($item instanceof design\FormField && $item->control == design\ControlType::DATE) {
                    $item->control = design\ControlType::SIMPLE_COMBO;
                    $item->options = ReportHelper::output_dateoptions();
                }
                if (\property_exists($item, 'id') && $item->id != NULL && $item->id != '') {
                    $item->id = 'subscr_' . $item->id;
                }
                $content .= self::output_IDataBindingItem($item, FALSE, $reportView->accessLevel);
            }
        }
        $content .= ReportHelper::output_subscription_schedule();
        $content .= '<button id="btnsubscr" class="btn btn-default" style="margin-top:10px;float:right" type="button" onclick="cwf_jrpt.subscrClick();">
                Subscribe
            </button>';
        $content .= '</div>';
        return $content;
    }

    private static function output_WIZARD_VIEW(design\CwFrameworkType $wizardView) {
        
    }

    public static function output_SUMMARY_VIEW(design\FormView $formView) {
        $form = '';
        $form .= Html::hiddenInput('bindingBO', '?r=' . str_replace('@app/', '', $formView->modulePath) . '&bo=' . $formView->bindingBO, ['id' => 'bindingBO']);
        if (count($formView->formParams) > 0) {
            $form .= Html::hiddenInput('formParams', $formView->formParams, ['id' => 'formParams']);
        }
        $form .= Html::hiddenInput('formName', Html::encode($formView->formName), ['id' => 'formName']);
        $form .= Html::hiddenInput('formModulePath', Html::encode(str_replace('@app/', '', $formView->modulePath)), ['id' => 'formModulePath']);
        $form .= Html::hiddenInput('upBit', '0', ['id' => 'upBit']);
        if ($formView->afterLoadEvent != '') {
            $form .= '<input type="hidden" id="hkAfterLoadEvent" value="' . $formView->afterLoadEvent . '"/>';
        }
        $form .= '<div id="cformmain" name="cformmain" class="cformmain">
                        <div id="cboformheader">'
                . FormHelper::output_FormOptions($formView, TRUE)
                . '</div><!--cboformheader-->'
                . FormHelper::output_SummaryView($formView)
                . self::output_FormBody($formView)
                . '</div><!--cformmain-->
                  <div id="cdialog" style="display: none;">
                  </div>';
        return $form;
    }

    private static function output_IElementItem(design\IElementItem $item, design\FormView $formView) {
        switch ($item->getType()) {
            case design\IElementItem::TYPE_XDIV:
                return self::output_TYPE_XDIV($item);
            case design\IElementItem::TYPE_XTAB:
                return self::output_TYPE_XTAB($item, $formView);
            case design\IElementItem::TYPE_XTABPAGE:
                return self::output_TYPE_XTABPAGE($item);
            case design\IElementItem::TYPE_XDIVEND:
                return '</div></div>';
            case design\IElementItem::TYPE_XTABEND:
                return '</div>';
            case design\IElementItem::TYPE_XTABPAGEEND:
                return '</div></div>';
            case design\IElementItem::TYPE_CALLMETHOD:
                $ele = self::output_TYPE_XDIV($item);
                $ele .= $item->methodOutput;
                $ele .= '</div></div>';
                return $ele;
        }
    }

    private static function output_TYPE_XDIV(design\IElementItem $item) {
        $div = '<div';
        $div .= $item->id != '' ? ' id="' . $item->id . '"' : '';
        $div .= ' class="row ' . ($item->class != '' ? $item->class : '') . ' ' . $item->getType() . ' nopadding ';
        $div .= self::output_FieldSize($item->size) . '"';
        $div .= $item->style != '' ? ' style="' . $item->style . '"' : '';
        if (isset($item->cdata_bind)) {
            $div .= 'data-bind = "' . $item->cdata_bind . '"';
        }
        return $div . '><div class="row">';
    }

    private static function output_TYPE_XTAB(design\IElementItem $item, design\FormView $formView) {
        $div = '<div';
        $div .= $item->id != '' ? ' id="' . $item->id . '"' : '';
        $div .= ' class="row ' . ($item->class != '' ? $item->class : '') . ' ' . $item->getType() . ' nopadding tab-content ';
        $div .= self::output_FieldSize($item->size) . '"';
        $div .= $item->style != '' ? ' style="' . $item->style . '"' : '';
        if ($item->cdataVisibleOnExists()) {
            $div .= ' data-bind="visible:' . $item->cdata_visible_on . '($data)"';
        }
        $div .= '>';
        $div .= '<ul class="nav nav-tabs" ';
        $div .= $item->id != '' ? ' id="ul_' . $item->id . '"' : '';
        $div .= '>';
        $markactive = true;
        foreach ($formView->controlSection->dataBinding->items as $ele) {
            if ($ele instanceof design\XtabPage) {
                if ($ele->tabid == $item->id) {
                    $div .= '<li';
                    if ($markactive) {
                        $div .= ' class="active" ';
                        $markactive = false;
                    }
                    if ($ele->cdataVisibleOnExists()) {
                        $div .= ' data-bind="visible:' . $ele->cdata_visible_on . '($data)"';
                    }
                    $div .= '><a data-toggle="tab" href="#' . $ele->id . '"';
                    $div .= $ele->onClick != '' ? ' onclick="' . $ele->onClick . '" ' : '';
                    $div .= '>' . $ele->label . '</a></li>';
                }
            }
        }
        $div .= '</ul>';
        return $div;
    }

    private static function output_TYPE_XTABPAGE(design\IElementItem $item) {
        $div = '<div';
        $div .= $item->id != '' ? ' id="' . $item->id . '"' : '';
        $div .= ' class="row ' . ($item->class != '' ? $item->class : '') . ' ' . $item->getType() . ' nopadding tab-pane fade ';
        $div .= $item->isFirst ? ' in active ' : '';
        $div .= self::output_FieldSize($item->size) . '"';
        $div .= $item->style != '' ? ' style="' . $item->style . '"' : '';
        $div .= $item->tabid != '' ? ' tabid="' . $item->tabid . '"' : '';
        return $div . '><div class="row">';
    }

    private static function output_IDataBindingItem(design\IDataBindingItem $item, $intran = FALSE, $accessLevel) {
        switch ($item->getType()) {
            case design\IDataBindingItem::TYPE_FIELD :
                return self::output_TYPE_FIELD($item, $intran, '', $accessLevel);
            case design\IDataBindingItem::TYPE_SECTION_HEADER :
                return self::output_TYPE_SECTION_HEADER($item, $intran);
            case design\IDataBindingItem::TYPE_NEXTROW :
                return self::output_TYPE_NEXTROW($item);
            case design\IDataBindingItem::TYPE_DUMMY :
                return self::output_TYPE_DUMMY($item);
            case design\IDataBindingItem::TYPE_TRAN_SECTION :
                return self::output_TYPE_TRAN_SECTION($item, $intran, $accessLevel);
            case design\IDataBindingItem::TYPE_CLINK :
                return self::output_TYPE_CLINK($item);
            case design\IDataBindingItem::TYPE_CBUTTON :
                return self::output_TYPE_CBUTTON($item, $intran);
            case design\IDataBindingItem::TYPE_CUSTOM_FIELD :
                return self::output_TYPE_FIELD($item, $intran, '', $accessLevel);
            case design\IDataBindingItem::TYPE_CHTML :
                return self::output_TYPE_CHTML($item);
        }
    }

    private static function output_TYPE_TRAN_SECTION(design\FormTranSection $section, $intran, $accessLevel) {
        self::$trans[] = $section;
        $tran = '';
        // ** '. $this->renderSectionOptions($section).' not fully implemented 
        if (!$intran) {
            $tran .= '</div>' . "\n" . '<div style="margin-left: 1px;margin-bottom:10px;"' . self::output_DataBindSectionOptions($section) . '>';
        }
        if ($section->label !== '') {
            $tran .= "\n" . '<div class="ctranheader"><h5>' . $section->label;
            // add the Add New button in tran (if addFirst is true)
            if ($section->editMode->allowAdd && $section->dataBinding->addFirst == 'true') {
                self::render_addBtn($section, $tran);
            }
            $tran .= '</h5></div>';
        }
        if ($section->dataRelation === design\RelationType::ONE_TO_ONE) {
            $tran .= '<div style="margin-left:5px;" id="' . $section->dataBinding->dataProperty . '"' .
                    ' data-bind="template: { name: \'' . $section->dataBinding->dataProperty . '-template\', foreach: ' . $section->dataBinding->dataProperty . ' }">';
            $tran .= '</div>';

            $tran .= '<script type="text/html" id="' . $section->dataBinding->dataProperty . '-template">'
                    . '<div class="row">';

            foreach ($section->dataBinding->items as $item) {
                $tran .= "\n" . self::output_IDataBindingItem($item, FALSE, $accessLevel);
            }
            $tran .= '</div></script>' . "\n";
        } else {
            $tran .= '<div id="' . $section->dataBinding->dataProperty . '-cont"'
                    . ($section->fixedWidthExists() ? (' style="overflow-x: auto"') : '') . '>';
            $tran .= '<table class="table table-hover table-condensed" id="' . $section->dataBinding->dataProperty . '" style="border-bottom: 1px solid teal;'
                    . ($section->fixedWidthExists() ? ('width:' . $section->fixedWidth . 'pt; max-width: ' . $section->fixedWidth . 'pt;"') : '"');
            if ($section->noColHeader) {
                $tran .= '><thead style="display:none;">';
            } else {
                if ($section->fixedWidthExists()) {
                    $tran .= '><thead style="display:block;">';
                } else {
                    $tran .= '><thead>';
                }
            }
            $widthFactor = 1;
            if ($section->fixedWidthExists()) {
                $totalwidth = 0;
                foreach ($section->dataBinding->items as $item) {
                    if ($item instanceof design\NextRow) {
                        break;
                    }
                    if ($item instanceof design\FormField && $item->control != design\ControlType::HIDDEN) {
                        $totalwidth += self::output_FieldSizeNumber($item->size);
                    }
                }
                $widthFactor = $section->fixedWidth / ($totalwidth == 0 ? 12 : $totalwidth);
            }
            $isNextRow = false;
            foreach ($section->dataBinding->items as $item) {
                if ($item instanceof design\NextRow) {
                    $isNextRow = TRUE;
                    break;
                }
            }
            foreach ($section->dataBinding->items as $item) {
                if ($item instanceof design\FormField) {
                    $style = '';
                    if ($item->control == design\ControlType::HIDDEN) {
                        $style = 'display:none';
                    } else if ($item->control == design\ControlType::CHECK_BOX && $item->selectAllExists()) {
                        $tran .= '<th class="' . ($section->fixedWidthExists() ? '' : self::output_FieldSize($item->size)) . ' th-' . $item->id . '"  style="'
                                . ($section->fixedWidthExists() ? ' width: '
                                . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt; max-width: '
                                . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt;')) : '')
                                . ' text-align:center; ' . $style . '" '
                                . self::output_DataBindOptions($item, TRUE, FALSE) . '>'
                                . '<input id="all_' . $item->id . '" name="all_' . $item->id . '" data-validation-optional="true" type="checkbox" '
                                . ' onchange="' . $item->select_all_event . '(this.checked)">' //'(\''.'all_'.$item->id.'\')">'
                                . '</th>';
                    } else {
                        $tran .= '<th class="' . ($section->fixedWidthExists() ? '' : self::output_FieldSize($item->size)) . ' th-' . $item->id . '"  style="';
                        if ($isNextRow) {
                            $tran .= ($section->fixedWidthExists() ? ' width: '
                                    . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) + ($item->control == design\ControlType::SMART_COMBO ? 20 : 4) . 'pt; max-width: '
                                    . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) + ($item->control == design\ControlType::SMART_COMBO ? 20 : 4) . 'pt;')) : '');
                        } else {
                            $tran .= ($section->fixedWidthExists() ? ' width: '
                                    . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt; max-width: '
                                    . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt;')) : '');
                        }
                        $tran .= ($item->control == design\ControlType::CHECK_BOX || $item->control == design\ControlType::TOGGLE ? ' text-align:center; ' : ' ') . $style . '" '
                                . self::output_DataBindOptions($item, TRUE, FALSE) . '>'
                                . $item->label
                                . '</th>';
                    }
                } else if ($item instanceof design\CButton) {
                    if ($item->hasHeader) {
                        $tran .= '<th class="' . ($section->fixedWidthExists() ? '' : self::output_FieldSize($item->size)) . ' th-' . $item->id . '"  style="'
                                . ($section->fixedWidthExists() ? ' width: '
                                . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt; max-width: '
                                . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt;')) : '') . '">&nbsp;</th>';
                    } else {
                        $tran .= '<th style=" white-space: nowrap; width: 1%;">' . '</th>';
                    }
                } else if ($item instanceof design\NextRow) {
                    break;
                } else if ($item instanceof design\DisplayFieldType) {
                    $tran .= '<th class="' . self::output_FieldSize($item->size) . '"   style="'
                            . ($section->fixedWidthExists() ? ' width: '
                            . ((round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt; ') : '')
                            . '" >' . $item->displayName . '</th>';
                }
            }

            $tran .= (($section->editMode->allowDelete) ? '<th style="width:20px;">&nbsp</th>' : '')
                    . (($section->editMethodExists()) ? '<th style="width:20pt;">&nbsp</th>' : '')
                    . '</thead>'
                    . '<tbody ';
            if ($section->fixedWidthExists()) {
                $tran .= ' style="display:block; ';
                if ($section->fixedWidthExists()) {
                    $tran .= ($section->fixedHeightExists() ? 'max-height:' . $section->fixedHeight . 'pt;' : '')
                            . 'overflow-y:auto;"';
                }
            }
            if ($section->dataBinding->bindMethod == "datatable") {
                // For datatable, we expect client to handle templating
                $tran .= ' bind-method="datatable" bind-templ="' . $section->dataBinding->dataProperty . '-template"';
            } else {
                $tran .= 'data-bind="'
                        . 'template: { name: \'' . $section->dataBinding->dataProperty . '-template\', '
                        . 'foreach: ' . $section->dataBinding->dataProperty//.'}'
                        . ' ,afterRender: coreWebApp.latestElementadded }"';
            }
            $tran .= '>'
                    . '</tbody>'
                    . '</table>'
                    . '</div>';

            // add the Add New button in tran
            if ($section->editMode->allowAdd && $section->dataBinding->addFirst == 'false') {
                self::render_addBtn($section, $tran);
            }

            $tran .= '<div style="display: none;" id="' . $section->dataBinding->dataProperty . '-errors"></div>';

            $tran .= '<script type="text/html" id="' . $section->dataBinding->dataProperty . '-template">'
                    . '<tr>';
            $fieldcount = 0;
            foreach ($section->dataBinding->items as $item) {
                if ($item instanceof design\FormField) {
                    $style = '';
                    if ($item->control === design\ControlType::HIDDEN) {
                        $style = 'display:none;';
                    } else if ($item->control === design\ControlType::CHECK_BOX || $item->control === design\ControlType::TOGGLE) {
                        $style = 'text-align:center;' . $item->style;
                    }
                    if ($section->fixedWidthExists()) {
                        $style .= 'width: ' . (round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt;';
                        $style .= 'max-width: ' . (round(self::output_FieldSizeNumber($item->size) * $widthFactor)) . 'pt;';
                        if (!$isNextRow) {
                            $item->style .= 'width: ' . (round(self::output_FieldSizeNumber($item->size) * $widthFactor) - 4) . 'pt;';
                            $item->style .= 'max-width: ' . (round(self::output_FieldSizeNumber($item->size) * $widthFactor) - 4) . 'pt;';
                        }
                    }
                    if ($item->control == design\ControlType::LABEL) {
                        $style = $item->style;
                    }
                    $tran .= "\n" . '<td style="' . $style . '" class="td-' . $item->id . '" colspan="' . $item->colspan . '"'
                            . self::output_DataBindOptions($item, FALSE, TRUE)
                            . '>' . self::output_TYPE_FIELD($item, TRUE, $section->dataBinding->dataProperty, $accessLevel);
                    if ($item->control === design\ControlType::CHECK_BOX || $item->control === design\ControlType::TOGGLE) {
                        if ($item->tranLabel != '') {
                            $tran .= ' ' . $item->tranLabel;
                        }
                    }
                    $tran .= '</td>';
                    $fieldcount++;
                } else if ($item instanceof design\CButton) {
                    $tran .= '<td style=" white-space: nowrap; width: 1%;">' . self::output_TYPE_CBUTTON($item, TRUE) . '</td>' . "\n";
                } else if ($item instanceof design\Dummy) {
                    $tran .= '<td></td>' . "\n";
                } else if ($item instanceof design\NextRow) {
                    $tran .= '</tr><tr>' . "\n";
                } else if ($item instanceof design\Xdiv) {
                    $tran .= '<td colspan="' . $item->colspan . '"><table ';
                    if ($item->cdataBindExists()) {
                        $tran .= ' data-bind="' . $item->cdata_bind . '"';
                    } else if ($item->cdataVisibleOnExists()) {
                        $tran .= ' data-bind="visible:' . $item->cdata_visible_on . '($data)"';
                    }
                    $tran .= '><tbody><tr style="border: none;">';
                } else if ($item instanceof design\XdivEnd) {
                    $tran .= '</tr></tbody></table></td>';
                } else if ($item instanceof design\DisplayFieldType) {
                    $style = '';
                    if ($item->format == 'Amount' || $item->format == 'Number' ||
                            $item->format == 'Qty' || $item->format == 'Rate' ||
                            $item->format == 'FC' || $item->format == 'Date') {
                        $style = 'class="datatable-col-right"';
                    }
                    $tran .= '<td ' . $style . '>' . self::output_TYPE_DISPLAYFIELD($item) . '</td>';
                }
            }

            // add edit button in row

            if ($section->editMethodExists()) {
                $tran .= '<td>';
                $tran .= '<button id="cmd_editrow" type="button" tabindex="-1" class="btn btn-default" 
                    style="border:none;padding-left:5px;padding-right:5px;" 
                    data-bind="click: function() 
                                { ' . $section->editMethod . '($parent, \'' . $section->dataBinding->dataProperty . '\', $data); }">
                <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                </button>';
                $tran .= '</td>';
            }

            // add Delete button in row

            if ($section->editMode->allowDelete) {
                $tran .= '<td>';
                $tran .= '<button id="cmd_deleterow" type="button" tabindex="-1" class="btn btn-default" 
                    style="border:none;padding-left:5px;padding-right:5px;" 
                    data-bind="click: function() 
                                { coreWebApp.RemoveRowFromParent($parent, \'' . $section->dataBinding->dataProperty . '\', $data,\''
                        . $section->beforeDeleteMethod . '\',\'' . $section->afterDeleteMethod . '\');
                                    },visible: coreWebApp.ModelBo.__editMode()">
                <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                </button>';
                $tran .= '</td>';
            }

            // add nested trans
            foreach ($section->dataBinding->items as $item) {
                if ($item instanceof design\FormTranSection) {
                    $tran .= '</tr><tr><td style="border-left:10px solid teal;padding-left:1px;"'
                            . self::output_DataBindSectionOptions($item) . ' colspan="' . $fieldcount . '">';
                    $tran .= self::output_TYPE_TRAN_SECTION($item, TRUE, $accessLevel);
                    $tran .= '</td>';
                }
            }
            $tran .= '</tr></script>' . "\n";
        }
        if (!$intran) {
            $tran .= '</div>' . "\n" . '<div class="row">';
        }

        return $tran;
    }

    private static function render_addBtn(design\FormTranSection $section, string &$tran) {
        $mrgn = '';
        if ($section->dataBinding->addFirst == 'true') {
            $mrgn = ' margin-left:15px;';
        } else {
            $mrgn = ' margin-top:5px;';
        }
        if ($section->beforeAddMethodExists()) {
            if ($section->dataBinding->addRowEventExists()) {
                $tran .= '<button id="cmd_addnew_' . $section->dataBinding->dataProperty . '" class="btn btn-default cmd_addnewrowb"
                                style="font-size:12px;padding:5px 10px 3px 10px;' . $mrgn . '" type="button" 
                                data-bind="click:function(){ 
                                    var beforeadd = ' . $section->beforeAddMethod . '($data);
                                    if(!beforeadd){return;}
                                    var newrow = ' . $section->dataBinding->crudOn . '.addNewRow(\'' . $section->dataBinding->dataProperty . '\',$data, true, ' . $section->dataBinding->addFirst . ');'
                        . $section->dataBinding->addRowEvent . '(newrow);
                                        coreWebApp.afterNewRowAdded(newrow);},visible: coreWebApp.ModelBo.__editMode()">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                          </button>';
            } else {
                $tran .= '<button id="cmd_addnew_' . $section->dataBinding->dataProperty . '" class="btn btn-default cmd_addnewrowb"
                                style="font-size:12px;padding:5px 10px 3px 10px;' . $mrgn . '" type="button" 
                                data-bind="click:function(){ 
                                var beforeadd = ' . $section->beforeAddMethod . '($data);
                                    if(!beforeadd){return;}
                                var newrow = ' . $section->dataBinding->crudOn . '.addNewRow(\'' . $section->dataBinding->dataProperty . '\',$data, true, ' . $section->dataBinding->addFirst . ');
                                    coreWebApp.afterNewRowAdded(newrow);},visible: coreWebApp.ModelBo.__editMode()">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                          </button>';
            }
        } else {
            if ($section->dataBinding->addRowEventExists()) {
                $tran .= '<button id="cmd_addnew_' . $section->dataBinding->dataProperty . '" class="btn btn-default cmd_addnewrowb"
                                style="font-size:12px;padding:5px 10px 3px 10px;' . $mrgn . '" type="button" 
                                data-bind="click:function(){ 
                                    var newrow = ' . $section->dataBinding->crudOn . '.addNewRow(\'' . $section->dataBinding->dataProperty . '\',$data, true, ' . $section->dataBinding->addFirst . ');'
                        . $section->dataBinding->addRowEvent . '(newrow);
                                        coreWebApp.afterNewRowAdded(newrow);},visible: coreWebApp.ModelBo.__editMode()">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                          </button>';
            } else {
                $tran .= '<button id="cmd_addnew_' . $section->dataBinding->dataProperty . '" class="btn btn-default cmd_addnewrowb"
                                style="font-size:12px;padding:5px 10px 3px 10px;' . $mrgn . '" type="button" 
                                data-bind="click:function(){ 
                                var newrow = ' . $section->dataBinding->crudOn . '.addNewRow(\'' . $section->dataBinding->dataProperty . '\',$data, true, ' . $section->dataBinding->addFirst . ');
                                    coreWebApp.afterNewRowAdded(newrow);},visible: coreWebApp.ModelBo.__editMode()">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                          </button>';
            }
        }
    }

    /*    private static function output_transripts() {
      $tran='';
      foreach (self::$trans as $section) {
      $tran .= '<script type="text/html" id="'.$section->dataBinding->dataProperty.'-template">'
      . '<tr>';
      $fieldcount=0;
      foreach ($section->dataBinding->items as $item){
      if($item instanceof design\FormField){
      $style = '';
      if($item->control === design\ControlType::HIDDEN){
      $style = 'display:none';
      } else if ($item->control === design\ControlType::CHECK_BOX){
      $style = 'text-align:center';
      }
      $tran .= "\n".'<td style="'.$style.'" class="td-'.$item->id.'"'
      .self::output_DataBindOptions($item)
      .'>'.self::output_TYPE_FIELD($item, TRUE, $section->dataBinding->dataProperty) .'</td>';
      $fieldcount++;
      }
      }

      // add Delete button in row
      $tran .= '<td>';
      if($section->editMode->allowDelete){
      $tran .= '<button id="cmd_deleterow" type="button" tabindex="-1" class="btn btn-default"
      style="border:none;padding-left:5px;padding-right:5px;"
      data-bind="click: function()
      { coreWebApp.RemoveRowFromParent($parent, \''.$section->dataBinding->dataProperty.'\', $data); }">
      <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
      </button>';
      }
      $tran .= '</td>';

      $tran .= '</tr></script>'."\n";
      }
      return $tran;
      } */

    private static function output_DataBindOptions(design\FormField $field, $isHeader = FALSE, $isCell = FALSE) {
        $dataBindOptions = '';

        if ($field->fcDpendencyExists()) {
            $dataBindOptions .= 'enable: coreWebApp.ModelBo.fc_type_id()!=0 && coreWebApp.ModelBo.__editMode()' . ',' .
                    'visible: coreWebApp.ModelBo.fc_type_id()!=0';
        } else {
            if ($field->cdataEnableOnExists() && $isHeader == FALSE) {
                $dataBindOptions .= 'enable: ' . $field->cdata_enable_on . '($data) && coreWebApp.ModelBo.__editMode()';
            } else {
                $dataBindOptions .= 'enable: coreWebApp.ModelBo.__editMode()';
            }
            if ($field->cdataVisibleOnExists()) {
                if ($dataBindOptions != '') {
                    $dataBindOptions .= ', ';
                }
                $dataBindOptions .= 'visible: ' . $field->cdata_visible_on . '($data)';
            }
        }

        if ($dataBindOptions != '') {
            $dataBindOptions = 'data-bind=" ' . $dataBindOptions . ' "';
        }
        if ($isCell && $field->cellDataBindExists()) {
            $dataBindOptions = 'data-bind= "' . $field->cell_data_bind . '($data)"';
        }
        if ($isHeader && $field->headerDataBindExists()) {
            $dataBindOptions = 'data-bind= "' . $field->header_data_bind . '"';
        }

        return $dataBindOptions;
    }

    private static function output_DataBindSectionOptions(design\FormTranSection $tran) {
        $dataBindOptions = '';

        if ($tran->cdataEnableOnExists()) {
            $dataBindOptions .= 'enable: ' . $tran->cdata_enable_on . '($data) && coreWebApp.ModelBo.__editMode()';
        } else {
            $dataBindOptions .= 'enable: coreWebApp.ModelBo.__editMode()';
        }

        if ($tran->cdataVisibleOnExists()) {
            if ($dataBindOptions != '') {
                $dataBindOptions .= ', ';
            }
            $dataBindOptions .= 'visible: ' . $tran->cdata_visible_on . '($data)';
        }

        if ($dataBindOptions != '') {
            $dataBindOptions = 'data-bind=" ' . $dataBindOptions . ' "';
        }

        if ($tran->sizeExists()) {
            $dataBindOptions .= ' class="row nopaddingleft ' . self::output_FieldSize($tran->size) . '"';
        } else {
            $dataBindOptions .= ' class="row"';
        }

        return $dataBindOptions;
    }

    private static function output_TYPE_SECTION_HEADER(design\sectionHeader $sectionHeader, $intran) {
        if ($intran) {
            return '';
        } else {
            return '<div class="col-md-12" style="margin:2em 0 1em 0; padding:0;">
                        <div style="border-bottom:1px solid lightgray;"></div>
                        <div style="position:relative;">
                                <span class="cSectionHeader">' . $sectionHeader->label . '</span>
                        </div>
                </div>';
        }
    }

    private static function output_TYPE_NEXTROW(design\NextRow $nextRow) {
        $style = '';
        if (isset($nextRow->style)) {
            $style = ' style="' . $nextRow->style . '" ';
        }
        return '</div><div class="row"' . $style . '>';
    }

    private static function output_TYPE_DUMMY(design\Dummy $dummy) {
        $style = '';
        if (isset($dummy->style)) {
            $style = ' style="' . $dummy->style . '" ';
        }
        return '<div class="' . self::output_FieldSize($dummy->size) . '" ' . $style . '></div>';
    }

    private static function output_TYPE_CLINK(design\CLink $clink) {
        $cl = '<div class="form-group" style="margin-top:17px;">';
        $cl .= '<a href="#" '
                . 'id="' . $clink->id . '" '
                . 'class="' . self::output_FieldSize($clink->size) . ' clink" '
                . (isset($clink->style) ? ' style="' . $clink->style . '" ' : '')
                . 'data-bind="click: function() {' . $clink->onClick . '(); }">'
                . $clink->label
                . '</a>';
        $cl .= '</div>';
        return $cl;
    }

    private static function output_TYPE_CBUTTON(design\CButton $cbutton, $intran = FALSE) {
        $dataBindOptions = '';
        if ($cbutton->cdataBindExists()) {
            $dataBindOptions = $cbutton->cdata_bind;
        } else {
            if ($cbutton->ignoreEdit) {
                if ($cbutton->cdataEnableOnExists()) {
                    $dataBindOptions .= 'enable: ' . $cbutton->cdata_enable_on . '($data)';
                }
            } else {
                if ($cbutton->cdataEnableOnExists()) {
                    $dataBindOptions .= 'enable: ' . $cbutton->cdata_enable_on . '($data) && coreWebApp.ModelBo.__editMode()';
                } else {
                    $dataBindOptions .= 'enable: coreWebApp.ModelBo.__editMode()';
                }
            }

            if ($cbutton->cdataVisibleOnExists()) {
                if ($dataBindOptions != '') {
                    $dataBindOptions .= ', ';
                }
                $dataBindOptions .= 'visible: ' . $cbutton->cdata_visible_on . '($data)';
            }
        }
        $topmargin = '25px';
        $bottommargin = '0';
        if ($cbutton->inline) {
            $topmargin = '7px';
            $bottommargin = '5px';
        }
        $bicon = '';
        $btitle = '';
        if (isset($cbutton->icon) && $cbutton->icon != '') {
            $bicon = '<span class="' . $cbutton->icon . '" aria-hidden="true"></span> ';
            if (isset($cbutton->tooltip) && $cbutton->tooltip != '') {
                $btitle = ' title="' . $cbutton->tooltip . '" ';
            }
        }
        if (!$cbutton->nolabel) {
            $bicon .= $cbutton->label;
        }
        $cl = '';
        $clv = '';
        if ($cbutton->onClick != NULL || $cbutton->onClick != '') {
            if (strpos($cbutton->onClick, '(') !== false && strpos($cbutton->onClick, ')') !== false) {
                $clv = 'data-bind="click: function() {' . $cbutton->onClick . ' }';
            } else {
                $clv = 'data-bind="click: function() {' . $cbutton->onClick . '(); }';
            }
        }
        if (!$intran) {
            $cl .= '<div class="form-group" style="margin:0;">';
            $cl .= '<button '
                    . 'id="' . $cbutton->id . '" ' . $btitle
                    . 'class=" btn simple-button" style="margin:' . $topmargin . ' 15px ' . $bottommargin . ' 0; float:left; border:1px solid lightgrey;" '
                    . $clv
                    . ($dataBindOptions == '' ? '' : ',' . $dataBindOptions)
                    . '">'
                    . $bicon
                    . '</button>';
            $cl .= '</div>';
        } else {
            $cl .= '<button '
                    . 'id="' . $cbutton->id . '" ' . $btitle
                    . 'class=" btn simple-button" style="margin:0; padding:2px 10px; border:1px solid lightgrey;" '
                    . $clv
                    . ($dataBindOptions == '' ? '' : ',' . $dataBindOptions)
                    . '">'
                    . $bicon
                    . '</button>';
        }
        return $cl;
    }

    private static function output_TYPE_CHTML(design\CHtml $chtml) {
        return $chtml->html;
    }

    private static function output_TYPE_FIELD(design\FormField $field, $intran, $tranName = '', $accessLevel) {
        if ($field->control == design\ControlType::SPAN) {
            $htmlattr = [];
            if ($field->class != '') {
                $htmlattr['class'] = ' ' . $field->class . ' ';
            }
            if ($field->style != '') {
                $htmlattr['style'] = ' ' . $field->style . ' ';
            }
            if ($field->cdataBindExists()) {
                $htmlattr['data-bind'] = $field->cdata_bind;
            }
            return self::output_SPAN($field, $htmlattr, $intran);
        } else if ($field->control == design\ControlType::LABEL) {
            $htmlattr = [];
            if ($field->class != '') {
                $htmlattr['class'] = ' ' . $field->class . ' ';
            }
            if ($field->style != '') {
                $htmlattr['style'] = ' ' . $field->style . ' ';
            }
            if ($field->cdataBindExists()) {
                $htmlattr['data-bind'] = $field->cdata_bind;
            }
            return self::output_LABEL($field, $htmlattr, $intran);
        } else {
            $opfield = '';
            $nolabel = FALSE;
            if (!$intran && $field->control !== design\ControlType::HIDDEN) {
                $margin = ' style="margin-top:' . ($field->inline ? '15' : '30') . 'px;' . ($field->style != '' ? $field->style : '') . '" ';
                $opfield .= "\n" . '<div class="form-group '
                        . self::output_FieldSize($field->size)
                        . ($field->id == '' ? '' : (' field-' . $field->id))
                        . ' required" ' . (($field->control == design\ControlType::CHECK_BOX || $field->control == design\ControlType::TOGGLE) ? $margin : '') . self::output_DataBindOptions($field) . '>';
                if ($field->label != '' && $field->control != design\ControlType::CHECK_BOX && $field->control != design\ControlType::TOGGLE) {
                    $opfield .= '<label class="control-label" for="' . $field->id . '"' . self::output_DataBindOptions($field) . '>'
                            . $field->label . ($field->forStatus ? '<span id="spanstatus"></span>' : '')
                            . '</label>';
                } else {
                    $nolabel = TRUE;
                }
            }

            $field_attribs = self::output_field_attributes($field, $intran, $tranName);
            if ($field->forConsolidated && $accessLevel != AccessLevels::CONSOLIDATED) {
                $field_attribs['readonly'] = 'readonly';
            }
            switch ($field->control) {
                case design\ControlType::SIMPLE_COMBO :
                    $opfield .= self::output_SIMPLE_COMBO($field, $field_attribs, $nolabel);
                    break;
                case design\ControlType::FC :
                    $opfield .= self::output_FC($field, $field_attribs, $nolabel);
                    break;
                case design\ControlType::TEXT_AREA :
                    $opfield .= self::output_TEXT_AREA($field, $field_attribs, $nolabel);
                    break;
                case design\ControlType::SMART_LIST_BOX :
                    $opfield .= self::output_SMART_LIST_BOX($field, $field_attribs, $nolabel);
                    break;
                case design\ControlType::CHECK_LIST :
                    $opfield .= self::output_CHECK_LIST($field, $field_attribs);
                    break;
                default :
                    $opfield .= self::output_TEXT_BOX($field, $field_attribs, $nolabel);
                    break;
            }

            if (!$intran && $field->label != '' && ($field->control == design\ControlType::CHECK_BOX || $field->control == design\ControlType::TOGGLE)) {
                $opfield .= '&nbsp<label class="control-label cf-color" for="' . $field->id . '">'
                        . $field->label
                        . '</label>';
            }

            if (!$intran && $field->control !== design\ControlType::HIDDEN) {
                $opfield .= '</div>' . "\n";
            }
        }
        if ($field->computedFieldExists()) {
            $opfield .= self::output_ComputedField($field);
        }
        return $opfield;
    }

    private static function output_field_attributes(design\FormField $field, $intran, $tranName) {
        $htmlattr = array();
        $htmlattr['id'] = $field->id;
        $htmlattr['name'] = $field->id;
        $htmlattr['data-validation-optional'] = $field->isOptional == TRUE ? 'true' : 'false';
        $htmlattr['data-validation-error-msg'] = ($field->label != '' ? $field->label : $field->id) . ' is required.';
        if ($intran && $tranName != '') {
            $htmlattr['data-validation-error-msg-container'] = '#' . $tranName . '-errors';
        }

        if ($field->type == design\FieldType::INT || $field->type == design\FieldType::DECIMAL) {
            $htmlattr['allowNegative'] = $field->allowNegative == TRUE ? 'true' : 'false';
            $htmlattr['maxVal'] = $field->maxVal;
            $htmlattr['data-validations'] = 'decimal';
            //($field->type == design\FieldType::DECIMAL) ? 'decimal' : 'number';
        }
        $htmlattr['scale'] = 0;
        if ($field->type == design\FieldType::DECIMAL) {
            switch ($field->scale) {
                case design\FieldScale::FC :
                    $htmlattr['scale'] = \app\cwf\vsla\Math::$fcScale;
                    break;
                case design\FieldScale::QTY :
                    $htmlattr['scale'] = \app\cwf\vsla\Math::$qtyScale;
                    break;
                case design\FieldScale::RATE :
                    $htmlattr['scale'] = \app\cwf\vsla\Math::$rateScale;
                    break;
                default :
                    if (is_numeric($field->scale)) {
                        $htmlattr['scale'] = intval($field->scale);
                    } else {
                        $htmlattr['scale'] = \app\cwf\vsla\Math::$amtScale;
                    }
                    break;
            }
        }

        if ($field->type != design\FieldType::DATE && $field->type != design\FieldType::DATETIME && $field->type != design\FieldType::TIME) {
            if ($field->maxLength > 0) {
                $htmlattr['data-validation'] = 'length';
                $htmlattr['maxlength'] = (string) $field->maxLength;
                $htmlattr['data-validation-length'] = '1-' . (string) $field->maxLength;
                $htmlattr['data-validation-error-msg'] = $field->label . ' is required. max(' . (string) $field->maxLength . ')';
            } else if ($field->control == design\ControlType::SMART_COMBO) {
                $htmlattr['data-validation'] = 'smart-combo';
                if ($field->onChangeExists()) {
                    $htmlattr['on-change-event'] = $field->on_change_event;
                }
            } else {
                $htmlattr['data-validation'] = 'required';
            }
        }

        if ($field->control != design\ControlType::CHECK_BOX && $field->control != design\ControlType::TOGGLE) {
            if ($field->control == design\ControlType::DATE) {
                $htmlattr['class'] = ' datetime form-control';
            } else if ($field->control == design\ControlType::FC) {
                $htmlattr['class'] = ' smartcombo form-control';
            } else {
                $htmlattr['class'] = strtolower($field->control) . ' form-control';
            }
        }

        if ($field->control == design\ControlType::DATE && $field->range == 'finYear') {
            $htmlattr['start_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
            $htmlattr['end_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        }

        if ($field->control == design\ControlType::TEXT_AREA) {
            $htmlattr['rows'] = $field->rows;
        }

        if ($field->smartText != '') {
            $htmlattr['smarttext'] = $field->smartText;
        }

        if ($field->lookupExists()) {
            $htmlattr['data-NamedLookup'] = $field->lookup->namedLookup;
            $htmlattr['data-DisplayMember'] = $field->lookup->displayMember;
            $htmlattr['data-ValueMember'] = $field->lookup->valueMember;
            $htmlattr['data-Filter'] = $field->lookup->filter;
            $htmlattr['filterEvent'] = $field->lookup->filterEvent;
        }

        if ($field->lookupExists() ||
                $field->control == design\ControlType::FC) {
            $htmlattr['notyetsmart'] = 'true';
            $htmlattr['data-validation-error-msg'] = 'Please select ' . $field->label;
        }

        if ($field->control == design\ControlType::FC) {
            $htmlattr['data-NamedLookup'] = '../core/ac/lookups/FCType.xml';
            $htmlattr['data-ValueMember'] = 'fc_type_id';
            $htmlattr['data-DisplayMember'] = 'fc_type';
            $htmlattr['data-Filter'] = '';
            $htmlattr['filterEvent'] = '';
        }

        if ($field->computedFieldExists()) {
            $htmlattr['forceCalOnPost'] = $field->forceCalOnPost == TRUE ? 'true' : 'false';
            $htmlattr['data-computed'] = $field->id . '_calculated';
        }
        if ($field->placeholder != '') {
            $htmlattr['placeholder'] = $field->placeholder;
        }

        if (isset($field->readOnly) && ($field->readOnly == 'readonly' || $field->readOnly == 'onEdit')) {
            $htmlattr['readonly'] = $field->readOnly;
            $htmlattr['tabindex'] = '-1';
            $htmlattr['data-validation-optional'] = 'true';
        }
        if (!isset($field->readOnly) || $field->readOnly != 'readonly') {
            $htmlattr['tabindex'] = '0';
            $htmlattr['autocomplete'] = 'off';
        }
        $databind = '';
        switch ($field->control) {
            case design\ControlType::CHECK_BOX :
                $databind = 'checked';
                $htmlattr['type'] = 'checkbox';
                $htmlattr['data-validation-optional'] = 'true';
                unset($htmlattr['data-validation']);
                break;
            case design\ControlType::TOGGLE:
                $databind = 'toggle';
                $htmlattr['type'] = 'checkbox';
                $htmlattr['data-validation-optional'] = 'true';
                $htmlattr['data-toggle'] = 'toggle';
                $htmlattr['data-on'] = $field->toggleOn;
                $htmlattr['data-off'] = $field->toggleOff;
                $htmlattr['data-size'] = "mini";
                $htmlattr['data-width'] = self::output_custom_size($field->size);
                unset($htmlattr['data-validation']);
                break;
            case design\ControlType::TEXT_BOX :
                if ($field->type == design\FieldType::DECIMAL || $field->type == design\FieldType::INT) {
                    $databind = 'numericValue';
                } else {
                    $databind = 'value';
                }
                break;
            case design\ControlType::DATE :
                $databind = 'dateValue';
                $htmlattr['type'] = 'DateTime';
                break;
            case design\ControlType::PASSWORD :
                $databind = 'value';
                $htmlattr['type'] = 'password';
                break;
            case design\ControlType::DATE_TIME_TEXT :
                $databind = 'datetimetext';
                break;
            case design\ControlType::SMART_LIST_BOX :
                $databind = 'options';
                break;
            case design\ControlType::CHECK_LIST :
                $databind = 'checked';
                break;
            default :
                $databind = 'value';
                $htmlattr['type'] = 'text';
                break;
        }
        $htmlattr['data-bind'] = $databind . ': ' . $field->id;

        if ($field->control == design\ControlType::SMART_COMBO || $field->control == design\ControlType::FC) {
            $htmlattr['data-bind'] .= ' , select2: ' . $field->id;
        } else if ($field->control == design\ControlType::MULTI_SELECT) {
            $htmlattr['data-bind'] .= ' , select2m: ' . $field->id;
        } else if ($field->control == design\ControlType::SMART_LIST_BOX) {
            $htmlattr['data-bind'] .= ', optionsText:\'' . $field->lookup->displayMember . '\', value: ' . $field->lookup->valueMember;
            $htmlattr['size'] = '6';
            if ($field->fwdAction != '' || $field->revAction != '') {
                $htmlattr['style'] = 'border-radius: 0 0 5px 5px;';
            }
//            $htmlattr['multiple'] = 'true';
        }
        if ($field->control == design\ControlType::MULTI_SELECT) {
            $htmlattr['multiple'] = 'multiple';
        }

        if ($field->fcDpendencyExists()) {
            $htmlattr['data-fc-dependent'] = $field->data_fc_dependent;
            if ($htmlattr['data-bind'] !== NULL && $htmlattr['data-bind'] !== '') {
                $htmlattr['data-bind'] .= ', ';
            } else {
                $htmlattr['data-bind'] = '';
            }
            if ($intran) {
                $htmlattr['data-bind'] .= 'visible: $parent.fc_type_id()!=0, enable: $parent.fc_type_id()!=0 && coreWebApp.ModelBo.__editMode()';
            } else {
                $htmlattr['data-bind'] .= 'visible: coreWebApp.ModelBo.fc_type_id()!=0, enable: coreWebApp.ModelBo.fc_type_id()!=0 && coreWebApp.ModelBo.__editMode()';
            }
        }

        if ($field instanceof design\CustomFormField) {
            $htmlattr['CustomField'] = 'true';
            $htmlattr['data-validation-optional'] = 'true';
            $htmlattr['forceCalOnPost'] = 'true';
        }

        if ($field->cdataEnableOnExists()) {
            if ($htmlattr['data-bind'] !== NULL && $htmlattr['data-bind'] !== '') {
                $htmlattr['data-bind'] .= ', ';
            } else {
                $htmlattr['data-bind'] = '';
            }
            $htmlattr['data-bind'] .= 'enable: ' . $field->cdata_enable_on . '($data) && coreWebApp.ModelBo.__editMode()';
        } else {
            if ($htmlattr['data-bind'] !== NULL && $htmlattr['data-bind'] !== '') {
                $htmlattr['data-bind'] .= ', ';
            } else {
                $htmlattr['data-bind'] = '';
            }
            $htmlattr['data-bind'] .= 'enable: coreWebApp.ModelBo.__editMode()';
        }

        if ($field->cdataVisibleOnExists() && !$intran) {
            if ($htmlattr['data-bind'] !== NULL && $htmlattr['data-bind'] !== '') {
                $htmlattr['data-bind'] .= ', ';
            } else {
                $htmlattr['data-bind'] = '';
            }
            $htmlattr['data-bind'] .= 'visible: ' . $field->cdata_visible_on . '($data)';
        }

        if ($field->cdataBindExists()) {
            if ($htmlattr['data-bind'] !== NULL && $htmlattr['data-bind'] !== '') {
                $htmlattr['data-bind'] .= ', ';
            } else {
                $htmlattr['data-bind'] = '';
            }
            $htmlattr['data-bind'] .= $field->cdata_bind;
        }

        if ($field->mdataEventExists()) {
            $htmlattr['mdata-events'] = $field->mdata_events;
        }

        if ($field->valueExists()) {
            $cbval = ReportHelper::output_paramvalue($field);
            if ($field->control == design\ControlType::CHECK_BOX || $field->control == design\ControlType::TOGGLE) {
                if (strtolower($cbval) == 'true') {
                    $htmlattr['checked'] = 'true';
                }
            }
            $htmlattr['value'] = $cbval;
        }

        if ($field->class != '') {
            if (key_exists('class', $htmlattr)) {
                $htmlattr['class'] .= ' ' . $field->class . ' ';
            } else {
                $htmlattr['class'] = ' ' . $field->class . ' ';
            }
        }

        if ($field->style != '') {
            if (key_exists('style', $htmlattr)) {
                $htmlattr['style'] .= ' ' . $field->style . ' ';
            } else {
                $htmlattr['style'] = ' ' . $field->style . ' ';
            }
        }

        return $htmlattr;
    }

    private static function output_TYPE_DISPLAYFIELD(design\DisplayFieldType $displayField) {
        if ($displayField->format == 'Amount' || $displayField->format == 'Number' ||
                $displayField->format == 'Qty' || $displayField->format == 'Rate' ||
                $displayField->format == 'FC') {
            $scale = 0;
            switch ($displayField->scale) {
                case design\FieldScale::FC :
                    $scale = \app\cwf\vsla\Math::$fcScale;
                    break;
                case design\FieldScale::QTY :
                    $scale = \app\cwf\vsla\Math::$qtyScale;
                    break;
                case design\FieldScale::RATE :
                    $scale = \app\cwf\vsla\Math::$rateScale;
                    break;
                default :
                    $scale = \app\cwf\vsla\Math::$amtScale;
                    break;
            }
            $fld = '<span data-bind="text:coreWebApp.formatNumber(' . $displayField->columnName . '(),' . $scale . ')"></span>';
        } else if ($displayField->format == 'Date' || $displayField->format == 'Datetime') {
            $fld = '<span data-bind="text:coreWebApp.formatDate(' . $displayField->columnName
                    . '(), \'' . \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format') . '\')"></span>';
        } else {
            $fld = '<span data-bind="text:' . $displayField->columnName . '"></span>';
        }
        return $fld;
    }

    private static function output_LABEL(design\FormField $label, $htmlattr, $intran = FALSE) {
        $visibility = '';
        if ($label->cdataVisibleOnExists()) {
            $visibility = 'data-bind="visible: ' . $label->cdata_visible_on . '($data)"';
        }
        $labelstyle = '';
        if ($label->inline) {
            if (key_exists('style', $htmlattr)) {
                $labelstyle .= 'margin-top:15px;' . $htmlattr['style'];
            } else {
                $labelstyle .= 'margin-top:15px;" ';
            }
        } else {
            if (key_exists('style', $htmlattr)) {
                $labelstyle .= $htmlattr['style'];
            }
        }
        $labelclass = 'form-group clabel';
        if (key_exists('class', $htmlattr)) {
            $labelclass .= ' ' . $htmlattr['class'];
        }
        if (!$intran) {
            $labelclass .= ' ' . (self::output_FieldSize($label->size));
        }
        $databind = '';
        if (key_exists('data-bind', $htmlattr)) {
            $databind = ' data-bind = "' . $htmlattr['data-bind'] . '" ';
        }
        return '<div>'
                . '<span id="' . $label->id . '" class="' . $labelclass . '"' . $visibility
                . ' style="' . $labelstyle . '" '
                . $databind
                . '>'
                . $label->label
                . '</span>'
                . '</div>';
    }

    private static function output_SPAN(design\FormField $label, $htmlattr, $intran = FALSE) {
        $visibility = '';
        if ($label->cdataVisibleOnExists()) {
            $visibility = 'data-bind="visible: ' . $label->cdata_visible_on . '($data)"';
        }
        $labelstyle = '';
        if ($label->inline) {
            if (key_exists('style', $htmlattr)) {
                $labelstyle .= $htmlattr['style'];
            } else {
                $labelstyle .= 'margin-top:15px;" ';
            }
        } else {
            if (key_exists('style', $htmlattr)) {
                $labelstyle .= $htmlattr['style'];
            }
        }
        $labelclass = 'form-group';
        if (key_exists('class', $htmlattr)) {
            $labelclass .= ' ' . $htmlattr['class'];
        }
        if (!$intran) {
            $labelclass .= ' ' . (self::output_FieldSize($label->size));
        }
        $databind = '';
        if (key_exists('data-bind', $htmlattr)) {
            $databind = ' data-bind = "' . $htmlattr['data-bind'] . '" ';
        }
        return '<div>'
                . '<span id="' . $label->id . '" class="' . $labelclass . '"' . $visibility
                . ' style="' . $labelstyle . '" '
                . $databind
                . '>'
                . $label->label
                . '</span>'
                . '</div>';
    }

    private static function output_SIMPLE_COMBO(design\FormField $simplecombo, $field_attribs, $nolabel) {
        if ($nolabel) {
            if (array_key_exists('style', $field_attribs)) {
                $field_attribs['style'] .= ' margin-top:17px;';
            } else {
                $field_attribs['style'] = ' margin-top:17px;';
            }
        }
        return Html::dropDownList($simplecombo->id, $simplecombo->options->defaultValue, $simplecombo->options->choices, $field_attribs);
    }

    private static function output_FC(design\FormField $fc, $field_attribs, $nolabel) {
        $eropts = ['class' => 'fc-x-rate form-control',
            'id' => 'exch_rate',
            'data-bind' => 'numericValue: exch_rate, visible:' . $fc->id . '()!=0',
            'data-validations' => 'decimal',
            'subtype' => 'rate',
            'data-fc-field' => $fc->id,
            'scale' => \app\cwf\vsla\Math::$fcScale,
            'placeholder' => 'Exchange rate',
            'style' => 'text-align:right;width:70px;'
        ];
        if (isset($fc->readOnly) && $fc->readOnly == 'true' && $fc->exchDisable) {
            $eropts['readonly'] = 'readonly';
        }
        if ($fc->mdataEventExists()) {
            $eropts['mdata-events'] = $field_attribs['mdata-events'];
        }

        $field_attribs['class'] .= ' form-group ';
        return '<div id="fc_' . $fc->id . '" class="form-inline">'
                . '<div class="form-group" style="margin-top:0;">'
                . Html::input($fc->type, $fc->id, Null, $field_attribs)
                . '</div>'
                . '<div class="form-group" style="margin-top:0;">'
                . Html::input('TextBox', 'exch_rate', Null, $eropts)
                . '</div>'
                . '</div>';
    }

    private static function output_TEXT_AREA(design\FormField $textarea, $field_attribs, $nolabel) {
        if ($nolabel) {
            if (array_key_exists('style', $field_attribs)) {
                $field_attribs['style'] = ' margin-top:17px;' . $field_attribs['style'];
            } else {
                $field_attribs['style'] = ' margin-top:17px;';
            }
        }
        return Html::textarea($textarea->id, '', $field_attribs);
    }

    private static function output_TEXT_BOX(design\FormField $textbox, $field_attribs, $nolabel) {
        if (!$textbox->inline) {
            if ($nolabel && ($textbox->control != design\ControlType::CHECK_BOX && $textbox->control != design\ControlType::TOGGLE)) {
                if (array_key_exists('style', $field_attribs)) {
                    $field_attribs['style'] = ' margin-top:17px;' . $field_attribs['style'];
                } else {
                    $field_attribs['style'] = ' margin-top:17px;';
                }
            }
        }
        if ($textbox->valueExists()) {
            return Html::input($textbox->type, $textbox->id, $field_attribs['value'], $field_attribs);
        } else {
            return Html::input($textbox->type, $textbox->id, NULL, $field_attribs);
        }
    }

    private static function output_SMART_LIST_BOX(design\FormField $listbox, $field_attribs, $nolabel) {
        $fld = Html::listBox($listbox->id, $selection = [], $items = [], $options = $field_attribs);
        $actn = '';
        $actnlbl = '';
        if ($listbox->fwdAction != '') {
            $actn = 'data-bind="click: function() {' . $listbox->fwdAction . '(); }"';
            $actnlbl = '<i class="glyphicon glyphicon-arrow-right"></i>';
        } else if ($listbox->revAction != '') {
            $actn = 'data-bind="click: function() {' . $listbox->revAction . '(); }"';
            $actnlbl = '<i class="glyphicon glyphicon-arrow-left"></i>';
        }
        $res = '';
        if ($listbox->fwdAction != '' || $listbox->revAction != '') {
            $res = '<button id="' . $listbox->id . '_rev" '
                    . 'class=" btn" style="width:100%; border:1px solid lightgrey;border-radius:5px 5px 0 0;border-bottom:0px;padding: 0 12px;" ' . $actn . '>' . $actnlbl . '</button>';
        }
        $res .= $fld;
        return $res;
    }

    private static function output_CHECK_LIST(design\FormField $checklist, $attrs) {
        $fld = '';
        $attrs['class'] = 'checklist';
        $attrs['style'] = 'height:7em;overflow-y:auto;border:1px solid lightgrey;';
        if ($checklist->optionsExists()) {
            unset($checklist->options->choices[-1]);
            $fld .= '<div id="' . $checklist->id . '" style="height:7em;overflow-y:auto;border:1px solid lightgrey;">';
            foreach ($checklist->options->choices as $key => $value) {
                $fld .= ' <input type="checkbox" data-bind="checkedValue: ' . $key . ', checked: coreWebApp.ModelBo.' . $checklist->id . '" />
                        <span>' . $value . '</span><br/>';
            }
            $fld .= '</div>';
        }
        if ($checklist->lookupExists()) {
            unset($attrs['data-bind']);
            $fld .= Html::tag('div', '', $attrs);
        }
        return $fld;
    }

    private static function output_ComputedField(design\FormField $field) {
        return '<script type="text/javascript" id="' . $field->id . '_calculated">' .
                'var ' . $field->id . '_calculated=function(){' . $field->computedField . '};' .
                'var ' . $field->id . '_calculated_w=function(val){};' .
                '</script>';
    }

    private static function output_FieldSize($size) {
        switch ($size) {
            case design\FieldSize::XS_COL_1 :
                return 'col-md-1 col-xs-12';
            case design\FieldSize::MS_COL_2 :
                return 'col-md-2 col-xs-12';
            case design\FieldSize::M_COL_6 :
                return 'col-md-6 col-xs-12';
            case design\FieldSize::L_COL_9 :
                return 'col-md-9 col-xs-12';
            case design\FieldSize::XL_COL_12 :
                return 'col-md-12 col-xs-12';
            default :
                if (is_numeric($size)) {
                    $temp = intval($size);
                    if ($temp > 12) {
                        $res = 'col-md-12 col-xs-12';
                    } else if ($temp < 1) {
                        $res = 'col-md-1 col-xs-12';
                    } else {
                        $res = 'col-md-' . $temp . ' col-xs-12';
                    }
                } else {
                    $res = 'col-md-3 col-xs-12';
                }
                return $res;
        }
    }

    private static function output_custom_size($size) {
        $res = 0;
        if (strpos($size, 'C') == 0) {
            $intval = substr($size, 1);
            if (is_numeric($intval)) {
                $res = $intval;
            }
        }
        return $res;
    }

    private static function output_FieldSizeNumber($size) {
        switch ($size) {
            case design\FieldSize::XS_COL_1 :
                return 1;
            case design\FieldSize::MS_COL_2 :
                return 2;
            case design\FieldSize::M_COL_6 :
                return 6;
            case design\FieldSize::L_COL_9 :
                return 9;
            case design\FieldSize::XL_COL_12 :
                return 12;
            default :
                if (is_numeric($size)) {
                    $temp = floatval($size);
                    return $temp;
                } else {
                    return 3;
                }
        }
    }

}
