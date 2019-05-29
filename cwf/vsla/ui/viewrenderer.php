<?php

namespace app\cwf\vsla\ui {

    use yii\helpers\Html;

    class viewrenderer {

        private $viewparser;
        private $renderstring;
        private $renderheader;
        private $formname;

        public function __construct($viewparser, $formname) {
            $this->viewparser = $viewparser;
            $this->formname = $formname;
            $this->renderMain();
        }

        public function getHeder() {
            $this->renderformoptions();
            return $this->renderheader;
        }

        public function getForm() {
            return $this->renderstring;
        }

        private function renderMain() {
            $this->renderstring = <<<rndr
rndr;
            $this->renderstring .= Html::input('hidden', 'fc-fields', json_encode($this->viewparser->section->fc_fields), ['id' => 'fc-fields']);
            $this->renderstring .= '<div class="row">';
            $this->renderstring .= '<div id="divbrule" name="divbrule" style="display:none;">'
                    . '<ul id="brokenrules" style="color: #a94442;"></ul></div>';
            foreach ($this->viewparser->section->fields as $field) {
                if (!$field instanceof viewpartsection && $field !== NULL) {
                    if ($field->inputType === 'nextRow') {
                        $this->renderstring .= $this->renderNextRow();
                    } else if ($field->inputType === 'sectionHeader') {
                        $this->renderstring .= $this->renderSectionHeader($field);
                    } else if ($field->inputType === 'cLabel') {
                        $this->renderstring .= $this->renderLabel($field);
                    } else if ($field->inputType === 'cHTML') {
                        $this->renderstring .= $field->options;
                    } else if ($field->inputType === 'cButton') {
                        $this->renderstring .= $this->renderButton($field);
                    } else if ($field->inputType === 'cLink') {
                        $this->renderstring .= $this->renderLink($field);
                    } else if ($field->inputType === 'Hidden') {
                        $this->renderstring .= $this->renderItem($field, FALSE, '');
                    } else {
                        $enfld = '';
                        if (is_array($field->options)) {
                            if (key_exists('data-bind', $field->options)) {
                                if (strpos($field->options['data-bind'], 'visible:')) {
                                    $enfld = substr($field->options['data-bind'], strpos($field->options['data-bind'], 'visible:'));
                                }
                            }
                        }
                        $this->renderstring .= '<div class="form-group ' .
                                ($field->size === null ? 'col-md-3' :
                                ($field->inputType === 'Hidden' ? '' : $field->size)) .
                                ' field-' . $field->id . ' required"'
                                . (($field->nolabel == TRUE || $field->label == '') ? ' style="margin-bottom:0;"' : '')
                                . '>' . (($field->nolabel == TRUE || $field->label == '' ||
                                $field->inputType == 'CheckBox' || $field->inputType == 'Hidden') ? '' :
                                Html::label($content = $field->label, $for = $field->id, $options = ['class' => 'control-label' . (($field->inputType !== 'CheckBox' ? '' : ' col-md-12 ')),
                                    'style' => (($field->inputType == 'CheckBox' ? 'padding:6px 0;' : '')),
                                    'data-bind' => $enfld])) .
                                $this->renderItem($field, FALSE, '') . '</div>';
                    }
                } else {
                    $this->renderstring .= $this->renderItem($field, FALSE, '');
                }
            }
            if ($this->viewparser->isPrintAllowed) {
                $this->renderstring .= $this->addprintdata();
            }
            if (isset($this->viewparser->clientJsCodes)) {
                foreach ($this->viewparser->clientJsCodes as $clientjscode) {
                    if ($clientjscode != '') {
                        $this->renderstring .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($clientjscode) . '"></script>';
                    }
                }
            }
            $this->renderstring .= '</div>';
        }

