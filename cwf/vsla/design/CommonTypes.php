<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\design;

/**
 * This includes the list of all classes that are Common accross various design objects
 * Always include this class after the namespace in each design type
 * @author girish
 */
interface IDataBindingItem {
    const TYPE_FIELD            = 'field';
    const TYPE_SECTION_HEADER   = 'sectionHeader';
    const TYPE_NEXTROW          = 'nextRow';
    const TYPE_DUMMY            = 'dummy';
    const TYPE_TRAN_SECTION     = 'tranSection';
    const TYPE_CLINK            = 'cLink';
    const TYPE_CBUTTON          = 'cButton';
    const TYPE_CUSTOM_FIELD     = 'customField';
    const TYPE_CHTML            = 'cHtml';

    public function getType();
}

interface IElementItem {    
    const TYPE_XDIV             = 'xdiv';
    const TYPE_XDIVEND          = 'xdivEnd';
    const TYPE_XTAB             = 'xtab';
    const TYPE_XTABEND          = 'xtabEnd';
    const TYPE_XTABPAGE         = 'xtabPage';
    const TYPE_XTABPAGEEND      = 'xtabPageEnd';
    const TYPE_CALLMETHOD       = 'callMethod';
    public function getType();
}

abstract class FieldSize {
    const XS_COL_1      = 'xs';
    const MS_COL_2      = 'ms';
    const S_COL_3       = 's';
    const M_COL_6       = 'm';
    const L_COL_9       = 'l';
    const XL_COL_12     = 'xl';
}

abstract class FieldType {
    const STRING    = 'string';
    const INT       = 'int';
    const DECIMAL   = 'decimal';
    const DATE      = 'date';
    const TIME      = 'time';
    const DATETIME  = 'datetime';
    const BOOL      = 'bool';
    const ARRAYVAL  = 'array';
}

class EditMode {
    public $allowAdd = false;
    public $allowEdit = false;
    public $allowDelete = false;
}

abstract class ControlType {
    const LABEL             = 'Label';
    const SMART_COMBO       = 'SmartCombo';
    const SIMPLE_COMBO      = 'SimpleCombo';
    const FC                = 'FC';
    const SMART_TEXT_BOX    = 'SmartTextBox';
    const DATE              = 'Date';
    const TIME              = 'Time';
    const CHECK_BOX         = 'CheckBox';
    const TEXT_AREA         = 'TextArea';
    const TEXT_BOX          = 'TextBox';
    const PASSWORD          = 'Password';
    const HIDDEN            = 'Hidden';
    const TOGGLE            = 'Toggle';
    const DATE_TIME_TEXT    = 'DateTimeText';
    const SMART_LIST_BOX    = 'SmartListBox';
    const CHECK_LIST        = 'CheckList';
    const SPAN              = 'Span';
    const MULTI_SELECT      = 'MultiSelect';
}

abstract class ReportEngineType {
    const PENTAHO           = 'pentaho';
    const JASPER            = 'jasper';
}

class sectionHeader implements IDataBindingItem {
    public $label = '';
    public function getType() {
        return self::TYPE_SECTION_HEADER;
    }
}

class NextRow implements IDataBindingItem {
    public $style;
    public function getType() {
        return self::TYPE_NEXTROW;
    }
}

class Dummy implements IDataBindingItem {
    public $style;
    public $size = FieldSize::S_COL_3;
    public function getType() {
        return self::TYPE_DUMMY;
    }
}

class CLink implements IDataBindingItem {
    public function getType() {
        return self::TYPE_CLINK;
    }
    
