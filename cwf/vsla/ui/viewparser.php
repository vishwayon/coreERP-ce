<?php

namespace app\cwf\vsla\ui {
    include_once 'viewparts.php';

    use app\cwf\vsla\data\DataAdapter;
    use app\cwf\vsla\security\AccessManager;
    use app\cwf\vsla\security\AccessLevels;

include_once getcwd() . '/../cwf/fwShell/models/MenuTree.php';

    class viewparser {

        private $xrootview;
        public $header;
        public $keyField;
        public $modulePath;
        public $formParams;
        public $formType;
        public $newType = 'normal';
        public $wizPath, $wizStep;
        public $isNewAllowed;
        public $fc_field = 'fc_type_id', $exch_field, $fc_fields = array();
        public $beforeNew, $afterNew;
        public $isDeleteAllowed;
        public $isUnpostAllowed;
        public $newParams;
        public $isPrintAllowed;
        public $printXml;
        public $printParams;
        public $bindingBO;
        public $clientJsCodes;

        /** @var viewpartsection */
        public $section;
        private $parentviewparser;
        private $isNew = true;
        public $access_level = -1, $bo_id;
        public $helplink = '';

        function __construct($rootView, $modulePath, $formParams) {
            $this->xrootview = $rootView;
            $this->modulePath = $modulePath;
            $this->formParams = $formParams;
            $this->initialise();
        }

        private function initialise() {
            $params = is_array($this->formParams) ? $this->formParams : json_decode($this->formParams);
            if ($params != null) {
                foreach ($params as $param) {
                    if (is_int($param) && $param != -1) {
                        $this->isNew = false;
                    }
                }
            }
            if (isset($this->xrootview['type'])) {
                if ((string) $this->xrootview['type'] !== 'alloc') {
                    if (isset($this->xrootview['id'])) {
                        $this->bo_id = (string) $this->xrootview['id'];
                        $this->access_level = AccessManager::verifyAccess($this->bo_id);
                        if ($this->access_level < AccessLevels::READONLY) {
                            return;
                        }
                    }
                } else {
                    $this->access_level = AccessLevels::CONSOLIDATED;
                }
            }

            if (isset($this->xrootview['type'])) {
                $this->formType = (string) $this->xrootview['type'];
            } else {
                $this->formType = 'Master';
            }
            $this->bindingBO = $this->xrootview['bindingBO'];
            $this->helplink = isset($this->xrootview['helpLink']) ? (string) $this->xrootview['helpLink'] : '';
            $this->header = (string) $this->xrootview->header;

            if (isset($this->xrootview['extends'])) {
                $parentview = simplexml_load_file((string) $this->xrootview['extends'] . '.xml');
                $this->parentviewparser = new viewparser($parentview, $this->modulePath, $this->formParams);
            }
            if ($this->xrootview->controlSection) {
                $section;
                if (isset($this->xrootview->controlSection['method'])) {
                    $section = $this->parentviewparser->section;
                } else {
                    $section = new viewpartsection();
                }
                $section->sectionType = 'controlSection'; //ensectiontype::controlsection;
                $section->header = (string) $this->xrootview->header;
                $section->dataProperty = (string) $this->xrootview->attributes()->id;
                $section->editMode = (string) $this->xrootview->controlSection->attributes()->editMode;
                $xExtnFields = self::parseExtendedView($this->bo_id);
                $xDataBinding = $this->xrootview->controlSection->dataBinding;
                if (isset($xExtnFields)) {
                    foreach ($xExtnFields->field as $xfld) {
                        $temp = $xDataBinding->addChild($xfld->getName());
                        foreach ($xfld->attributes() as $key => $value) {
                            $temp->addAttribute($key, $value);
                        }
                    }
                }
                foreach ($this->xrootview->controlSection->dataBinding->children() as $name => $fld) {
                    $myfield = NULL;
                    if ($name === 'field' || $name === 'customField') {
                        if ((string) $fld->attributes()->control === 'Label') {
                            $myfield = $this->setDummy('cLabel', $fld);
                        } else {
                            $myfield = $this->setField($fld);
                        }
                        if ((string) $fld->attributes()['data-fc-dependent']) {
                            $section->fc_fields[(string) $fld->attributes()['id']] = (string) $fld->attributes()['data-fc-dependent'];
                        }
                    } else if ($name === 'tranSection') {
                        $myfield = $this->setTran($fld);
                    } else if ($name === 'dummy') {
                        $myfield = $this->setDummy('blank', $fld);
                    } else if ($name === 'nextRow') {
                        $myfield = $this->setDummy('nextRow', $fld);
                    } else if ($name === 'sectionHeader') {
                        $myfield = $this->setSectionHeader($fld);
                    } else if ($name === 'cHTML') {
                        $myfield = $this->setHtml($fld);
                    } else if ($name === 'cButton') {
                        $myfield = $this->setButton($fld);
                    } else if ($name === 'cLink') {
                        $myfield = $this->setLink($fld);
                    }
                    if (strtolower((string) $fld->attributes()['readOnly']) == 'true') {
                        $myfield->options['readonly'] = 'readonly';
                        $myfield->options['tabindex'] = '-1';
                    } elseif ((string) $fld->attributes()['readOnly'] == 'OnEdit') {
                        if (!$this->isNew) {
                            $myfield->options['readonly'] = 'readonly';
                            $myfield->options['data-validation-optional'] = 'true';
                            $myfield->options['tabindex'] = '-1';
                        }
                    }

                    if (!$myfield instanceof viewpartsection && $myfield !== NULL) {
                        if ($this->access_level === AccessLevels::READONLY) {
                            $myfield->options['readonly'] = 'true';
                        }
                        if ($this->access_level > AccessLevels::NOACCESS) {
                            array_push($section->fields, $myfield);
                        }
                    } else {
                        if ($this->access_level > AccessLevels::NOACCESS && $myfield !== NULL) {
                            array_push($section->fields, $myfield);
                        }
                    }
                }
                if (isset($this->xrootview->controlSection['mdata-event'])) {
                    $section->mdata_events = $this->set_mdata((string) $this->xrootview->controlSection['mdata-event']);
                    $section->options['mdata-events'] = (string) $this->xrootview->controlSection['mdata-event'];
                }
                $this->section = $section;
            }
            $this->set_formoptions();
        }