        private function renderItem($fld, $isTran = FALSE, $parentname = '') {
            $res;
            if ($fld instanceof viewpartsection) {
                $res = $this->renderTran($fld, $parentname);
            } else if ($fld !== NULL) {
                if ($fld->inputType === 'CheckBox') {
                    $res = $this->renderCheckbox($fld);
                } else if ($fld->inputType === 'SimpleCombo') {
                    $res = $this->renderSimpleCombo($fld);
                } else if ($fld->inputType === 'SmartCombo') {
                    $res = $this->renderSmartCombo($fld);
                }/* else if($fld->inputType==='SmartText'){
                  $res= $this->renderSmartTextBox($fld);
                  } */ else if ($fld->inputType === 'Date') {
                    $res = $this->renderDate($fld);
                } else if ($fld->inputType === 'FC') {
                    $res = $this->renderFC($fld);
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
                if (isset($fld->options['data-fc-dependent'])) {
                    //$res.=$this->renderFcFields($fld,$isTran);
                }
            } else {
                $res = '';
            }
            return $res;
        }

        private function renderIndControls($sec) {
            $rndrstring = '<div class="row"' . $this->renderSectionOptions($sec) . '>';
            $theader = '<div class="row">' .
                    ($sec->header != '' ? '<h5 class="ch5">'
                    . $sec->header . '</h5>' : '') . '</div>';
            $rndrstring .= $theader;
            $rndrstring .= '<div style="margin-left:5px;" id="' . $sec->dataProperty . '"' .
                    ' data-bind="template: { name: \'' . $sec->dataProperty . '-template\', foreach: ' . $sec->dataProperty . ' }">';
            $rndrstring .= '</div>';

            $rndrstring .= <<<tranheadr
                <script type="text/html" id="{$sec->dataProperty}-template">
                <div class="row">
tranheadr;
            foreach ($sec->fields as $field) {
                if (!$field instanceof viewpartsection) {
                    if ($field->inputType === 'nextRow') {
                        $rndrstring .= $this->renderNextRow();
                    } else if ($field->inputType === 'cLabel') {
                        $this->renderstring .= $this->renderLabel($field);
                    } else if ($field->inputType === 'cHTML') {
                        $this->renderstring .= $field->options;
                    } else if ($field->inputType === 'cButton') {
                        $res = $this->renderButton($field);
                    } else if ($field->inputType === 'cLink') {
                        $res = $this->renderLink($field);
                    } else {
                        $enfld = '';
                        if (is_array($field->options)) {
                            if (key_exists('data-bind', $field->options)) {
                                if (strpos($field->options['data-bind'], 'visible:')) {
                                    $enfld = substr($field->options['data-bind'], strpos($field->options['data-bind'], 'visible:'));
                                }
                            }
                        }
                        $rndrstring .= '<div class="form-group ' .
                                ($field->size === null ? 'col-md-3' : $field->size) .
                                ' field-' . $field->id . ' required">' . (($field->nolabel == TRUE || $field->label == '') ? '' :
                                Html::label($content = ($field->inputType !== 'CheckBox' ? $field->label : '&nbsp'), $for = $field->id, $options = ['class' =>
                                    'control-label' . (($field->inputType !== 'CheckBox' ? '' : ' col-md-12 ')),
                                    'data-bind' => $enfld])) .
                                $this->renderItem($field, TRUE, $sec->dataProperty) . '</div>';
                    }
                } else {
                    $rndrstring .= $this->renderItem($field, TRUE, $sec->dataProperty);
                }
            }
            $rndrstring .= <<<tranftr
            </div>
            </script><br></div>
tranftr;

            return $rndrstring;
        }

        private function renderTran($transection, $parentproperty) {
            $tranbuilder = '';
            if ($transection->dataRelation === 'OneToOne') {
                $tranbuilder = $this->renderIndControls($transection);
            } else {
                if ($parentproperty == '') {
                    $tranbuilder .= '<div class="row" ' . $this->renderSectionOptions($transection) . ' style="margin-left: 1px;margin-bottom:10px;">';
                }
                $this->renderstring .= Html::input('hidden', 'fc-fields', json_encode($transection->fc_fields), ['id' => 'fc-fields']);
                $theader = '<table class="table table-hover table-condensed" id="' . $transection->dataProperty . '">' .
                        ($transection->header == '' ? '' : '<caption style="text-align:left;">'
                        . '<h5>' . $transection->header . '</h5>'
                        . '</caption>') . '<thead>';
                $tranbuilder .= $theader;
                $fldcnt = 0;
                foreach ($transection->fields as $field) {
                    $style = '';
                    if ($field instanceof viewpartsection) {
                        
                    } else {
                        if ($field->type === 'Hidden') {
                            $style = "display:none;";
                        }
                        if ($field->inputType === 'cButton') {
                            $tranbuilder .= '<th class="' .
                                    ($field->size === null ? 'col-md-1' : $field->size) . '">' . $this->renderButton($field) . '</th>';
                        } else if ($field->inputType === 'cLink') {
                            $tranbuilder .= '<th class="' .
                                    ($field->size === null ? 'col-md-1' : $field->size) . '">' . $this->renderLink($field) . '</th>';
                        } else {
                            $tranbuilder .= '<th class="' .
                                    ($field->size === null ? 'col-md-3' : $field->size) . '"'
                                    . ($field->inputType === 'CheckBox' ? ' style="text-align:center;' . $style . '"' : ' style="' . $style . '"')
                                    . $this->renderBindOptions($field)
                                    . '>' . $field->label . '</th>';
                        }
                        $fldcnt++;
                    }
                }

                $tranbuilder .= <<<tranheadr
                <th>&nbsp</th>
                </thead>
                <tbody data-bind="template: { name: '{$transection->dataProperty}-template', foreach: {$transection->dataProperty}, afterRender: coreWebApp.afterNewRowAdded }">
                </tbody>
                </table>
tranheadr;
                $propertypath = $parentproperty == '' ?
                        ($transection->dataProperty) : ($parentproperty . '.' . $transection->dataProperty);
                if (strpos($transection->editMode, 'Add') !== false && $this->viewparser->access_level > 1) {
                    if ($transection->addRowEvent === '') {
                        $tranbuilder .= <<<tranftr
                <button id="cmd_addnewrowb" class="btn btn-default" style="font-size:12px;padding:5px 10px 3px 10px;" type="button" 
                    data-bind="click:function(){ \$root.addNewRow('{$transection->dataProperty}',\$data);}">
                    <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                </button>
tranftr;
                    } else {
                        $tranbuilder .= <<<tranftr
                    <button id="cmd_addnewrowb" class="btn btn-default" style="font-size:12px;padding:5px 10px 3px 10px;" type="button" 
                        data-bind="click: function() { 
                            var newrow = \$root.addNewRow('{$transection->dataProperty}',\$data); 
                            {$transection->addRowEvent}(newrow);
                        }">
                    <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> Add
                    </button>
tranftr;
                    }
                }
                $tranbuilder .= <<<tranheadr
                <script type="text/html" id="{$transection->dataProperty}-template">
                <tr>
tranheadr;
                foreach ($transection->fields as $field) {
                    $style = '';
                    if ($field instanceof viewpartsection) {
                        
                    } else {
                        if ($field->type === 'Hidden') {
                            $style = "display:none;";
                        }
                        $tdd = '<td style="' . $style . '"'
                                . $this->renderBindOptions($field)
                                . '>' . $this->renderItem($field, TRUE, $transection->dataProperty) . '</td>';
                        $tranbuilder .= $tdd;
                    }
                }
                $tranbuilder .= '<td>';
                if (strpos($transection->editMode, 'Delete') !== false && $this->viewparser->access_level > 1) {
                    $tranbuilder .= <<<tranftr
            <button id="cmd_deleterow" type="button" tabindex="-1" class="btn btn-default" style="border:none;padding-left:5px;padding-right:5px;" data-bind="click: function() { coreWebApp.RemoveRowFromParent(\$parent, '{$transection->dataProperty}', \$data); }">
            <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
            </button>
tranftr;
                }
                $tranbuilder .= '</td>';
                foreach ($transection->fields as $field) {
                    if ($field instanceof viewpartsection) {
                        $tranbuilder .= '</tr><tr><td style="border-left:10px solid teal;padding-left:1px;" colspan="' . $fldcnt . '">';
                        $tranbuilder .= $this->renderTran($field, $transection->dataProperty);
                        $tranbuilder .= '</td>';
                    }
                }
                $tranbuilder .= '</tr></script>';
                if ($parentproperty == '') {
                    $tranbuilder .= '</div>';
                }
            }
            return $tranbuilder;
        }