    // Attributes
    public $id = '';
    public $onClick = '';
    public $label = '';
    public $size = FieldSize::S_COL_3;
    public $cdata_enable_on;
    public function cdataEnableOnExists(){
        if(isset($this->cdata_enable_on)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cdata_visible_on;
    public function cdataVisibleOnExists(){
        if(isset($this->cdata_visible_on)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cdata_bind;
    public function cdataBindExists() {
        if(isset($this->cdata_bind)) {
            return true;
        }
        return false;
    }
}

class CButton extends CLink{    
    public $inline = false;
    public $icon;
    public $tooltip = '';
    public $nolabel = false;
    public $hasHeader = false;
    
    /** applicable to buttons 
     *  which are to be immune to Edit Flag 
     *  @var boolean
     */
    public $ignoreEdit = false;
    
    public function getType() {
        return self::TYPE_CBUTTON;
    }
    
    public function __construct() {
        $this->size = FieldSize::XS_COL_1;
    }
}

class CHtml implements IDataBindingItem{
    public function getType() {
        return self::TYPE_CHTML;
    }
    
    public $html = '';
}

interface IBaseParamItem {
    const TYPE_SESSION          = 'session';
    const TYPE_TEXT             = 'text';
    const TYPE_CURRENT_DATE     = 'currentDate';
    
    public function getType();
    
}

interface IReportParamItem extends IBaseParamItem {
    const TYPE_PRESET           = 'preset';
    const TYPE_DATE_FORMAT      = 'dateFormat';
    const TYPE_NUMBER_FORMAT    = 'numberFormat';
    
}

class BaseParamSession implements IReportParamItem {
    const SESSION_COMPANY_ID    = 'company_id';
    const SESSION_BRANCH_ID     = 'branch_id';
    const SESSION_FINYEAR       = 'finyear';
    const SESSION_YEAR_BEGIN    = 'year_begin';
    const SESSION_YEAR_END      = 'year_end';
    
    public function getType() {
        return self::TYPE_SESSION;
    }
    
    public $id = '';
    public $sessionType = self::SESSION_COMPANY_ID;
}

class BaseParamText implements IReportParamItem {
    public function getType() {
        return self::TYPE_TEXT;
    }
    
    public $id = '';
    public $text = '';
}

class BaseParamCurrentDate implements IReportParamItem {
    public function getType() {
        return self::TYPE_CURRENT_DATE;
    }
    
    public $id = '';
    public $offsetMonth = '';
    public $offsetDate = '';

}

class ReportParamPreset implements IReportParamItem {
    public function getType() {
        return self::TYPE_PRESET;
    }
    
    public $id = '';
}

class ReportParamDateFormat implements IReportParamItem {
    public function getType() {
        return self::TYPE_DATE_FORMAT;
    }
    
    public $id = '';
}

class ReportParamNumberFormat implements IReportParamItem {
    public function getType() {
        return self::TYPE_NUMBER_FORMAT;
    }
    
    public $id = '';
}

class FieldLookupType {
    public $valueMember = '';
    public $displayMember = '';
    public $namedLookup = '';
    public $filter = '';
    public $filterEvent = '';
}

class FieldOptionType {
    public $choices = [];
    public $defaultValue = 0;
}

class FieldScale {
    const AMT   = 'amt';
    const RATE  = 'rate';
    const QTY   = 'qty';
    const FC    = 'fc';
}

class FormField implements IDataBindingItem {
    public function getType() {
        return self::TYPE_FIELD;
    }
    
    // Attributes
    public $id = '';
    public $label = '';
    public $tranLabel = '';
    public $type = FieldType::STRING;
    public $control = ControlType::TEXT_BOX;
    public $size = FieldSize::S_COL_3;
    public $range = '';
    public $maxLength = 0;
    public $isOptional = false;
    public $readOnly;
    public $scale = 'amt';
    public $allowNegative = false;
    public $maxVal = 0;
    public $rows = 1;
    public $smartText = '';
    public $exchDisable = false;
    public $colspan = 1;
    public $forStatus = false;
    public $fwdAction = '';
    public $revAction = '';
    public $inline = false;
    public $class = '';
    public $style = '';
    public $multiple = false;
    public $placeholder = '';
    public $noRender = false;
        
    // Elements
    public $value;
    public function valueExists() {
        if(isset($this->value)) {
            return true;
        } else {
            return false;
        }
    }
    
    /** @var FieldLookupType */
    public $lookup;
    public function lookupExists() {
        if(isset($this->lookup)) {
            return true;
        } else {
            return false;
        }
    }
    
    /** @var FieldOptionType */
    public $options;
    public function optionsExists() {
        if(isset($this->options)) {
            return true;
        } else {
            return false;
        }
    }
    
    public $computedField;
    public $forceCalOnPost = FALSE;
    public function computedFieldExists() {
        if(isset($this->computedField)) {
            return true;
        } else {
            return false;
        }
    }
    
    public $data_fc_dependent;
    public function fcDpendencyExists(){
        if(isset($this->data_fc_dependent)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cdata_enable_on;
    public function cdataEnableOnExists(){
        if(isset($this->cdata_enable_on)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cdata_visible_on;
    public function cdataVisibleOnExists(){
        if(isset($this->cdata_visible_on)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cdata_bind;
    public function cdataBindExists(){
        if(isset($this->cdata_bind)){
            return true;
        } else {
            return false;
        }
    }
    
    public $cell_data_bind;
    public function cellDataBindExists(){
        if(isset($this->cell_data_bind)){
            return true;
        } else {
            return false;
        }
    }
    
    public $header_data_bind;
    public function headerDataBindExists(){
        if(isset($this->header_data_bind)){
            return true;
        } else {
            return false;
        }
    }
 
    public $mdata_events;
    public function mdataEventExists(){
        if(isset($this->mdata_events)){
            return true;
        } else {
            return false;
        }
    }
    
    public $select_all_event;
    public function selectAllExists(){
        if(isset($this->select_all_event)){
            return true;
        }else{
            return false;
        }
    }
    
    //temperory feature - only to be used for smart combo in reports
    public $on_change_event;
    public function onChangeExists(){
        if(isset($this->on_change_event)){
            return true;
        }else{
            return false;
        }
    }
    
    public $forConsolidated = FALSE;
    
    public $toggleOn = 'Yes';
    public $toggleOff = 'No';
}

class CustomFormField extends FormField {
    public function getType() {
        return self::TYPE_CUSTOM_FIELD;
    }
}

class Xdiv implements IElementItem {
    public $size = '12';
    public $id = '';
    public $class = '';
    public $style = '';
    public $colspan = 1;
    public $cdata_visible_on;
    public $cdata_bind;
    public function cdataVisibleOnExists(){
        if(isset($this->cdata_visible_on)){
            return true;
        }
        return false;
    }
    public function cdataBindExists() {
        if(isset($this->cdata_bind)) {
            return true;
        }
        return false;
    }
    public function getType() {
        return self::TYPE_XDIV;
    }
}

class XdivEnd implements IElementItem {
    public function getType() {
        return self::TYPE_XDIVEND;
    }
}

class Xtab implements IElementItem {
    public $size = '12';
    public $id = '';
    public $class = '';
    public $style = '';
    public $cdata_visible_on;
    public function cdataVisibleOnExists(){
        if(isset($this->cdata_visible_on)){
            return true;
        } else {
            return false;
        }
    }
    public function getType() {
        return self::TYPE_XTAB;
    }
}

class XtabEnd implements IElementItem {
    public function getType() {
        return self::TYPE_XTABEND;
    }
}

class XtabPage implements IElementItem {
    public $size = '12';
    public $id = '';
    public $class = '';
    public $style = '';
    public $tabid = '';
    public $label = '';
    public $isFirst = false;
    public $onClick = '';
    public $cdata_visible_on;
    public function cdataVisibleOnExists(){
        if(isset($this->cdata_visible_on)){
            return true;
        } else {
            return false;
        }
    }
    public function getType() {
        return self::TYPE_XTABPAGE;
    }
}

class XtabPageEnd implements IElementItem { 
    public function getType() {
        return self::TYPE_XTABPAGEEND;
    }
}

class CallMethod implements IElementItem {
    public $size = '12';
    public $id = '';
    public $class = '';
    public $style = '';
    public $methodName = '';
    public $methodOutput = '';
    public function getType() {
        return self::TYPE_CALLMETHOD;
    }
}

class SqlCommandType {
    public $command = '';
    public $params = [];
}

/** Type used by CollectionView to display collection columns */
class DisplayFieldType {
    public $columnName = '';
    public $displayName = '';
    public $format;
    public $scale = 'amt';
    public function formatExists() {
        if(isset($this->format)) {
            return true;
        } else {
            return false;
        }
    }
    public $size = 's';
    public $wrapIn;
    public function wrapInExists() {
        return isset($this->wrapIn);
    }
    public $style;
    public function styleExists() {
        return isset($this->style);
    }
    public $class;
    public function classExists() {
        return isset($this->class);
    }
}

abstract class DisplayFormatType {
    const DATE = 'Date';
    const AMOUNT = 'Amount';
}

abstract class CollectionDetailType {
    const PARTIALVIEW = 'PartialView';
    const PARTIALACTION = 'PartialAction';
    const TRANVIEW = 'TranView';
}

class NewDocParam {
    public $docType = '';
    public $beforeNewEvent = '';
    public $afterNewEvent = '';
    public $wizardPath = '';
    public $wizardStep = '';
    public function haswizard() {
        if($this->wizardPath != '' 
                && $this->wizardStep != '') {
            return true;
        } else {
            return false;
        }
    }
}

class DMFiles {
    public $multipleFiles = false;
}
