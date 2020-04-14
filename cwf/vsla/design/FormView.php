<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\design;

include_once '../cwf/vsla/design/CommonTypes.php';

use app\cwf\vsla\design\RelationType;
use app\cwf\vsla\security\AccessLevels;

/**
 * Description of FormView
 *
 * @author girish
 */
class FormView extends CwFrameworkType {

    // FormView Attributes
    /** Gets the Form id */
    public $id = '';

    /** Gets one of the BusinessObject::TYPE_ constants */
    public $type = BusinessObject::TYPE_MASTER;

    /** Gets the Name of the Business Object bound to this form */
    public $bindingBO = '';

    /** Gets the module path of this form */
    public $modulePath = '';

    /** Gets the name of the form */
    public $formName = '';

    /** Gets the name of the summary form */
    public $summaryformName = '';

    /** Gets the params required to render the data */
    public $formParams = array();

    /** @var AccessLevels */
    public $accessLevel = AccessLevels::NOACCESS;
    // FormView Child Elements
    public $header = '';
    public $keyField = '';

    /** @var FormPrintView */
    public $printView;

    /** Mentions whether the print view is present for this FormView
     * @return boolean */
    public function printViewExists() {
        if (isset($this->printView)) {
            return true;
        } else {
            return false;
        }
    }

    public $newDocEnabled = false;

    /** @var NewDocParam */
    public $newDocParam;
    public $deleteDocEnabled = false;
    public $noRefreshOnClose = false;
    public $unpostDisabled = false;

    /**
     * applies to documents
     * indicates if the document can be closed
     * @var type boolean
     */
    public $archiveEnabled = false;
    public $clientJsCode = array();
    public $codeBehind;
    public $afterLoadEvent = '';
    public $afterPostEvent = '';
    public $afterSaveEvent = '';
    public $afterUnpostEvent = '';
    public $beforeSaveEvent = '';
    public $beforeCloseEvent = '';

    /** @var FormControlSection */
    public $controlSection;
    // Link to Help document
    public $helpLink = '';
    // DM File option availability
    public $dmFilesEnabled = false;

    /** @var DMFiles */
    public $dmFiles;

    // Base class overrides
    public function getType() {
        return self::FORM_VIEW;
    }

}

class FormPrintView {

    // Attributes
    public $rptOption = '';
    // Elements
    public $rptParams = [];
    public $rptEngine = ReportEngineType::JASPER;
    public $printOptions = [];
    public $exportOptions = [
        'pdf' => 'PDF',
        'ms-doc' => 'MS-Word/docx',
        'ms-xls' => 'MS-Excel/xlsx',
        'open-doc' => 'Open-Writer/odt',
        'open-calc' => 'Open-Calc/ods'
    ];

}

class FormControlSection {

    /** @var EditMode */
    public $editMode;

    /** @var FormDataBinding */
    public $dataBinding;

}

class FormDataBinding {

    // Attributes
    public $dataProperty = '';
    public $bindMethod = 'default';
    // Elements
    public $addRowEvent;
    public $crudOn = '$root';
    public $addFirst = 'false';

    public function addRowEventExists() {
        if (isset($this->addRowEvent)) {
            return true;
        } else {
            return false;
        }
    }

    /** @var IDataBindingItem[] */
    public $items = [];

}

class FormTranSection implements IDataBindingItem {

    public function getType() {
        return self::TYPE_TRAN_SECTION;
    }

    public $label = '';

    /** @var EditMode */
    public $editMode;
    public $editMethod;

    public function editMethodExists() {
        if (isset($this->editMethod)) {
            return true;
        } else {
            return false;
        }
    }

    public $beforeAddMethod;

    public function beforeAddMethodExists() {
        if (isset($this->beforeAddMethod)) {
            return true;
        } else {
            return false;
        }
    }

    public $beforeDeleteMethod;

    public function beforeDeleteMethodExists() {
        if (isset($this->beforeDeleteMethod)) {
            return true;
        } else {
            return false;
        }
    }

    public $afterDeleteMethod;

    public function afterDeleteMethodExists() {
        if (isset($this->afterDeleteMethod)) {
            return true;
        } else {
            return false;
        }
    }

    public $noColHeader = false;
    public $size;

    public function sizeExists() {
        if (isset($this->size)) {
            return true;
        } else {
            return false;
        }
    }

    public $fixedHeight;

    public function fixedHeightExists() {
        if (isset($this->fixedHeight)) {
            return true;
        } else {
            return false;
        }
    }

    public $fixedWidth;

    public function fixedWidthExists() {
        if (isset($this->fixedWidth)) {
            return true;
        } else {
            return false;
        }
    }
    public $tw;

    /** @var FormDataBinding */
    public $dataBinding;
    public $dataRelation = RelationType::ONE_TO_MANY;
    public $cdata_enable_on;

    public function cdataEnableOnExists() {
        if (isset($this->cdata_enable_on)) {
            return true;
        } else {
            return false;
        }
    }

    public $cdata_visible_on;

    public function cdataVisibleOnExists() {
        if (isset($this->cdata_visible_on)) {
            return true;
        } else {
            return false;
        }
    }

    public $mdata_events;

    public function mdataEventExists() {
        if (isset($this->mdata_events)) {
            return true;
        } else {
            return false;
        }
    }
    
    public $noRender = false;

}
