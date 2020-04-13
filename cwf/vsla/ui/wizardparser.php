<?php

namespace app\cwf\vsla\ui {
    include_once 'viewparts.php';

    use app\cwf\vsla\data\DataAdapter;

    class wizardparser {

        private $xrootview;

        /** @var wizStep[] */
        public $xsteps;
        public $header;
        public $modulePath;
        public $formParams;
        public $currentStep, $nextStep, $prevStep;
        public $codeBehind;
        public $stepData;
        public $clientJsCodes;

        function __construct($rootview, $modulePath, $formParams, $step, $operation = NULL) {
            $this->xrootview = $rootview;
            $this->modulePath = $modulePath;
            $this->formParams = $formParams;
            if ($formParams !== NULL) {
                
            }
            $this->setSteps($step, null);
            if ($this->xrootview->codeBehind) {
                $codeClass = (string) $this->xrootview->codeBehind->className;
                $this->codeBehind = new $codeClass();
            }
            $this->initWizard();
        }

        private function initWizard() {
            $temppath = '../' . $this->modulePath . '/';
            $cnt = 0;
            $this->header = (string) $this->xrootview['name'];
            foreach ($this->xrootview->wizardStep as $wiz) {
                $stp = new wizStep();
                $stp->id = (string) $wiz['id'];
                $stp->path = \yii::getAlias($this->modulePath) . DIRECTORY_SEPARATOR . (string) $wiz->path . '.xml';
                $stp->xroot = simplexml_load_file($stp->path);
                $stp->header = (string) $stp->xroot->header;
                $stp->wizSection = $this->initStep($stp);
                $this->xsteps[(string) $wiz['id']] = $stp;
                $cnt++;
            }
            $fin = new wizStep();
            $fin->id = (string) $this->xrootview->postWizard['id'];
            $fin->path = \yii::getAlias($this->modulePath) . DIRECTORY_SEPARATOR . (string) $this->xrootview->postWizard->path . '.xml';
            $fin->xroot = simplexml_load_file($fin->path);
            $fin->final = true;
            $fin->finalPath = (string) $this->xrootview->postWizard->path;
            $fin->newparams = $this->getNewOptions($fin->path);
            $this->xsteps[(string) $this->xrootview->postWizard['id']] = $fin;


            $this->clientJsCodes = array();
            if (isset($this->xrootview->clientJsCode)) {
                array_push($this->clientJsCodes, $this->modulePath . DIRECTORY_SEPARATOR . $this->xrootview->clientJsCode);
            }

            $clientcoderef = '';
            if (isset($this->xrootview->clientJsCodeRefs)) {
                foreach ($this->xrootview->clientJsCodeRefs->clientJsCodeRef as $clientJsCodeRef) {
                    $clientcoderef = str_replace('../', '@app/', (string) $clientJsCodeRef);
                    array_push($this->clientJsCodes, $clientcoderef);
                }
            }
        }

        private function initStep(wizStep $step) {
            $sections = array();

            foreach ($step->xroot->sections->children() as $sec) {
                $res = new wizSection();
                $res->id = (string) $sec['id'];
                $res->header = (string) $step->xroot->header;
                if ($sec->getName() == 'collectionSection') {
                    if ($sec->connectionType->mainDB) {
                        $res->cnType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
                    } else {
                        $res->cnType = \app\cwf\vsla\data\DataConnect::COMPANY_DB;
                    }
                    $res->sql = $sec->sql;

                    $res->keyField = (string) $sec->keyField['id'];
                    if (isset($sec['bindMethod'])) {
                        $res->bindMethod = (string) $sec['bindMethod'];
                    }
                    if (isset($sec['renderEvent'])) {
                        $res->renderEvents[] = (string) $sec['renderEvent'];
                    }
                    $res->wizType = 'collection';

                    foreach ($sec->displayFields->displayField as $disp) {
                        $dfield = new wizDisplayField();
                        $dfield->columnName = (string) $disp['columnName'];
                        $dfield->displayName = (string) $disp['displayName'];
                        $dfield->editMode = isset($disp['editMode']) ?
                                (string) $disp['editMode'] : 'readOnly';
                        $dfield->fieldName = isset($disp['id']) ? (string) $disp['id'] : '';
                        $dfield->size = isset($disp['size']) ?
                                $this->setSize((string) $disp['size']) : 'col-md-1';
                        $dfield->sizenum = $this->getColweight($dfield->size);
                        $dfield->viewField = $this->setField($disp);
                        $res->fields[$dfield->columnName] = $dfield;
                    }
                } else if ($sec->getName() == 'formSection') {
                    if ($sec->connectionType->mainDB) {
                        $res->cnType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
                    } else {
                        $res->cnType = \app\cwf\vsla\data\DataConnect::COMPANY_DB;
                    }
                    $res->sql = $sec->sql;

                    $res->keyField = (string) $sec->keyField['id'];
                    $res->wizType = 'form';
                    foreach ($sec->displayFields->displayField as $disp) {
                        $dfield = new wizDisplayField();
                        $dfield->displayName = (string) $disp['displayName'];
                        $dfield->editMode = isset($disp['editMode']) ?
                                (string) $disp['editMode'] : 'readOnly';
                        $dfield->fieldName = isset($disp['id']) ? (string) $disp['id'] : '';
                        $dfield->size = isset($disp['size']) ?
                                $this->setSize((string) $disp['size']) : 'col-md-1';
                        $dfield->viewField = $this->setField($disp);
                        $dfield->defaultValue = isset($disp['defaultValue']) ? (string) $disp['defaultValue'] : '';
                        $res->fields[(string) $disp['id']] = $dfield;
                    }
                }
                $sections[$res->id] = $res;
            }
            return $sections;
        }

