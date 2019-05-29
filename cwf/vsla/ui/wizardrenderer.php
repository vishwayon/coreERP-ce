<?php

namespace app\cwf\vsla\ui {

    use yii\helpers\Html;
    use app\cwf\vsla\data\SqlCommand;
    use app\cwf\vsla\data\DataConnect;

    class wizardrenderer {

        /** @var wizardparser * */
        private $wizparser;
        private $renderstring = '';
        private $renderheader;
        private $formname;
        public $renderEvents = [];

        public function __construct($wizparser, $formname) {
            $this->wizparser = $wizparser;
            $this->formname = $formname;
            if ($this->wizparser->xsteps[$formname]->final) {
                $this->renderstring = '';
            } else {
                $this->renderheader = '<div id="collheader" class="row cformheader"><h3 style="width:80%;float:left;">' .
                        $this->wizparser->header . '</h3><button id="cmdcancel" class="btn btn-danger" style="font-size:12px;float:right;" 
                     name="wcancel-button" data-bind="click: coreWebApp.closedetailonmenu">Cancel</button></div>';
                $this->renderstring = '<div><h4>' .
                        $this->wizparser->xsteps[$formname]->header . '</h4></div>';
                $this->renderBrokenRules();
                foreach ($this->wizparser->xsteps[$formname]->wizSection as $wizSec) {
                    if ($wizSec->wizType === 'collection') {
                        $this->wizparser->xsteps[$formname]->stepWizData[$wizSec->id] = $this->getData(
                                $wizSec->sql, $wizSec->cnType);
                        $this->renderEvents = $wizSec->renderEvents;
                    } else if ($wizSec->wizType === 'form') {
                        $temp = array();
                        foreach ($wizSec->fields as $fld) {
                            if ($fld->defaultValue != NULL) {
                                $temp[$fld->fieldName] = $fld->defaultValue;
                            } else {
                                $temp[$fld->fieldName] = $this->setDefaults($fld->viewField->type); //$fld->defaultValue;
                            }
                            if ($fld->fieldName == 'doc_date') {
                                $temp[$fld->fieldName] = \app\cwf\vsla\utils\FormatHelper::GetValidDate();
                            }
                        }
                        $this->wizparser->xsteps[$formname]->stepWizData[$wizSec->id] = $temp;
                    }
                    $this->renderMain($wizSec);
                }
                $this->renderstring .= $this->addButtons();
            }
        }

        public function getHeder() {
            return $this->renderheader;
        }

        public function getForm() {
            return $this->renderstring;
        }

        public function renderMain($wizSec) {
            if ($wizSec->wizType == 'collection' && $wizSec->bindMethod == 'datatable') {
                $this->renderstring .= $this->renderDatatable($wizSec);
            } else if ($wizSec->wizType === 'collection') {
                $this->renderstring .= $this->renderCollection($wizSec);
            } else if ($wizSec->wizType === 'form') {
                $this->renderstring .= $this->renderForm($wizSec);
            }

            if (isset($this->wizparser->clientJsCodes)) {
                foreach ($this->wizparser->clientJsCodes as $clientjscode) {
                    if ($clientjscode != '') {
                        $this->renderstring .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($clientjscode) . '"></script>';
                    }
                }
            }
        }

        private function renderBrokenRules() {
            if (count($this->wizparser->codeBehind->brokenrules) > 0) {
                $errors = '<div style="color: #a94442;"><br/><p style="margin-left:5px;">Broken Rules : </p><ul>';
                foreach ($this->wizparser->codeBehind->brokenrules as $err) {
                    $errors .= '<li>' . $err . '</li>';
                }
                $errors .= '</ul></div>';
                $this->renderstring .= $errors;
            }
        }

