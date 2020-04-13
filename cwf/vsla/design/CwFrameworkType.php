<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\design;

/**
 * This is the abstract class that is inherited by all Cwframework choices
 *
 * @author girish
 */
abstract class CwFrameworkType {
    // Constants for possible choice in root elements
    const BUSINESS_OBJECT = 'businessObject';
    const COLLECTION_VIEW = 'collectionView';
    const LOOKUP = 'lookup';
    const REPORT_VIEW = 'reportView';
    const FORM_VIEW = 'formView';
    const CWF_CONFIG = 'cwfConfig';
    const ALLOC_VIEW = 'alloc';
    const WIZARD_VIEW = 'wizard';
    const DATASET_VIEW = 'datasetView';
    
    public function getType() {
        
    }
}