        private function setField($fld, $isTran = FALSE) {
            $inputType = (string) $fld->attributes()->control;
            if ($inputType === 'Label') {
                return NULL;
            }
            $field;
            if ($inputType == 'SmartCombo') {//(int)  eninputtype::Smartcombo){
                $field = new viewpartsmartcombofield();
            } else if ($inputType == 'SimpleCombo') {
                $field = new viewpartsimplecombofield();
                $field->items[-1] = 'Select an option';
                foreach ($fld->options->option as $opt) {
                    $field->items[(string) $opt->attributes()->value] = (string) $opt;
                }
            } else if ($inputType === 'FC') {
                $field = new viewpartsmartcombofield();
                $field->exchRateField = 'exch_rate';
                $fld->lookup->namedLookup = '../core/ac/lookups/FCType.xml';
                $fld->lookup->valueMember = 'fc_type_id';
                $fld->lookup->displayMember = 'fc_type';
                $this->fc_field = (string) $fld['id'];
                $this->exch_field = 'exch_rate';
            } else if ($inputType == 'SmartTextBox') {
                $field = new viewpartsmartcombofield();
            } else {
                $field = new viewpartfield();
            }
            $field->id = (string) $fld->attributes()->id;
            $field->label = (string) $fld->attributes()->label;
            $field->type = (string) $fld->attributes()->type;
            $field->inputType = $inputType;
            if (strtolower((string) $fld['isOptional']) == 'true') {
                $field->optional = TRUE;
            }

            if ($fld->attributes->editMode != NULL) {
                $field->editMode = $fld->attributes->editMode;
            } else {
                $field->editMode = 'Edit'; //eneditmode::edit;
            }

            if ((string) $fld->computedField != NULL) {
                $field->calculated = TRUE;
                $field->formula = (string) $fld->computedField;
            }

            if ($fld->getName() === 'customField') {
                $field->isCustom = TRUE;
            } else {
                $field->isCustom = FALSE;
            }
            $field->size = $this->setSize($fld);
            $field->options = $this->setOptions($fld, $isTran);

            $field->defaultDate = NULL;
            if ($fld->value) {
                if ($fld->value->session) {
                    $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable((string) $fld->value->session);
                }
                if ($fld->value->text) {
                    $field->defaultValue = $fld->value->text;
                }
                if ($fld->value->currentDate) {
                    $field->defaultValue = date("Y-m-d", time());
                }
            }

            if ($inputType == 'SimpleCombo') {
                if (isset($fld->options->attributes()['defaultValue'])) {
                    $field->defaultValue = (string) $fld->options->attributes()['defaultValue'];
                }
            }

            if ((string) $fld->attributes()['range']) {
                if ((string) $fld->attributes()['range'] == 'finYear') {
                    if ($field->defaultValue == null) {
                        if (strtotime(date("Y-m-d", time())) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
                        }
                    } else {
                        if (strtotime($field->defaultValue) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
                        }
                    }
                }
            }
            if (isset($fld['mdata-event'])) {
                $field->mdata_events = $this->set_mdata((string) $fld['mdata-event']);
                $field->options['mdata-events'] = (string) $fld['mdata-event'];
            }
            if (isset($fld['nolabel']) || $field->label == '') {
                $field->nolabel = TRUE;
            }
            return $field;
        }

