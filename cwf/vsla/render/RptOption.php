<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\render;

/**
 * Contains the options required to generate a report
 * @author girish
 */
class RptOption {
    /**
     * The Report Name. Contains a .jrxml or .twig file name.
     * This file is relative to filePath
     * @var string Physical file name
     */
    public $rptName;
    
    /**
     * The Report File Path. A folder relative to the root web path.
     * This folder would be prepended to the report name while searching for Report File
     * @var string Physical file path (relative to web root)
     */
    public $rptPath;
    /**
     * An array of report parameters and filters selected by the user
     * @var array name-value pair of Parameters/Filters
     */
    public $rptParams = null;
    public $brokenRules;
    public $sent_to;
    public $cc_to;
    public $subject;
    public $reply_to;

}