        private function renderCollection($collectionSection) {
            $wizbuilder = <<<wizhead
                <div id="coll-{$this->formname}" style="max-height: 400px;">
                <style scoped>
                tbody {
                    display:block;
                    overflow:auto;
                    max-height:340px;
                }
                thead, tbody tr {
                    display:table;
                    width:100%;
                }
                thead {
                    width: 100%;
                }
                th{
                    padding:5px 0;
                }
                </style>
                <table class="table table-hover table-condensed wiz" id="{$this->formname}">
                <thead><tr>
wizhead;
            $wizbuilder .= '<th style="width:45px;padding-left:5px;">Select</th>';
//            $colcnt = count($collectionSection->fields);
//            $vart = 99/$colcnt ;
            $normsize = 0;
            foreach ($collectionSection->fields as $nfld) {
                $normsize += $nfld->sizenum;
            }
            $vart = 99 / $normsize;
            foreach ($collectionSection->fields as $fld) {
                $field = $fld->viewField;
                if ($field->inputType === 'cButton') {
                    $wizbuilder .= '<th style="width:' . $vart * $fld->sizenum . '%;">' . $this->renderButton($field) . '</th>';
                } else if ($field->inputType === 'cLink') {
                    $wizbuilder .= '<th style="width:' . $vart * $fld->sizenum . '%;">' . $this->renderLink($field) . '</th>';
                } else {
                    $wizbuilder .= '<th'
                            . ($field->inputType === 'CheckBox' ? ' style="text-align:center;width:45px;"' : ' style="width:' . $vart * $fld->sizenum . '%;"')
                            . '>' . $field->label . '</th>';
                }
            }
            //<td>&nbsp</td>
            $wizbuilder .= <<<wizheadr
                </tr>
                </thead>
                <tbody data-bind="template: { name: '{$this->formname}-template', foreach: {$collectionSection->id} }"> 
                </tbody>
                </table>
                </div>
wizheadr;
            $wizbuilder .= <<<wizheadr
                <script type="text/html" id="{$this->formname}-template">
                <tr><td><input id="selected" class="form-control" type="checkbox" data-bind="checked: selected" value="0" name="selected" style="width:40px;"></td>
wizheadr;

            foreach ($collectionSection->fields as $field) {
                $wizbuilder .= '<td style="width:' . $vart * $field->sizenum . '%;">' . $this->renderItem($field->viewField) . '</td>';
            }

            $wizbuilder .= '</tr></script>';
            return $wizbuilder;
        }

        private function renderDatatable($collectionSection) {
            $wizbuilder = <<<wizhead
                <div id="coll-{$this->formname}" style="max-height: 800px;">
                
                <table id="tbl-{$this->formname}"></table>
                </div>
wizhead;

            return $wizbuilder;
        }

        private function renderForm($formSection) {
            $wizbuilder = '<div class="row">'; //'<!-- ko with:" '.$formSection->id.'"--><div class="row">';
            foreach ($formSection->fields as $fld) {
                $field = $fld->viewField;
                if (!$field instanceof viewpartsection && $field !== NULL) {
                    $tempbind = $field->options['data-bind'];
                    if ($field->type === 'date') {
                        $field->options['data-bind'] = 'dateValue: ' . $formSection->id . '.' . $field->id;
                    } else if ($field->type === 'decimal') {
                        $field->options['data-bind'] = 'numericValue: ' . $formSection->id . '.' . $field->id;
                    } else {
                        $field->options['data-bind'] = 'value: ' . $formSection->id . '.' . $field->id;
                    }

                    if ($field->inputType == 'SmartCombo') {
                        $field->options['data-bind'] .= ' ,select2: ' . $formSection->id . '.' . $field->id;
                    }

                    if ($field->inputType === 'nextRow') {
                        $wizbuilder .= $this->renderNextRow();
                    } else if ($field->inputType === 'cLabel') {
                        $wizbuilder .= $this->renderLabel($field);
                    } else if ($field->inputType === 'cHTML') {
                        $wizbuilder .= $field->options;
                    } else if ($field->inputType === 'cButton') {
                        $wizbuilder .= $this->renderButton($field);
                    } else if ($field->inputType === 'cLink') {
                        $wizbuilder .= $this->renderLink($field);
                    } else {
                        $wizbuilder .= '<div class="form-group ' .
                                ($field->size === null ? 'col-md-3' :
                                ($field->inputType === 'Hidden' ? '' : $field->size)) .
                                ' field-' . $field->id . ' required">' .
                                Html::label($content = ($field->inputType !== 'CheckBox' && $field->inputType !== 'Hidden' ? $field->label : '&nbsp'), $for = $field->id, $options = ['class' =>
                                    'control-label' . (($field->inputType !== 'CheckBox' ? '' : ' col-md-12 '))]) .
                                //'<div class="">'.
                                $this->renderItem($field) .
                                //'</div><div class="col-lg-7"></div>'.
                                '</div>';
                    }
                } else {
                    $wizbuilder .= $this->renderItem($field);
                }
            }
            $wizbuilder .= '</div>';

            return $wizbuilder;
        }