        private function setTran($transection) {
            $section = new viewpartsection();
            $section->sectionType = 'tranSection';
            $section->header = (string) $transection->attributes()->label;
            $section->dataProperty = (string) $transection->dataBinding->attributes()->dataProperty;
            if ($transection->dataBinding->attributes()->dataRelation !== NULL && (string) $transection->dataBinding->attributes()->dataRelation !== '') {
                $section->dataRelation = (string) $transection->dataBinding->attributes()->dataRelation;
            }

            $section->editMode = (string) $transection->attributes()->editMode;

            if (isset($transection->dataBinding->addRowEvent)) {
                $section->addRowEvent = (string) $transection->dataBinding->addRowEvent;
            } else {
                $section->addRowEvent = '';
            }

            foreach ($transection->dataBinding->children() as $name => $fld) {
                $myfield = NULL;
                if ($name === 'field' || $name === 'customField') {
                    if ($transection->dataRelation !== NULL && $section->dataRelation === 'OneToOne') {
                        if ((string) $fld->attributes()->control === 'Label') {
                            $myfield = $this->setDummy('cLabel', $fld);
                        } else {
                            $myfield = $this->setField($fld);
                        }
                    } else {
                        $myfield = $this->setField($fld, TRUE);
                    }
                } else if ($name === 'dummy') {
                    if ($transection->dataRelation !== NULL && $section->dataRelation === 'OneToOne') {
                        $myfield = $this->setDummy('blank', $fld);
                    }
                } else if ($name === 'nextRow') {
                    if ($transection->dataRelation !== NULL && $section->dataRelation === 'OneToOne') {
                        $myfield = $this->setDummy('nextRow', $fld);
                    }
                } else if ($name === 'tranSection') {
                    $myfield = $this->setTran($fld);
                } else if ($name === 'cHTML') {
                    $myfield = $this->setHtml($fld);
                } else if ($name === 'cButton') {
                    $myfield = $this->setButton($fld);
                } else if ($name === 'cLink') {
                    $myfield = $this->setLink($fld);
                }
                if (!$myfield instanceof viewpartsection && $myfield !== NULL) {
                    if ($this->access_level === AccessLevels::READONLY) {
                        $myfield->options['readonly'] = 'true';
                    }
                    if ($this->access_level > AccessLevels::NOACCESS) {
                        array_push($section->fields, $myfield);
                    }
                } else {
                    if ($this->access_level > AccessLevels::NOACCESS && $myfield !== NULL) {
                        array_push($section->fields, $myfield);
                    }
                }
                if ((string) $fld->attributes()['data-fc-dependent']) {
                    $section->fc_fields[(string) $fld->attributes()['id']] = (string) $fld->attributes()['data-fc-dependent'];
                }
            }

            $visiblefor = (string) $transection->attributes()['cdata-visible-on'];
            $visiblevalue = (string) $transection->attributes()['cdata-visible-value'];
            $opts = array();
            if (isset($visiblefor) && $visiblefor != '') {
                if (isset($visiblevalue) && $visiblevalue != '') {
                    $enablevalue;
                    $opts['data-bind'] = '';
                    if ($visiblevalue == 'true' || $visiblevalue == 'false') {
                        $enablevalue = $visiblevalue;
                    } else {
                        $enablevalue = '\'' . strtolower($visiblevalue) . '\'';
                    }
                    $opts['data-bind'] = 'visible: coreWebApp.getvisTranCal($data' . ',$data.'
                            . $visiblefor . '(),\''
                            . $section->dataProperty . '\',' . $enablevalue . ')';
                } else {
                    $opts['data-bind'] = 'visible: coreWebApp.getvisTranCal($data' . ',$data.'
                            . $visiblefor . '(),\''
                            . $section->dataProperty . '\',true)';
                }
                $section->options = $opts;
            }
            if (isset($transection['mdata-event'])) {
                $section->mdata_events = $this->set_mdata((string) $transection['mdata-event']);
                $section->options['mdata-events'] = (string) $transection['mdata-event'];
            }
            return $section;
        }