        public function setSteps($step, $operation) {
            if ($step !== NULL) {
                if ($operation === 'next') {
                    $this->prevStep = $step;
                    $this->currentStep = $this->setNextStep($step);
                    $this->nextStep = $this->setNextStep($this->currentStep);
                } elseif ($operation === 'prev') {
                    $this->nextStep = $step;
                    $this->currentStep = $this->setPrevStep($step);
                    $this->prevStep = $this->setPrevStep($this->currentStep);
                } else {
                    $this->currentStep = $step;
                    $this->nextStep = $this->setNextStep($step);
                    $this->prevStep = $this->setPrevStep($step);
                }
            }
        }

        public function setNextStep($step) {
            for ($i = 0; $i < count($this->xrootview->wizardStep); $i++) {
                if ((string) $this->xrootview->wizardStep[$i]['id'] === $step) {
                    if (($i + 1) < count($this->xrootview->wizardStep)) {
                        return (string) $this->xrootview->wizardStep[$i + 1]['id'];
                    }
                }
            }
            return (string) $this->xrootview->postWizard['id'];
        }

        public function setPrevStep($step) {
            for ($i = 0; $i < count($this->xrootview->wizardStep); $i++) {
                if ((string) $this->xrootview->wizardStep[$i]['id'] === $step) {
                    if (($i - 1) >= 0) {
                        return (string) $this->xrootview->wizardStep[$i - 1]['id'];
                    }
                }
            }
        }

        private function setField($fld, $isTran = FALSE) {
            $inputType = isset($fld->attributes()->control) ?
                    (string) $fld->attributes()->control : 'TextBox';
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
            } else {
                $field = new viewpartfield();
            }
            $field->id = (string) $fld->attributes()->id;
            $field->label = (string) $fld->attributes()->label;
            $field->type = (string) $fld->attributes()->type;
            $field->inputType = $inputType;
            if (strtolower((string) $fld->attributes()['isOptional']) == 'True') {
                $field->optional = TRUE;
            }

            if ($fld->attributes->editMode != NULL) {
                $field->editMode = $fld->attributes->editMode;
            } else {
                $field->editMode = 'Edit';
            }

            if ((string) $fld->computedField != NULL) {
                $field->calculated = TRUE;
                $field->formula = (string) $fld->computedField;
            }

            if ((string) $fld->attributes()['data-fc-dependent']) {
                $section->fc_fields[(string) $fld->attributes()->id] = (string) $fld->attributes()['data-fc-dependent'];
            }

            if ($fld->getName() === 'customField') {
                $field->isCustom = TRUE;
            } else {
                $field->isCustom = FALSE;
            }
            $field->size = $this->setSize((string) $fld['size']);
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