        private function renderItem($fld) {
            $res;
            if ($fld instanceof viewpartsection) {
                $res = $this->renderTran($fld);
            } else if ($fld !== NULL) {
                if ($fld->inputType === 'CheckBox') {
                    $res = $this->renderCheckbox($fld);
                } else if ($fld->inputType === 'SimpleCombo') {
                    $res = $this->renderSimpleCombo($fld);
                } else if ($fld->inputType === 'SmartCombo') {
                    $res = $this->renderSmartCombo($fld);
                } else if ($fld->inputType === 'Date') {
                    $res = $this->renderDate($fld);
                } else if ($fld->inputType === 'blank') {
                    $res = $this->renderBlank();
                } else if ($fld->inputType === 'nextRow') {
                    $res = $this->renderNextRow();
                } else if ($fld->inputType === 'TextArea') {
                    $res = $this->renderTextArea($fld);
                } else if ($fld->inputType === 'cHTML') {
                    $res = $fld->options;
                } else if ($fld->inputType === 'cButton') {
                    $res = '';
                } else if ($fld->inputType === 'cLink') {
                    $res = '';
                } else {
                    $res = Html::input($fld->inputType, $fld->id, Null, $fld->options);
                }
                if ($fld->calculated) {
                    $res .= $this->renderCalculatedField($fld);
                }
            } else {
                $res = '';
            }
            return $res;
        }

        private function renderCheckbox($fld) {
            if ($fld->defaultValue != NULL) {
                return Html::checkbox($fld->id, $checked = $fld->defaultValue, $fld->options);
            } else {
                return Html::checkbox($fld->id, $checked = false, $fld->options);
            }
        }

        private function renderSmartCombo($fld) {
            if ($fld->defaultValue != NULL) {
                return Html::input($fld->inputType, $fld->id, $fld->defaultValue, $fld->options);
            } else {
                return Html::input($fld->inputType, $fld->id, Null, $fld->options);
            }
        }

        private function renderSimpleCombo($fld) {
            if ($fld->defaultValue != NULL) {
                return Html::dropDownList($fld->id, $fld->defaultValue, $fld->items, $fld->options);
            } else {
                return Html::dropDownList($fld->id, Null, $fld->items, $fld->options);
            }
        }

        private function renderDate($fld) {
            if ($fld->defaultValue != NULL) {
                return Html::input($fld->inputType, $fld->id, \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($fld->defaultValue), $fld->options);
            } else {
                return Html::input($fld->inputType, $fld->id, NULL, $fld->options);
            }
        }

        private function renderBlank() {
            return '<div class=""></div>';
        }

        private function renderNextRow() {
            return '</div><div class="row">';
        }

        function setCollectionURL($sql) {
            $res = 'select false as selected,a.* from ( ' . $sql . ')a';
            return $res;
        }

        function getData($sql, $cnType) {
            if ((string) $sql == '') {
                return '';
            }
            $cmm = \app\cwf\vsla\data\SqlParser::getSql($sql);
            $cmm->setCommandText($this->setCollectionURL($cmm->getCommandText()));
            foreach ($sql->params->param as $param) {
                $paramval = NULL;
                if ($param->wizard) {
                    $paramval = $this->wizparser->getParamValue($param->wizard);
                    $cmm->setParamValue((string) $param['id'], $paramval);
                }
            }
            $collection = DataConnect::getData($cmm, $cnType);
            return $collection;
        }

        function addButtons() {
            $res = '<div class="form-group">';
            if ($this->wizparser->prevStep !== NULL) {
                $res .= '<button id="cmdprev" class="btn btn-primary" style="font-size:12px;float:left;" 
                     name="wprev-button" data-bind="click:coreWebApp.wizPrev">
                     <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> Prev
                     </button>';
            }
            if ($this->wizparser->nextStep !== NULL) {
                $res .= '<button id="cmdnext" class="btn btn-primary" style="font-size:12px;float:right;" 
                     name="wnext-button" data-bind="click:coreWebApp.wizNext">
                     <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> Next
                     </button>';
            } else {
                
            }
            $res .= '</div>';
            return $res;
        }

        function setDefaults($type) {
            switch ($type) {
                case 'int':
                    return -1;
                case 'decimal':
                    return 0.00;
                case 'date':
                    return date("Y-m-d", time());
                default:
                    return '';
            }
        }

    }

}