        private function setDummy($name, $fld) {
            $field = new viewpartfield();
            $field->inputType = $name;
            $field->options = [];
            $field->label = ' ';
            if (isset($fld['size'])) {
                $field->size = $this->setSize($fld);
            }
            if (isset($fld['label'])) {
                $field->label = (string) $fld['label'] != '' ? (string) $fld['label'] : $name;
            }
            return $field;
        }

        private function setHtml($fld) {
            $field = new viewpartfield();
            $field->inputType = 'cHTML';
            $field->options = $fld;
            $field->label = ' ';
            return $field;
        }

        private function setSectionHeader($fld) {
            $field = new viewpartfield();
            $field->inputType = 'sectionHeader';
            if (isset($fld['label'])) {
                $field->label = (string) $fld['label'];
            } else {
                $field->label = '';
            }
            return $field;
        }

        private function setButton($fld) {
            $field = new viewpartfield();
            $field->inputType = 'cButton';
            if (isset($fld->attributes()->onClick) && (string) $fld->attributes()->onClick !== '') {
                $field->options['onclick'] = (string) $fld->attributes()->onClick;
            }
            if (isset($fld->attributes()->params) && (string) $fld->attributes()->params !== '') {
                $field->options['params'] = (string) $fld->attributes()->params;
            } else {
                $field->options['params'] = '';
            }
            $field->size = $this->setSize($fld);
            $field->label = (string) $fld->attributes()->label;
            $field->id = (string) $fld['id'];
            return $field;
        }

        private function setLink($fld) {
            $field = new viewpartfield();
            $field->inputType = 'cLink';
            if (isset($fld->attributes()->onClick) && (string) $fld->attributes()->onClick !== '') {
                $field->options['onclick'] = (string) $fld->attributes()->onClick;
            }
            if (isset($fld->attributes()->params) && (string) $fld->attributes()->params !== '') {
                $field->options['params'] = (string) $fld->attributes()->params;
            } else {
                $field->options['params'] = '';
            }
            $field->size = $this->setSize($fld);
            $field->label = (string) $fld->attributes()->label;
            $field->id = (string) $fld['id'];
            return $field;
        }

