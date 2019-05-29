<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\design;
include_once '../cwf/vsla/design/CommonTypes.php';
use app\cwf\vsla\design\RelationType;

/**
 * Description of CollectionView
 *
 * @author girish
 */
class CollectionDesignView extends CwFrameworkType {
    
    // Attributes
    public $id = '';
    public $type = BusinessObject::TYPE_MASTER;
    public $bindingBO = '';
    public $editView = '';
        /** The ID field of the collection (generally the primary key of the table) */
    public $keyField;
    //Elements
    public $header = '';
    
    public $filter = [];
    public $clientJsCode = array();
    /** @var CollectionSection */
    public $collectionSection;
    
    public $newDocEnabled = false;
    /** @var NewDocParam */
    public $newDocParam;
    /** @var \app\cwf\vsla\render\CollectionViewOptions */
    public $option;
    
    public $afterLoadEvent = '';
           
    // Set override of standard collection query
    public $ovrrideClass = '';
    public $ovrrideMethod = '';
    
    public function getType() {
        return self::COLLECTION_VIEW;
    }
}

class CollectionSection {
    public $afterFetch = '';
    /** Dataconnect Connection Type constant */
    public $connectionType;
    /** @var SqlCommandType */
    public $sql;
    /** @var DisplayFieldType[] */
    public $displayFields = [];
    /** The field name in the returned sql resultset that should be of type boolean. 
     *  If 'true', then that row is not editable in the collection
     * @var string */
    public $editNotAllowed;
    public function editNotAllowedExists() {
        if(isset($this->editNotAllowed)) {
            return true;
        } else {
            return false;
        }
    }
    /** @var CollectionDetailView */
    public $detailView;
    public function detailViewExists() {
        if(isset($this->detailView)) {
            return true;
        } else {
            return false;
        }
    }
}

class CollectionDetailView {
    
    /** @var app\cwf\vsla\design\CollectionDetailType */
    public $viewType;
    
    /** Path to action method. This must be a javascript function with a parameter in the definition.
        This method would be supplied with the current row data. 
        This method ideally returns a self contained html with model and view  */
    public $partialActionPath = '';
    public $partialAction = '';
    
    /** Path to view xml. This xml will be rendered using the system xml to view generator.
        The view will use the data from the collection query */
    public $partialViewPath = '';
    public $partialView = '';
    
    /** Collection of displayfields. This will be rendered as simple "key label: value" format.
        This view will use the data from the collection query 
        @var \app\cwf\vsla\dbd\displayField[]  */
    public $tranView = [];
}