        private function renderBindOptions($field) {
            $databindoption = '';
            $dbvis = '';
            $dbenb = '';
            if (key_exists('data-fc-dependent', $field->options)) {
                $dbvis = 'visible:coreWebApp.ModelBo.' . $this->viewparser->fc_field . '()!=0';
                $dbenb = 'enable:coreWebApp.ModelBo.' . $this->viewparser->fc_field . '()!=0';
            }
            if (key_exists('cdata-enable', $field->options)) {
                $dbenb = $field->options['cdata-enable'];
            }
            if (key_exists('cdata-visible', $field->options)) {
                $dbvis = $field->options['cdata-visible'];
            }

            if ($dbvis != '' && $dbenb != '') {
                $databindoption = 'data-bind="' . $dbvis . ',' . $dbenb . '"';
            } else if ($dbvis != '' && $dbenb == '') {
                $databindoption = 'data-bind="' . $dbvis . '"';
            } else if ($dbenb != '' && $dbvis == '') {
                $databindoption = 'data-bind="' . $dbenb . '"';
            }
            return $databindoption;
        }

        private function renderSectionOptions(viewpartsection $sec) {
            $stropt = '';
            if (isset($sec->options)) {
                if (count($sec->options) > 0) {
                    foreach ($sec->options as $opt => $val) {
                        $stropt .= $opt . '="' . $val . '" ';
                    }
                }
            }
            return $stropt;
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

        private function renderSectionHeader($fld) {
            return <<<hdr
                <div class="col-md-12" style="margin:2em 0 1em 0;">
                        <div style="border-bottom:1px solid lightgray;"></div>
                        <div style="position:relative;">
                                <span class="cSectionHeader">{$fld->label}</span>
                        </div>
                </div>
hdr;
        }

        private function renderBlank() {
            return '<div class=""></div>';
        }

        private function renderNextRow() {
            return '</div><div class="row">';
        }

        private function renderButton($fld) {
            $str = '<button id="' . $fld->id . '" class="btn btn-default" style="margin-top:15px;" data-bind="click: function() {' . $fld->options['onclick'] . '(' . $fld->options['params'] . '); }"';
            if (isset($fld->options['readonly']) && $fld->options['readonly'] != '') {
                $str .= ' disabled="disabled" ';
            }
            $str .= '>' . $fld->label . '</button>';
            return $str;
        }

        private function renderLink($fld) {
            $str = '<a href="#" id=" ' . $fld->id . '" style="border:none;" style="margin-top:15px;"  data-bind="click: function() {' . $fld->options['onclick'] . '(' . $fld->options['params'] . '); }"';
            if (isset($fld->options['readonly']) && $fld->options['readonly'] != '') {
                $str .= ' disabled="disabled" ';
            }
            $str .= '>' . $fld->label . '</a>';
            return $str;
        }

        private function renderLabel($fld) {
            return '<div class="form-group ' . ($fld->size == null ? 'col-md-3' : $fld->size) .
                    ' " style="display: table-cell; vertical-align: middle;height:24px;">'
                    . '<label style="padding:6px 0;">' . $fld->label . '</label>'
                    . '</div>';
        }

        private function renderTextArea($fld) {
            return Html::textarea($fld->id, '', $fld->options);
        }

        private function renderSmartTextBox($fld) {
            $config = array();
            $config['name'] = $fld->id;
            $config['options'] = ['placeholder' => 'Filter as you type ...'];
            $config['model'] = 'coreWebApp.ModelBo';
            $config['attribute'] = $fld->id;
            $config['pluginOptions'] = ['highlight' => true];
            $sconfig = array();
            $sconfig['datumTokenizer'] = "Bloodhound.tokenizers.obj.whitespace(" . $fld->id . ")";
            //$sconfig['display']=$fld->id;;
            $lnk = '?r=cwf/fwShell/main/lookup3&namedlookup=' . $fld->options['data-NamedLookup'] .
                    '&displaymember=' . $fld->options['data-DisplayMember'] . '&q=%QUERY';
            $sconfig['remote'] = ['url' => $lnk, 'wildcard' => '%QUERY'];
            $config['dataset'] = [$sconfig];
            $out = \kartik\typeahead\Typeahead::widget($config);
            return $out;
        }

        private function renderCalculatedField($fld) {
            return '<script type="text/javascript" id="' . $fld->id . '_calculated">' .
                    'var ' . $fld->id . '_calculated=function(){' . $fld->formula . '};' .
                    'var ' . $fld->id . '_calculated_w=function(val){};' .
                    '</script>';
        }

        private function renderFC($fld) {
            $eropts = ['class' => 'fc-x-rate form-control', 'id' => 'exch_rate',
                'data-bind' => 'numericValue: ' . $fld->exchRateField . ', visible:' . $fld->id . '()!=0',
                'data-validations' => 'decimal', 'subtype' => 'rate',
//                'data-validation-allowing'=>'float',
//                'data-validation-optional'=>'true',
                'data-fc-field' => $fld->id, 'scale' => \app\cwf\vsla\Math::$fcScale,
                'placeholder' => 'Exchange rate', 'style' => 'text-align:right;width:70px;'
            ];
            if (key_exists('readonly', $fld->options)) {
                $exchdisable = false;
                if (key_exists('exch-disable', $fld->options)) {
                    $exchdisable = $fld->options['exch-disable'];
                }
                if ($fld->options['readonly'] && $exchdisable) {
                    $eropts['readonly'] = 'true';
                }
            }
            if (key_exists('mdata-events', $fld->options)) {
                if (isset($fld->options['mdata-events'])) {
                    $eropts['mdata-events'] = $fld->options['mdata-events'];
                }
            }

            $fld->options['class'] .= ' form-group ';
            return '<div id="fc_' . $fld->id . '" class="form-inline">' .
                    '<div class="form-group" style="margin-top:0;">' .
                    Html::input($fld->inputType, $fld->id, Null, $fld->options) .
                    '</div><div class="form-group" style="margin-top:0;">' .
                    Html::input('TextBox', $fld->exchRateField, Null, $eropts) .
                    '</div></div>';
        }

        private function renderFcFields($fld, $isTran = FALSE, $eos = FALSE) {
            if ($isTran && !$eos) {
                return '';
            }
            $temp = '<script type="text/javascript" id="' . $fld->id . '_calculated">
                    var ' . $fld->id . '_calculated=function(){
                        var res=new Number();
                        var xrate=new Number();
                        if(parseFloat(coreWebApp.ModelBo.exch_rate())===0){
                            xrate=1;
                        }else{
                            xrate= new Number(parseFloat(coreWebApp.ModelBo.exch_rate()));
                        }
                        res=new Number(parseFloat('
                    . ($isTran ? '$data.' . $fld->options['data-fc-dependent'] : 'coreWebApp.ModelBo.' . $fld->options['data-fc-dependent'])
                    . '())/xrate.toFixed(4));
                        return res.toFixed(4);
                    };
                    var ' . $fld->id . '_calculated_w=function(val){
                        if(isNaN(val)){return 1;}
                        var res=new Number();
                        var xrate=new Number();
                        if(parseFloat(coreWebApp.ModelBo.exch_rate())===0){
                            xrate=1;
                        }else{
                            xrate= new Number(parseFloat(coreWebApp.ModelBo.exch_rate()));
                        }
                        res=new Number(parseFloat(val)*xrate.toFixed(4));'
                    . ($isTran ? '$data.' . $fld->options['data-fc-dependent'] : 'coreWebApp.ModelBo.' . $fld->options['data-fc-dependent']) . '(res.toFixed(4));
                    };
                    </script>';
            return $temp;
        }

        private function renderformoptions() {
            $rndformopt = <<<opts
            <div class="row cformheader">
                <h3 class="col-md-5">{$this->viewparser->section->header}{$this->addHelp()}</h3>
                <div class="col-md-6 cformheaderbuttons">
opts;
            $rndformopt .= $this->addclose();


            if ($this->viewparser->access_level > 1) {
                if ($this->viewparser->isNewAllowed) {
                    $rndformopt .= $this->addnew();
                }
                if ($this->viewparser->isPrintAllowed) {
                    $rndformopt .= $this->addprint();
                }
                if ($this->viewparser->formType === 'Document' && $this->viewparser->access_level === 3) {
                    $rndformopt .= $this->addpost();
                }
                $rndformopt .= $this->addsave();
                if ($this->viewparser->isDeleteAllowed) {
                    $rndformopt .= $this->adddelete();
                }
            } elseif ($this->viewparser->access_level == 1) {
                if ($this->viewparser->isPrintAllowed) {
                    $rndformopt .= $this->addprint();
                }
            }
            $rndformopt .= <<<opts
                </div>
            </div>
opts;
            $this->renderheader = $rndformopt;
        }

        private function addHelp() {
            $helpbtn = '';
            if ($this->viewparser->helplink != '') {
                $helpbtn = <<<helplink
                    <button id="hlink" name="hlink" class="btn btn-sm btn-default"
                        style="margin-left: 15px;border:none;padding:0;font-size:18px;" 
                        type="button" onclick="coreWebApp.openHelp('{$this->viewparser->helplink}')">
                    <span class="glyphicon glyphicon-question-sign" style="color:darkgray;" aria-hidden="true"></span>
                </button>
helplink;
            }
            return $helpbtn;
        }

        private function addsave() {
            $res = <<<res
            <button id="cmdsave" class="btn btn-primary formoptions" 
                    data-bind="click: \$root.Submit.bind(\$data, '{$this->formname}',0)" 
                    name="save-button" type="submit" style="display:none;">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Save
            </button>
res;
            return $res;
        }

        private function addpost() {
            $res = <<<res
            <button id="cmdpost" class="btn btn-success formoptions" 
                    data-bind="click: \$root.Submit.bind(\$data, '{$this->formname}',5)" 
                    name="post-button" type="submit" style="display:none;">
                    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Post
            </button>
res;
            return $res;
        }

        private function addprint() {
            $res = <<<res
            <button id="cmdprint" class="btn btn-info formoptions" 
                    data-bind="click: \$root.Print.bind()"
                    name="print-button" style="display:none;">
                    <span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print
            </button>
res;
            return $res;
        }

        private function addclose() {
            $res = <<<res
            <button id="cmdclose" class="btn btn-info formoptions" 
                    style="background-color:lightgrey;border-color:lightgrey;color:black;float:right;"
                    data-bind="click: coreWebApp.closeDetail(true)"
                    name="close-button">
                    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Close
            </button>
res;
            return $res;
        }

        private function addprintdata() {
            $printurl = '?r=cwf%2FfwShell%2Freport%2Fvchreport';
            $res = '<div id="divprintdata" name="divprintdata" style="display:none;" printurl="' . $printurl . '">';
            $res .= '<input type="hidden" id="divp__csrf" name="divp__csrf" value="'
                    . \Yii::$app->request->csrfToken . '">';
            $res .= '<input type="hidden" id="divp_xmlPath" name="divp_xmlPath" value="'
                    . $this->viewparser->printXml . '">';
            foreach ($this->viewparser->printParams as $key => $value) {
                $res .= '<input type="hidden" id="divp_' . $key . '" name="divp_' . $key . '" data-bind="value:' . $value . '">';
            }
            $res .= '<iframe id="rptContainer" name="rptContainer" style="display:none;"></iframe>';
            $res .= '</div>';

            return $res;
        }

        private function addnew() {
            $res = <<<res
            <button id="cmdnew" class="btn btn-default formoptions" 
                    onclick="{$this->createnewlink()}" 
                    name="new-button" style="display:none;">
                    <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
            </button>
res;
            return $res;
        }

        private function adddelete() {
            $res = <<<res
            <button id="cmddelete" class="btn btn-danger formoptions" 
                    data-bind="click: \$root.Delete.bind('bo-form')"
                    name="delete-button" style="display:none;">
                    <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> Delete
            </button>
res;
            return $res;
        }

        private function createnewlink() {
            $keytype = NULL;
            $clicklink = NULL;
            if ($this->viewparser->isNewAllowed === TRUE) {
                if (isset($this->viewparser->newParams)) {
                    if (isset($this->viewparser->newParams['DocType']) && $this->viewparser->newParams['DocType'] !== null) {
                        $keytype = (string) $this->viewparser->newParams['DocType'];
                    }
                }
            }

            if ($keytype === NULL) {
                $clicklink = Html::encode(
                                'coreWebApp.onNewClick(\'?r=/' . $this->viewparser->modulePath .
                                '/form&formName=' . $this->formname .
                                '&formParams=' . '{"' . $this->viewparser->keyField . '": -1}' . '\',\'details\',\'contentholder\''
                                . (isset($this->viewparser->beforeNew) ? (',' . $this->viewparser->beforeNew) : '')
                                . (isset($this->viewparser->afterNew) ? (',' . $this->viewparser->afterNew) : '')
                                . ');');
            } else {
                $clicklink = Html::encode(
                                'coreWebApp.onNewClick(\'?r=/' . $this->viewparser->modulePath .
                                '/form&formName=' . $this->formname .
                                '&formParams=' . '{"' . $this->viewparser->keyField . '": -1,"doc_type":"' . $keytype . '"}' . '\',\'details\',\'contentholder\''
                                . (isset($this->viewparser->beforeNew) ? (',' . $this->viewparser->beforeNew) : '')
                                . (isset($this->viewparser->afterNew) ? (',' . $this->viewparser->afterNew) : '')
                                . ');');
            }

            if ($this->viewparser->newType === 'wizard') {
                $clicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->viewparser->modulePath .
                                '/form/wizard&formName=' . $this->viewparser->wizPath .
                                '&step=' . $this->viewparser->wizStep . '\',\'details\',\'contentholder\');');
            }
            $clicklink .= ' return false;';
            return $clicklink;
        }

        private function createdelete() {
            $keytype = NULL;
            $clicklink = NULL;
            if ($keytype === NULL) {
                $clicklink = Html::encode(
                                'renderdelete(\'?r=/' . $this->viewparser->modulePath .
                                '/form&formName=' . $this->formname .
                                '&formParams=' . '{"' . $this->viewparser->keyField . '": -1}' . '\',\'details\',\'contentholder\');');
            } else {
                $clicklink = Html::encode(
                                'renderdelete(\'?r=/' . $this->viewparser->modulePath .
                                '/form&formName=' . $this->formname .
                                '&formParams=' . '{"' . $this->viewparser->keyField . '": -1,"doc_type":"' . $keytype . '"}' . '\',\'details\',\'contentholder\');');
            }
            $clicklink .= ' return false;';
            return $clicklink;
        }

    }

}