        private function setOptions($fld, $isTran = FALSE) {
            $opts = array();
            $opts['id'] = (string) $fld->attributes()->id;
            $opts['name'] = (string) $fld->attributes()->id;

            if (strtolower((string) $fld['isOptional']) == 'true') {
                $opts['data-validation-optional'] = 'true';
            }

            if ($fld->attributes()['validateMessage']) {
                $opts['data-validation-error-msg'] = (string) $fld->attributes()['validateMessage'];
            } else {
                $opts['data-validation-error-msg'] = (string) $fld->attributes()->label . ' is required.';
            }

            switch ((string) $fld->attributes()['type']) {
                case DataAdapter::PHPDATA_TYPE_INT:
                    $opts['data-validations'] = 'number';
                    if (isset($fld->attributes()['allowNegative'])) {
                        $opts['allowNegative'] = (string) $fld['allowNegative'];
                    }
                    if (isset($fld->attributes()['maxValue'])) {
                        $opts['maxValue'] = (string) $fld['maxValue'];
                    }
//                    $opts['data-validation-allowing']=  $this->setnumber($fld);
//                    if(!$fld->attributes()['validateMessage']){
//                        $opts['data-validation-error-msg']=(string)$fld->attributes()->label.' should be a number.';
//                    }
                    break;
                case DataAdapter::PHPDATA_TYPE_DECIMAL:
                    $opts['data-validations'] = 'decimal';
                    if (isset($fld->attributes()['scale'])) {
                        $opts['scale'] = $this->setNumericScale((string) ($fld->attributes()['scale']));
                    } else if (isset($fld->attributes()['subType']) && (string) $fld->attributes()['subType'] === 'rate') {
                        $opts['scale'] = \app\cwf\vsla\Math::$rateScale;
                    } else {
                        $opts['scale'] = \app\cwf\vsla\Math::$amtScale;
                    }
                    if (isset($fld->attributes()['allowNegative'])) {
                        $opts['allowNegative'] = (string) $fld['allowNegative'];
                    }
                    if (isset($fld->attributes()['maxValue'])) {
                        $opts['maxVal'] = (string) $fld['maxValue'];
                    }
//                    $opts['data-validation-allowing']=  $this->setnumber($fld). ',float';
//                    if(!$fld->attributes()['validateMessage']){
//                        $opts['data-validation-error-msg']=(string)$fld->attributes()->label.' should be a number.';
//                    }
                    break;
                case DataAdapter::PHPDATA_TYPE_DATE:
                    //$opts['data-validation']='date';
                    break;
                default:
                    $opts['data-validation'] = 'required';
                    break;
            }

            if ((string) $fld->attributes()['control'] == 'SmartCombo' || (string) $fld->attributes()['control'] == 'FC') {
                $opts['class'] = ' smartcombo form-control ';
                $opts['data-NamedLookup'] = (string) $fld->lookup->namedLookup;
                $opts['data-DisplayMember'] = (string) $fld->lookup->displayMember;
                $opts['data-ValueMember'] = (string) $fld->lookup->valueMember;
                $opts['notyetsmart'] = true;
                $opts['filterEvent'] = isset($fld->lookup->filterEvent) ?
                        (string) $fld->lookup->filterEvent : '';
                if ($fld->lookup->filter) {
                    $opts['data-Filter'] = (string) $fld->lookup->filter;
                } else {
                    $opts['data-Filter'] = '';
                }
                $opts['data-validation'] = 'required';
                $opts['data-validation-error-msg'] = 'Please select ' . (string) $fld->attributes()->label;
                if (isset($fld['exchDisable'])) {
                    $opts['exch-disable'] = strtolower((string) $fld['exchDisable']) == 'true' ? true : false;
                }
            } else if ((string) $fld->attributes()['control'] == 'SmartTextBox') {
                $opts['class'] = ' smarttextbox form-control ';
                $opts['data-NamedLookup'] = (string) $fld->lookup->namedLookup;
                $opts['data-DisplayMember'] = (string) $fld->lookup->displayMember;
                $opts['data-ValueMember'] = (string) $fld->lookup->valueMember;
                $opts['notyetsmart'] = true;
                $opts['filterEvent'] = isset($fld->lookup->filterEvent) ?
                        (string) $fld->lookup->filterEvent : '';
                if ($fld->lookup->filter) {
                    $opts['data-Filter'] = (string) $fld->lookup->filter;
                } else {
                    $opts['data-Filter'] = '';
                }
                $opts['data-validation'] = 'required';
                $opts['data-validation-error-msg'] = 'Please select ' . (string) $fld->attributes()->label;
            } else if ((string) $fld->attributes()['control'] == 'Date') {
                $opts['class'] = ' datetime form-control ';
                $tempdateformat = '';
                if (\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->hasSessionVariable('date_format')) {
                    $tempdateformat = \app\cwf\vsla\security\SessionManager::getSessionVariable('date_format'); //  'yyyy-mm-dd';
                } else {
                    $tempdateformat = 'dd/MM/yyyy';
                }
                $opts['data-validation-format'] = $tempdateformat;

                if ((string) $fld->attributes()['range']) {
                    if ((string) $fld->attributes()['range'] == 'finYear') {
                        $startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
                        $opts['start_date'] = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
                        $enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
                        $opts['end_date'] = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
                    }
                }
            } else if ((string) $fld->attributes()['control'] == 'CheckBox' && !$isTran) {
                $opts['class'] = ' ';
            } else {
                $opts['class'] = ' form-control ';
            }

            if ((string) $fld->computedField != NULL) {
                $opts['data-computed'] = $fld->attributes()->id . '_calculated';
                if (isset($fld->computedField['forceCalOnPost'])) {
                    $opts['forceCalOnPost'] = (string) $fld['forceCalOnPost'];
                }
            }
            if (strtolower((string) $fld['readOnly']) == 'true') {
                $opts['readonly'] = 'readonly';
                $opts['tabindex'] = '-1';
            } elseif ((string) $fld->attributes()['readOnly'] == 'OnEdit') {
                if (!$this->isNew) {
                    $opts['readonly'] = 'readonly';
                    $opts['data-validation-optional'] = 'true';
                    $opts['tabindex'] = '-1';
                }
            }

            if ((string) $fld->attributes()['control'] == 'CheckBox') {
                $opts['data-bind'] = 'checked: ' . $fld->attributes()->id;
                $opts['type'] = 'checkbox';
                if (!$isTran) {
                    $opts['label'] = (string) $fld->attributes()->label;
                }
                unset($opts['data-validation']);
            } else if ((string) $fld->attributes()['control'] == 'TextBox' && isset($opts['data-validations']) && $opts['data-validations'] === 'decimal') {
                $opts['data-bind'] = 'numericValue: ' . $fld->attributes()->id;
                $opts['type'] = 'text';
            } else if ((string) $fld->attributes()['control'] == 'Date') {
                $opts['data-bind'] = 'dateValue: ' . $fld->attributes()->id;
                $opts['type'] = 'text';
            } else if ((string) $fld->attributes()['control'] == 'Password') {
                $opts['type'] = 'password';
                $opts['data-bind'] = 'value: ' . $fld->attributes()->id;
            } else if ((string) $fld->attributes()['control'] == 'Hidden') {
                $opts['type'] = 'hidden';
            } else {
                $opts['data-bind'] = 'value: ' . $fld->attributes()->id;
                $opts['type'] = 'text';
            }

            if ((string) $fld->attributes()['maxLength']) {
                $opts['maxlength'] = (string) $fld->attributes()['maxLength'];
                $opts['data-validation'] = 'length';
                $opts['data-validation-length'] = '1-' . (string) $fld->attributes()['maxLength'];
                $opts['data-validation-error-msg'] = (string) $fld->attributes()->label . ' is required. max(' . (string) $fld->attributes()['maxLength'] . ')';
            } else {
                if (array_key_exists('data-validation-optional', $opts) && $opts['data-validation-optional'] !== 'true') {
                    if ($opts['data-validation'] === 'required') {
                        $opts['data-validation'] = 'length';
                        $opts['data-validation-length'] = 'max0';
                        $opts['data-validation-error-msg'] = 'Max Length not set.';
                    }
                }
            }

            if ((string) $fld->attributes()['data-fc-dependent']) {
                $opts['data-fc-dependent'] = (string) $fld->attributes()['data-fc-dependent'];
                //$opts['data-computed']=$fld->attributes()->id.'_calculated';
                if (!$isTran) {
                    $opts['data-bind'] .= ', visible:coreWebApp.ModelBo.' . $this->fc_field . '()!=0, enable:coreWebApp.ModelBo.' . $this->fc_field . '()!=0';
                } else {
                    $opts['data-bind'] .= ', visible:$parent.' . $this->fc_field . '()!=0, enable:$parent.' . $this->fc_field . '()!=0';
                }
            }

            if ($fld->getName() === 'customField') {
                $opts['CustomField'] = 'true';
                $opts['data-validation-optional'] = 'true';
                $opts['forceCalOnPost'] = 'true';
            }

            if ((string) $fld->attributes()['cdata-enable-on']) {
                if ((string) $fld->attributes()['cdata-enable-value']) {
                    $enablevalue;
                    if ((string) $fld->attributes()['cdata-enable-value'] == 'true' || (string) $fld->attributes()['cdata-enable-value'] == 'false') {
                        $enablevalue = (string) $fld->attributes()['cdata-enable-value'];
                    } else {
                        $enablevalue = '\'' . strtolower((string) $fld->attributes()['cdata-enable-value']) . '\'';
                    }
                    $opts['cdata-enable'] = 'enable: coreWebApp.getTranCal($data' . ',$data.'
                            . (string) $fld->attributes()['cdata-enable-on'] . '(),\''
                            . (string) $fld->attributes()['id'] . '\',' . $enablevalue . ')';
                    if ($opts['data-bind'] !== NULL && $opts['data-bind'] !== '') {
                        $opts['data-bind'] = $opts['data-bind'] . ', enable: coreWebApp.getTranCal($data' . ',$data.'
                                . (string) $fld->attributes()['cdata-enable-on'] . '(),\''
                                . (string) $fld->attributes()['id'] . '\',' . $enablevalue . ')';
                    } else {
                        $opts['data-bind'] = 'enable: coreWebApp.getTranCal($data' . ',$data.'
                                . (string) $fld->attributes()['cdata-enable-on'] . '(),\''
                                . (string) $fld->attributes()['id'] . '\',' . $enablevalue . ')';
                    }
                } else {
                    $opts['cdata-enable'] = 'enable: '
                            . (string) $fld->attributes()['cdata-enable-on'] . '($data)';
                    if ($opts['data-bind'] !== NULL && $opts['data-bind'] !== '') {
                        $opts['data-bind'] = $opts['data-bind'] . ', enable: '
                                . (string) $fld->attributes()['cdata-enable-on'] . '($data)';
                    } else {
                        $opts['data-bind'] = 'enable: '
                                . (string) $fld->attributes()['cdata-enable-on'] . '($data)';
                    }

//                    
//                    if($opts['data-bind']!==NULL && $opts['data-bind']!==''){
//                        $opts['data-bind']=$opts['data-bind'].', enable: coreWebApp.getTranCal($data'.',$data.'
//                                .(string)$fld->attributes()['cdata-enable-on'].'(),\''
//                                .(string)$fld->attributes()['id'].'\',true)';
//                    }else{
//                        $opts['data-bind']='enable: coreWebApp.getTranCal($data'.',$data.'
//                                .(string)$fld->attributes()['cdata-enable-on'].'(),\''
//                                .(string)$fld->attributes()['id'].'\',true)';
//                    }
                }
            }
            if ((string) $fld->attributes()['cdata-visible-on']) {
                if ((string) $fld->attributes()['cdata-visible-value']) {
                    $visiblevalue;
                    if ((string) $fld->attributes()['cdata-visible-value'] == 'true' || (string) $fld->attributes()['cdata-visible-value'] == 'false') {
                        $visiblevalue = (string) $fld->attributes()['cdata-visible-value'];
                    } else {
                        $visiblevalue = '\'' . strtolower((string) $fld->attributes()['cdata-visible-value']) . '\'';
                    }
                    $opts['cdata-visible'] = 'visible: coreWebApp.getTranCal($data' . ',$data.'
                            . (string) $fld->attributes()['cdata-visible-on'] . '(),\''
                            . (string) $fld->attributes()['id'] . '\',' . $visiblevalue . ')';
                    if ($opts['data-bind'] !== NULL && $opts['data-bind'] !== '') {
                        $opts['data-bind'] = $opts['data-bind'] . ', visible: coreWebApp.getTranCal($data' . ',$data.'
                                . (string) $fld->attributes()['cdata-visible-on'] . '(),\''
                                . (string) $fld->attributes()['id'] . '\',' . $visiblevalue . ')';
                    } else {
                        $opts['data-bind'] = 'visible: coreWebApp.getTranCal($data' . ',$data.'
                                . (string) $fld->attributes()['cdata-visible-on'] . '(),\''
                                . (string) $fld->attributes()['id'] . '\',' . $visiblevalue . ')';
                    }
                } else {
                    $opts['cdata-visible'] = 'visible: '
                            . (string) $fld->attributes()['cdata-visible-on'] . '($data)';
                    if ($opts['data-bind'] !== NULL && $opts['data-bind'] !== '') {
                        $opts['data-bind'] = $opts['data-bind'] . ', visible: '
                                . (string) $fld->attributes()['cdata-visible-on'] . '($data)';
                    } else {
                        $opts['data-bind'] = 'visible: '
                                . (string) $fld->attributes()['cdata-visible-on'] . '($data)';
                    }
                }
            }

            if ((string) $fld->attributes()['cdata-bind']) {
                if ($opts['data-bind'] !== NULL && $opts['data-bind'] !== '') {
                    $opts['data-bind'] = $opts['data-bind'] . ','
                            . (string) $fld->attributes()['cdata-bind'];
                } else {
                    $opts['data-bind'] = (string) $fld->attributes()['cdata-bind'];
                }
            }

            if ((string) $fld->attributes()['control'] === 'TextArea') {
                if ($fld['rows'] !== null) {
                    $opts['rows'] = (string) $fld['rows'];
                } else {
                    $opts['rows'] = 2;
                }
            }

            if (isset($fld->attributes()['smartText'])) {
                $opts['smarttext'] = (string) $fld->attributes()['smartText'];
            }

            if (isset($fld->attributes()['cEvent']) && isset($fld->attributes()['cMethod']) &&
                    (string) ($fld->attributes()['cEvent']) !== '' &&
                    (string) ($fld->attributes()['cMethod']) !== '') {
                $opts[(string) ($fld->attributes()['cEvent'])] = (string) ($fld->attributes()['cMethod']);
            }

            if ((string) $fld->attributes()['control'] === 'Hidden') {
                $opts['hidden'] = "True";
            }

            return $opts;
        }