            if ((string) $fld->attributes()['range']) {
                if ((string) $fld->attributes()['range'] == 'finYear') {
                    if ($field->defaultValue == null) {
                        if (strtotime(date("Y-m-d", time())) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
                        } else if (strtotime(date("Y-m-d", time())) < strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
                        }
                    } else {
                        if (strtotime($field->defaultValue) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
                        } else if (strtotime($field->defaultValue) < strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))) {
                            $field->defaultValue = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
                        }
                    }
                }
            }
            if (strtolower((string) $fld['editMode']) === 'edit') {
                unset($field->options['readonly']);
            }
            return $field;
        }

        private function setOptions($fld, $isTran = FALSE) {
            $opts = array();
            $opts['id'] = (string) $fld->attributes()->id;
            $opts['name'] = (string) $fld->attributes()->id;

            if (strtolower((string) $fld->attributes()['isOptional']) == 'True') {
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
                    break;
                case DataAdapter::PHPDATA_TYPE_DATE:
                    break;
                default:
                    $opts['data-validation'] = 'required';
                    break;
            }

            if ((string) $fld->attributes()['control'] == 'SmartCombo' || (string) $fld->attributes()['control'] == 'FC') {
                $opts['class'] = ' smartcombo form-control ';
                $opts['data-NamedLookup'] = str_replace('../', '@app/', (string) $fld->lookup->namedLookup);
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
            }
            if (!isset($fld->attributes()['Edit']) ||
                    (string) $fld->attributes()['Edit'] !== 'Edit') {
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

            if ($fld->name === 'customField') {
                $opts['CustomField'] = 'true';
                $opts['data-validation-optional'] = 'true';
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

            return $opts;
        }

        private function setSize($size) {
            $res;
            switch (strtolower($size)) {
                case 'xl':
                    $res = 'col-md-12';
                    break;
                case 'l':
                    $res = 'col-md-9';
                    break;
                case 'm':
                    $res = 'col-md-6';
                    break;
                case 'xs':
                    $res = 'col-md-1';
                    break;
                case 'ms':
                    $res = 'col-md-2';
                    break;
                default :
                    $res = 'col-md-3';
                    break;
            }
            return $res;
        }

        public function processStepData($step, $data, $oldStepData) {
            $this->codeBehind->setData($step, $data, $oldStepData);
            //if(count($this->codeBehind->brokenrules)==0){
            $this->stepData = $this->codeBehind->getData();
            $this->formParams = $this->codeBehind->getData();
            //}
        }

        public function getParamValue($wizparam) {
            $parent = $this->stepData[(string) $wizparam['step']];
            $temp = (string) $wizparam;
            $res = null;
            if (gettype($parent) == 'array') {
                if (key_exists($temp, $parent)) {
                    $res = $parent[$temp];
                }
            } else if (property_exists($temp, $parent)) {
                $res = $parent->$temp;
            }
            return $res;
        }

        private function getval($parent, $temp) {
            $res = NULL;
            if (gettype($parent) == 'array') {
                if (count($parent) > 1) {
                    foreach ($parent as $par) {
                        $res = $this->getval($par, $temp);
                        if (isset($res)) {
                            break;
                        }
                    }
                } else {
                    if (key_exists($temp, $parent)) {
                        $res = $parent[$temp];
                    }
                }
            } else if (property_exists($parent, $temp)) {
                $res = $parent->$temp;
            }
            return $res;
        }

        function getNewOptions($mpath) {
            $cwFramework = simplexml_load_file($mpath);
            $boxml = $cwFramework->formView;
            $res = array();
            if (isset($boxml->keyField)) {
                $res[(string) $boxml->keyField] = -1;
            }
            if (isset($boxml->newDocEnabled)) {
                $res['doc_type'] = isset($boxml->newDocEnabled->docType) ?
                        (string) $boxml->newDocEnabled->docType : '';
                foreach ($boxml->newDocEnabled->param as $param) {
                    if ((string) $param['name'] === 'DocType') {
                        $res['doc_type'] = (string) $param;
                    } else {
                        $res[(string) $param['name']] = (string) $param;
                    }
                }
            }
            return $res;
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

        private function getColweight($size) {
            $sznum = str_replace('col-md-', '', $size);
            if (is_numeric($sznum)) {
                $temp = intval($sznum);
                if ($temp > 12) {
                    $res = 12;
                } else if ($temp < 1) {
                    $res = 1;
                } else {
                    $res = $temp;
                }
            } else {
                $res = 3;
            }
            return $res;
        }

    }

}