        private function setnumber($fld) {
            $numopts = '';
            $max = (string) $fld->attributes()['maxValue'];
            $scale = (string) $fld->attributes()['scale'];
            $negate = (string) $fld->attributes()['allowNegative'];
            if ($max != \NULL) {
                $numopts = 'range[0;' . $max . ']';
            }
            if ($negate != NULL) {
                $numopts = $numopts . ',negative';
            }
            return $numopts;
        }

        private function setNumericScale($sc) {
            switch ($sc) {
                case 'rate':
                    return \app\cwf\vsla\Math::$rateScale;
                case 'qty':
                    return \app\cwf\vsla\Math::$qtyScale;
                case 'fc':
                    return \app\cwf\vsla\Math::$fcScale;
                default:
                    return \app\cwf\vsla\Math::$amtScale;
            }
        }

        private function setSize($fld) {
            $res;
            switch (strtolower((string) $fld['size'])) {
                case 'xl':
                    $res = 'col-md-12';
                    break;
                case 'l':
                    $res = 'col-md-9';
                    break;
                case 'm':
                    $res = 'col-md-6';
                    break;
                case 'ms':
                    $res = 'col-md-2';
                    break;
                case 'xs':
                    $res = 'col-md-1';
                    break;
                default :
                    if (is_numeric((string) $fld['size'])) {
                        $temp = intval((string) $fld['size']);
                        if ($temp > 12) {
                            $res = 'col-md-12';
                        } else if ($temp < 1) {
                            $res = 'col-md-1';
                        } else {
                            $res = 'col-md-' . $temp;
                        }
                    } else {
                        $res = 'col-md-3';
                    }
                    break;
            }
            return $res;
        }

        private function set_formoptions() {
            if (isset($this->xrootview->keyField)) {
                $this->keyField = (string) $this->xrootview->keyField;
            }
            $this->isNewAllowed = FALSE;
            if (isset($this->xrootview->newDocEnabled)) {
                $this->isNewAllowed = TRUE;
                if ($this->xrootview->newDocEnabled['wizard']) {
                    $this->newType = 'wizard';
                    $this->wizPath = (string) $this->xrootview->newDocEnabled['wizard'];
                    $this->wizStep = (string) $this->xrootview->newDocEnabled['step'];
                }
                if ($this->xrootview->newDocEnabled->beforeNewEvent) {
                    $this->beforeNew = (string) $this->xrootview->newDocEnabled->beforeNewEvent;
                }
                if ($this->xrootview->newDocEnabled->afterNewEvent) {
                    $this->afterNew = (string) $this->xrootview->newDocEnabled->afterNewEvent;
                }
                $this->newParams['DocType'] = isset($this->xrootview->newDocEnabled->docType) ?
                        (string) $this->xrootview->newDocEnabled->docType : '';
                foreach ($this->xrootview->newDocEnabled->param as $param) {
                    $this->newParams[(string) $param['name']] = (string) $param;
                }
            }
            if (isset($this->xrootview->deleteDocEnabled)) {
                $this->isDeleteAllowed = TRUE;
            } else {
                $this->isDeleteAllowed = FALSE;
            }
            if (isset($this->xrootview->unpostDisabled)) {
                $this->isUnpostAllowed = FALSE;
            } else {
                $this->isUnpostAllowed = TRUE;
            }
            $this->isPrintAllowed = FALSE;
            if (isset($this->xrootview->printView)) {
                $this->printParams = array();
                if (isset($this->xrootview->printView['rptOption']) &&
                        $this->xrootview->printView['rptOption'] !== NULL) {
                    $this->printXml = '../' . $this->modulePath . '/'
                            . (string) $this->xrootview->printView['rptOption'] . '.xml';
                    $this->isPrintAllowed = TRUE;
                    foreach ($this->xrootview->printView->rptParams->param as $param) {
                        if (isset($param['id']) && $param['id'] !== NULL) {
                            $this->printParams[(string) $param['id']] = (string) $param;
                        } else {
                            array_push($this->printParams, (string) $param);
                        }
                    }
                }
            }

            if ($this->access_level < AccessLevels::DATAENTRY) {
                $this->isNewAllowed = FALSE;
                $this->isDeleteAllowed = FALSE;
            }
            if ($this->access_level === AccessLevels::NOACCESS) {
                $this->isPrintAllowed = FALSE;
            }

            $this->clientJsCodes = array();
            if (isset($this->xrootview->clientJsCode)) {
                array_push($this->clientJsCodes, '@app/' . $this->modulePath . '/' . $this->xrootview->clientJsCode);
            }

            $clientcoderef = '';
            if (isset($this->xrootview->clientJsCodeRefs)) {
                foreach ($this->xrootview->clientJsCodeRefs->clientJsCodeRef as $clientJsCodeRef) {
                    $clientcoderef = str_replace('../', '@app/', (string) $clientJsCodeRef);
                    array_push($this->clientJsCodes, $clientcoderef);
                }
            }
        }

        private function set_mdata($mdata) {
            $result = array();
            $res = explode(',', $mdata);
            foreach ($res as $kv) {
                $temp = explode(':', $kv);
                if (count($temp) == 2) {
                    $result[$temp[0]] = $temp[1];
                }
            }
            return $result;
        }

        private function parseExtendedView($bo_id) {
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

    }

}
