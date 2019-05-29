<?php

namespace app\cwf\vsla\render;

use yii\helpers\Html;
use app\cwf\vsla\design;
use app\cwf\vsla\security\AccessLevels;

include_once '../cwf/fwShell/models/MenuTree.php';

class FormHelper {

    public static function output_FormOptions(design\FormView $formView, $noActions = FALSE) {
        if (\app\cwf\vsla\security\SessionManager::getInstance()->isMobile()) {
            return self::output_MobFormActions($formView);
        }
        $rndformopt = ' <div class="row cformheader">
                            <div class="row">
                            <h3 id="hdr-form" class="col-md-4"><span id="span-hdr-form">'
                . $formView->header . '</span>'
                . self::addHelp($formView->helpLink)
                . self::addDMFile($formView)
                . ($formView->type == design\BusinessObject::TYPE_DOCUMENT ? self::addCommentInfo() : '')
                . ($formView->type == design\BusinessObject::TYPE_MASTER ? self::addAudittrail() : '')
                . '     </h3>
                            <div class="col-md-8 cformheaderbuttons"><div class="btn-toolbar" role="toolbar">';
        $rndformopt .= self::addclose($formView);
        if ($noActions == TRUE) {
            $rndformopt .= '    </div></div></div>';
            return $rndformopt;
        }
        if ($formView->type === \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
            $rndformopt .= self::addactions($formView, $formView->afterPostEvent, $formView->afterUnpostEvent);
        } else {
            if ($formView->newDocEnabled  && $formView->accessLevel === AccessLevels::DATAENTRY) {
                $rndformopt .= self::addnew($formView);
            }
            if ($formView->deleteDocEnabled) {
                $rndformopt .= '<div class="btn-group" role="group" style="float:right;">
                    <button id="btn-delete" name="btn-delete" class="btn btn-danger formoptions" 
                        data-bind="visible: $root.docSecurity.allowDelete, click: $root.Delete.bind(\'bo-form\')">
                        <span class="glyphicon glyphicon-trash"></span> Delete
                    </button>
                </div>';
            }
            if ($formView->printViewExists()) {
                $rndformopt .= '<div class="btn-group" role="group" style="float:right;">
                    <button id="btn-print" name="btn-print" class="btn btn-warning formoptions" 
                        data-bind="click: coreWebApp.showPrintMaster;">
                        <span class="glyphicon glyphicon-print"></span> Print
                    </button>
                </div>';
            }
        }
        $rndformopt .= self::addsave($formView);
        $rndformopt .= '    </div></div></div>'
                . '  <div class="row" id="doc_stage_info" style="padding-top: 10px;"
                                data-bind="visible: $root.docStageInfo().length>0 && $root.status() != 5 ">
                                <div class="stagewizard col-md-offset-1" style="margin-top: 5px;">
                                    <div class="stagewizard-row setup-panel" 
                                        data-bind="template: { name: \'tmpl-stagewiz\', foreach: docStageInfo }">
                                        
                                    </div>
                                    <script id="tmpl-stagewiz" type="text/html">
                                        <div class="stagewizard-step">
                                            <a href="#step" type="button" class="btn btn-primary btn-circle" data-bind="text: ($index()+1), css: { \'btn-success\': state() }"></a>
                                            <p data-bind="text: desc"></p>
                                        </div>
                                    </script>
                                </div>
                            </div>'
                . '</div>';
        return $rndformopt;
    }

    private static function output_MobFormActions(design\FormView $formView) {

        $eventArg = '{
            formName: \'' . $formView->formName . '\',
            afterPost: \'' . $formView->afterPostEvent . '\',
            afterUnpost: \'' . $formView->afterUnpostEvent . '\'
            }';
        $cl = $formView->noRefreshOnClose ? 'false' : 'true';
        $rndformopt = '<div class="row cformheader">
                            <div class="row">
                                <div class="col-md-1 col-xs-2" style="padding-left: 0;">
                                    <button id="cmdclose" class="btn btn-info" style="background-color:lightgrey;border-color:lightgrey;color:black;" 
                                    data-bind="click: function() { coreWebApp.closeMobDetail(' . $cl . '); }" name="close-button">
                                        <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <h3 class="col-md-4 col-xs-8" style="text-align:center;">' . $formView->header . '
                                </h3>
                                <div class="col-md-1 col-xs-2 cformheaderbuttons" style="margin: 0;">
                                    <div class="btn-toolbar" role="toolbar">
                                        <div class="btn-group" role="group" style="float:right;">
                                            <button id="cmdsave" class="btn btn-primary" 
                                            data-bind="visible: $root.docSecurity.allowSave && $root.__editMode(), click: coreWebApp.DocSave.bind($data, '
                . $eventArg . ')" name="cmdsave" type="submit">
                                                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
                                            </button>
                                        </div>    
                                    </div>
                                </div>
                            </div>
                        </div>';
        return $rndformopt;
    }

    private static function addHelp($helpLink) {
        $helpbtn = '';
        if ($helpLink != '') {
            $helpbtn = '<button id="hlink" name="hlink" class="btn btn-sm btn-default"
                            style="margin-left: 15px;border:none;padding:0;font-size:18px;" 
                            type="button" onclick="coreWebApp.openHelp(\'' . $helpLink . '\')">
                            <span class="glyphicon glyphicon-question-sign" style="color:darkgray;" aria-hidden="true"></span>
                        </button>';
        }
        return $helpbtn;
    }

    private static function addsave($formView) {
        $eventArg = '{
            formName: \'' . $formView->formName . '\',
            afterPost: \'' . $formView->afterPostEvent . '\',
            afterUnpost: \'' . $formView->afterUnpostEvent . '\'
            }';
        $res = '<div class="btn-group" role="group" style="float:right;">
                    <button id="cmdsave" class="btn btn-primary formoptions" 
                        data-bind="visible: $root.docSecurity.allowSave && $root.__editMode(), click: coreWebApp.DocSave.bind($data, ' . $eventArg . ')"
                        name="save-button" type="submit">
                        <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Save
                    </button>
                </div>
                <div class="btn-group" role="group" style="float:right;">
                    <button id="cmdedit" class="btn btn-success formoptions" 
                        data-bind="visible: !$root.__editMode() && $root.docSecurity.allowSave, click: coreWebApp.toggleEdit"
                        name="edit-button" type="button">
                        <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span> Edit
                    </button>
                </div>';
        return $res;
    }

    private static function addactions(design\FormView $formView) {
        $eventArg = '{
            formName: \'' . $formView->formName . '\',
            afterPost: \'' . $formView->afterPostEvent . '\',
            afterUnpost: \'' . $formView->afterUnpostEvent . '\'
            }';
        $res = '<div class="btn-group" role="group" style="float:right;">
                    <button id="btn-action" name="btn-action" class="btn btn-info dropdown-toggle formoptions" 
                        data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <span class="glyphicon glyphicon-cog" aria-hidden="true"></span> Actions <span style="margin-left:5px;" class="caret"></span>
                    </button>
                    <ul class="dropdown-menu" style="font-family: initial;">
                        <li><a href="#" id="btn-send" name="btn-send" 
                            data-bind="visible: $root.docSecurity.allowSend() && !$root.__editMode(), click: coreWebApp.DocSendTo.bind($data, ' . $eventArg . ')">
                            <span class="glyphicon glyphicon-share" data-bind="text: $root.docSecurity.next_stage_desc">  Send For Approval</span></a>
                        </li>
                        <li><a href="#" id="btn-approve" name="btn-approve" data-bind="visible: $root.docSecurity.allowApprove() && !$root.__editMode(), click: coreWebApp.DocApproveTo.bind($data, ' . $eventArg . ')">
                            <span class="glyphicon glyphicon-check" data-bind="text: $root.docSecurity.next_stage_desc">  Approve And Send</span></a>
                        </li>
                        <li><a href="#" id="btn-assign" name="btn-assign" data-bind="visible: $root.docSecurity.allowAssign() && !$root.__editMode(), click: coreWebApp.DocAssignTo.bind($data, ' . $eventArg . ')">
                            <span class="glyphicon glyphicon-check">  Assign to User</span></a>
                        </li>
                        <li><a href="#" id="btn-post" name="btn-post" data-bind="visible: $root.docSecurity.allowPost() && !$root.__editMode(), click: coreWebApp.DocPost.bind($data, ' . $eventArg . ')">
                                <span class="glyphicon glyphicon-check" data-bind="text: $root.docSecurity.next_stage_desc">  Post</span></a>
                        </li>
                        <li><a href="#" id="btn-reject" name="btn-reject" data-bind="visible: $root.docSecurity.allowReject() && !$root.__editMode(), click: coreWebApp.DocReject.bind($data, ' . $eventArg . ')">
                            <span class="glyphicon glyphicon-thumbs-down" data-bind="text: $root.docSecurity.regress_stage_desc">  Reject</span></a>
                        </li>
                        <li><a href="#" id="btn-cancel" name="btn-cancel" data-bind="visible: $root.__editMode(), click: coreWebApp.cancelEdit">
                            <span class="glyphicon glyphicon-floppy-remove">  Cancel Edit</span></a>
                        </li>
                        <li><a href="#" id="btn-unpost" name="btn-unpost" data-bind="visible: $root.docSecurity.allowUnpost, click: coreWebApp.DocUnpost.bind($data, ' . $eventArg . ')">
                             <span class="glyphicon glyphicon-floppy-remove">  Unpost</span></a>
                        </li>
                        <li role="separator" class="divider" data-bind="visible: coreWebApp.HasWfAction()"></li>';
        if ($formView->deleteDocEnabled) {
            $res .= '   <li><a href="#" id="btn-delete" name="btn-delete" data-bind="visible: $root.docSecurity.allowDelete, click: $root.Delete.bind(\'bo-form\')"><span class="glyphicon glyphicon-trash">  Delete</span></a></li>
                        <li role="separator" class="divider" data-bind="visible: $root.docSecurity.allowDelete"></li>';
        }
        if ($formView->archiveEnabled) {
            $archlabel = '';
            if ($formView->type == design\BusinessObject::TYPE_DOCUMENT) {
                $archlabel = 'Void';
            } elseif ($formView->type == design\BusinessObject::TYPE_MASTER) {
                $archlabel = 'Archive';
            }
            $res .= '   <li><a href="#" id="btn-archive" name="btn-archive" data-bind="visible: $root.docSecurity.allowArchive, click: $root.Archive.bind(\'bo-form\')"><span><i class="fa fa-archive" style="margin-right: 10px;"></i>  ' . $archlabel . '</span></a></li>
                        <li role="separator" class="divider" data-bind="visible: $root.docSecurity.allowArchive"></li>';
        }
        if ($formView->printViewExists()) {
            $res .= '   <li><a href="#" id="btn-print" name="btn-print" data-bind="click: coreWebApp.showPrint;"><span class="glyphicon glyphicon-print">  Print</span></a></li>
                        <li role="separator" class="divider"></li>';

//                $res .= '   <li><a href="#" id="btn-print" name="btn-print" data-bind="click: $root.docPrint.bind()"><span class="glyphicon glyphicon-print">  Print</span></a></li>
//                        <li>
//                            <a href="#" id="btn-export" name="btn-export" onclick="coreWebApp.docExport($(\'#btn-export-option\').val());"><span class="glyphicon glyphicon-save-file">  Export
//                            <select class="form-control" id="btn-export-option" style="margin-top: 5pt;">
//                                    <option value="pdf">PDF</option>
//                                    <option value="ms-doc">MS-Word/docx</option>
//                                    <option value="ms-xls">MS-Excel/xlsx</option>
//                                    <option value="open-doc">Open-Writer/odt</option>
//                                    <option value="open-calc">Open-Calc/ods</option>
//                                </select></span>
//                            </a>
//                        </li>
//                        <li role="separator" class="divider"></li>';
//            
        }
        if ($formView->newDocEnabled) {
            $res .= '   <li><a href="#" id="btn-new" name="btn-new" onclick="' . self::createnewlink($formView) . '"><span class="glyphicon glyphicon-plus">  New</span></a></li>';
        }
        $res .= '    </ul>
                </div>';
        return $res;
    }

    private static function addclose(design\FormView $formView) {
        $cl = $formView->noRefreshOnClose ? 'false' : 'true';
        $res = '<div class="btn-group" role="group" style="float:right;">
                    <button id="cmdclose" class="btn btn-info formoptions" 
                        style="background-color:lightgrey;border-color:lightgrey;color:black;"
                        data-bind="click: function() { coreWebApp.closeDetail(' . $cl . '); }"
                        name="close-button">
                        <span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span> Close
                    </button>
                </div>';
        return $res;
    }

    private static function addnew(design\FormView $formView) {
        $res = '<div class="btn-group" role="group" style="float:right;">
                    <button id="cmdnew" class="btn btn-default formoptions" 
                        onclick="' . self::createnewlink($formView) . '" 
                        name="new-button">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
                    </button>
                </div>';
        return $res;
    }

    public static function addprintdata(design\FormPrintView $printView) {
        if ($printView->rptEngine == design\ReportEngineType::PENTAHO) {
            $printurl = '?r=cwf%2FfwShell%2Freport%2Fvchreport';
        } else {
            $printurl = '?r=cwf%2FfwShell%2Fjreport%2Fvchreport';
        }
        $res = '<div id="divprintdata" name="divprintdata" style="display:none;" printurl="' . $printurl . '">';
        $res .= '<input type="hidden" id="divp__csrf" name="divp__csrf" value="'
                . \Yii::$app->request->csrfToken . '">';
        $res .= '<input type="hidden" id="divp_xmlPath" name="divp_xmlPath" value="'
                . $printView->rptOption . '">';
        foreach ($printView->rptParams as $key => $value) {
            $res .= '<input type="hidden" id="divp_' . $key . '" name="divp_' . $key . '" data-bind="value:' . $value . '">';
        }
        $res .= '<iframe id="rptContainer" name="rptContainer" style="display:none;"></iframe>';
        $res .= '</div>';
        return $res;
    }

    public static function addExtendedPrint(design\FormPrintView $printView) {
        if ($printView->rptEngine == design\ReportEngineType::PENTAHO) {
            $printurl = '?r=cwf%2FfwShell%2Freport%2Fvchreport';
        } else {
            $printurl = '?r=cwf%2FfwShell%2Fjreport%2Fvchreport';
        }
        $exportOptions = '';
        foreach ($printView->exportOptions as $key => $value) {
            $exportOptions .= '<option value="' . $key . '">' . $value . '</option>';
        }
        $res = '<div id="divprintdata" name="divprintdata" style="display:none;" printurl="' . $printurl . '">';
        $res .= '<script type="application/javascript" src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/fwShell/views/jrpt_clientcode.js') . '"></script>';
        $res .= '<input type="hidden" id="noprint" name="noprint" value="false">';
        $res .= '<input type="hidden" id="divp__csrf" name="divp__csrf" value="'
                . \Yii::$app->request->csrfToken . '">';
        foreach ($printView->rptParams as $key => $value) {
            $res .= '<input type="hidden" id="divp_' . $key . '" name="divp_' . $key . '" data-bind="value:' . $value . '">';
        }
        $res .= '<div id="prnttype"><label class="control-label" style="width:10%; margin:8px 0 0 0;" id="type_label">Type</label>';
        $res .= Html::dropDownList('divp_xmlPath', $printView->rptOption, $printView->printOptions
                        , ['class' => 'form-control', 'style' => 'width:85%;float:right;', 'id' => 'divp_xmlPath']);
//        $res .= '</div><div id="prntopt" style="margin-top:30px;">' .
//                '<input type="radio" name="printact" style="margin-left:10px;" value="print" onclick="printoptchange()" checked><span id="prntlbl"> Print</span>' .
//                '</div><div id="exprtopt" style="margin-top:10px;display: none;">' .
//                '<input type="radio" name="printact" style="margin-left:10px;" value="export" onclick="printoptchange()"><span> Export to</span>
//                            <select id="btn-export-option" style="margin-left:10px;width:127px;">
//                                    ' . $exportOptions . '
//                                </select>' .
//                '</div><div id="mailopt" style="margin-top:10px;">' .
//                '<input type="radio" name="printact" style="margin-left:10px;" value="email" onclick="printoptchange()"><span id="emailrptlbl"> Email</span>' .
//                '<div id="mailopts" style="display: none;">
//                    <span style="margin-left:32px;">Send to</span><input id="mail_send_to" style="width:70%;margin-left:13px;margin-top:5px;"/>
//                    <span style="margin-left:32px; display: none;">CC to</span><input id="mail_cc_to" style="width:70%;margin-left:25px;margin-top:5px; display: none;"/>
//                    <span style="margin-left:32px;">Subject</span><input id="mail_subject" style="width:70%;margin-left:14px;margin-top:5px;"/>
//                 </div>' .
//                '</div><div style="margin-top:20px;">' .
//                '<a href="#" id="btn-print-cancel" onclick="$(\'#divprintdata\').dialog(\'destroy\');" name="btn-print-cancel" class="btn btn-default" style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>' .
//                '<a href="#" id="btn-print-ok" onclick="coreWebApp.printDialogSubmit()" name="btn-print-ok" class="btn btn-default" style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>' .
//                '</div>' .
//                '<script type="text/javascript">function printoptchange(){
//                    if($(\'input[name=printact]:checked\').val() == "email"){
//                        $("#mailopts").show();
//                    } else { $("#mailopts").hide(); }
//                }</script>' .
//                '<iframe id="rptContainer" name="rptContainer" style="display:none;"></iframe>' .
//                '</div>';
        $res .= '</div><div id="prntopt" style="margin-top:30px;">
                <input type="radio" name="printact" style="margin-left:10px;" value="print" onclick="printoptchange()" checked><span id="prntlbl"> Print</span>
                </div><div id="exprtopt" style="margin-top:10px;display: none;">
                <input type="radio" name="printact" style="margin-left:10px;" value="export" onclick="printoptchange()"><span> Export to</span>
                            <select id="btn-export-option" style="margin-left:10px;width:127px;">
                                    ' . $exportOptions . '
                                </select>
                </div><div id="mailopt" style="margin-top:10px;">
                    <input type="radio" name="printact" style="margin-left:10px;" value="email" onclick="printoptchange()"><span id="emailrptlbl"> Email</span>
                </div><div style="margin-top:20px;">
                <a href="#" id="btn-print-cancel" onclick="$(\'#divprintdata\').dialog(\'destroy\');" name="btn-print-cancel" class="btn btn-default" style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
                <a href="#" id="btn-print-ok" onclick="coreWebApp.printDialogSubmit()" name="btn-print-ok" class="btn btn-default" style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
                </div>
                <script type="text/javascript">function printoptchange(){
                    if($(\'input[name=printact]:checked\').val() == "email"){
                        $("#expopt").hide();
                    } else if ($(\'input[name=printact]:checked\').val() == "export") {
                        $("#expopt").show();
                        $("#mailopts").hide();
                    } else {
                        $("#expopt").hide();
                        $("#mailopts").hide();
                    }
                }</script>
                    <div id="divmaildata" name="divmaildata" style="display:none;" class="col-md-12" printurl="?r=cwf%2FfwShell%2Fjreport">
        <div class="col-md-12">
            <span class="col-md-3" style="margin-top: 5px;">Send to</span>
            <input id="mail_send_to" class="col-md-9" style="margin-bottom: 5px;"/>
            <span class="col-md-3" style="margin-top: 5px; display: none;">CC to</span>
            <input id="mail_cc_to" class="col-md-9" style="margin-bottom: 5px; display: none;"/>
            <span class="col-md-3" style="margin-top: 5px;">Subject</span>
            <input id="mail_subject" class="col-md-9" style="margin-bottom: 5px;"/>
            <span class="col-md-3" style="margin-top: 5px;">Message</span>
            <textarea rows="3" id="mail_body" class="col-md-9" style="margin-bottom: 5px;"/>
        </div>
        <div class="col-md-12">
                <a href="#" id="btn-mail-cancel" onclick="$(\'#divmaildata\').dialog(\'destroy\');" name="btn-mail-cancel" class="btn btn-default" 
                   style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
                <a href="#" id="btn-mail-ok" onclick="coreWebApp.docPrintMail();" name="btn-mail-ok" class="btn btn-default" 
                   style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
            </div>
        </div>
                <iframe id="rptContainer" name="rptContainer" style="display:none;"></iframe>
                </div>';

        return $res;
    }

    private static function createnewlink(design\FormView $formView) {
        $keytype = NULL;
        $clicklink = NULL;

        if ($formView->newDocEnabled) {
            $keytype = ($formView->newDocParam->docType == '' ?
                    NULL : $formView->newDocParam->docType);
        }

        if ($keytype === NULL) {
            $clicklink = Html::encode(
                            'coreWebApp.onNewClick(\'?r=' . str_replace('@app/', '', $formView->modulePath) .
                            '/form&formName=' . $formView->formName .
                            '&formParams=' . '{"' . $formView->keyField . '": -1}' . '\',\'details\',\'contentholder\''
                            . (($formView->newDocParam->beforeNewEvent != '') ? (',\'' . $formView->newDocParam->beforeNewEvent . '\'') : ',null')
                            . (($formView->newDocParam->afterNewEvent != '') ? (',\'' . $formView->newDocParam->afterNewEvent . '\'') : ',null')
                            . (($formView->afterLoadEvent != '') ? (',\'' . $formView->afterLoadEvent) . '\'' : ',null')
                            . ');');
        } else {
            $clicklink = Html::encode(
                            'coreWebApp.onNewClick(\'?r=' . str_replace('@app/', '', $formView->modulePath) .
                            '/form&formName=' . $formView->formName .
                            '&formParams=' . '{"' . $formView->keyField . '": -1,"doc_type":"' . $keytype . '"}' . '\',\'details\',\'contentholder\''
                            . (($formView->newDocParam->beforeNewEvent != '') ? (',\'' . $formView->newDocParam->beforeNewEvent . '\'') : ',null')
                            . (($formView->newDocParam->afterNewEvent != '') ? (',\'' . $formView->newDocParam->afterNewEvent . '\'') : ',null')
                            . (($formView->afterLoadEvent != '') ? (',\'' . $formView->afterLoadEvent) . '\'' : ',null')
                            . ');');
        }

        if ($formView->newDocParam->haswizard()) {
            $clicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . str_replace('@app/', '', $formView->modulePath) .
                            '/form/wizard&formName=' . $formView->newDocParam->wizardPath .
                            '&step=' . $formView->newDocParam->wizardStep . '\',\'details\',\'contentholder\');');
        }
        $clicklink .= ' return false;';
        return $clicklink;
    }

    public static function output_DMFile_form(design\DMFiles $dmFiles) {
        $res = '<form id="fileupload" method="POST" enctype="multipart/form-data" onsubmit="return coreWebApp.uploadFile();">
        <table id="attachedFileList" role="presentation" class="table table-hover table-condensed"><tbody class="files"></tbody></table>
        <table id="attachingFileList" role="presentation" class="table table-hover table-condensed"><tbody class="files"></tbody></table>
        <div class="row fileupload-buttonbar ">
            <div class="" style="">
                <button id="btnuploadfile" name="btnuploadfile" type="submit" class="btn btn-primary start" style="float:right;display:none;">
                    <i class="glyphicon glyphicon-upload"></i>
                    <span>Upload</span>
                </button>
                <span class="btn btn-success fileinput-button" style="float:right;margin-right:10px;">
                    <i class="glyphicon glyphicon-plus"></i>
                    <span>Add File</span>
                    <input type="file" name="files[]" id="cfile"'
                . ($dmFiles->multipleFiles == true ? ' multiple' : '') . '>
                </span>
                <!--<button type="reset" class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                </button>
                <button type="button" class="btn btn-danger delete">
                    <i class="glyphicon glyphicon-trash"></i>
                </button>
                <input type="checkbox" class="toggle">
                <span class="fileupload-process"></span>-->
            </div>
            <!--<div class="col-lg-5 fileupload-progress fade">
                <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-success" style="width:0%;"></div>
                </div>
                <div class="progress-extended">&nbsp;</div>
            </div>-->
        </div>  
    </form>';
        return $res;
    }

    public static function addDMFile(design\FormView $formView) {
        $clip = '';
        if ($formView->dmFilesEnabled) {
            $clip = '<button id="dmclip" name="dmclip" class="btn btn-sm btn-default"
                            style="margin-left: 15px;border:none;padding:0;font-size:18px;" 
                            type="button" onclick="coreWebApp.showDMForm();" data-bind="visible: coreWebApp.ModelBo.__doc_id()!=-1" >
                            <span class="glyphicon glyphicon-paperclip" style="color:darkgray;transform: rotate(135deg);" aria-hidden="true">
                                <span id="attcnt" class="rw-number-notification" style="display:none;transform: rotate(-135deg); top: 3px; right: 14px;">3</span>
                            </span>
                        </button>';
        }
        return $clip;
    }

    private static function addCommentInfo() {
        $cmtbtn = '<button id="hlink" name="hlink" class="btn btn-sm btn-default"
                            style="margin-left: 15px;border:none;padding:0;font-size:18px;" 
                            type="button" onclick="coreWebApp.openComments()">
                            <span class="glyphicon glyphicon-comment" style="color:darkgray;" aria-hidden="true">
                                <span id="cmtcnt" class="rw-number-notification" style="display:none;"></span>
                            </span>
                        </button>';
        return $cmtbtn;
    }

    public static function addComments() {
        $comments = '<script type="text/html" id="cmt-template">
                        <div style="border-bottom:1px solid lightgrey;margin-top:5px;">
                            <strong>
                                <span id="doc_action_desc" data-bind="text:doc_action_desc, style: { color: coreWebApp.setWFColor(doc_action())}"></span>
                            </strong> <i style="font-size:smaller;color:grey;">by</i> 
                            <span id="full_user_name" data-bind="text:full_user_name"></span>
                            <p style="margin-top:2px;"><i style="font-size:smaller;color:grey;"> on</i>
                                <span id="doc_sent_on" data-bind="text:coreWebApp.formatDateTime(doc_sent_on())" style="font-size:smaller;"></span></p>
                           <p style="margin-top:5px;"> <span id="doc_sender_comment" data-bind="text:doc_sender_comment"></span></p></div>
                     </script>';
        $comments .= '<div id="wfcomments" class="col-md-3" style="display:none;border-left:1px solid lightgrey;">
                            <h5 style="border-bottom:1px solid lightgrey;font-style:italic;color:grey;">
                                Workflow/Comments<i class="glyphicon glyphicon-time" style="float:right;cursor: pointer; cursor: hand;margin-top:-2px;" 
                                data-bind="visible: $root.docSecurity.allowAuditTrail"  onclick="coreWebApp.getDocATT()"></i></h5>
                            <div data-bind="template:{ name: \'cmt-template\', foreach: coreWebApp.ModelBo.docComments}" id="wfcommentsin"></div>
                        </div>';
        return $comments;
    }

    public static function addAudittrail() {
        $at = '<button style="border:none;padding-left:15px;padding-right:5px;background-color:white;color:grey;font-size:14px;" '
                . 'data-bind="visible: $root.docSecurity.allowAuditTrail" '
                . 'onclick="coreWebApp.getDocATT()" title="Audit Trail" id="cviewat" type="button">'
                . '<i class="glyphicon glyphicon-time"></i></button>';
        return $at;
    }

    public static function output_SummaryView($smry_template) {
        $content = '<div id="frm_summary" class="col-md-12 col-xs-12" style="border-bottom:1px solid grey;">
                            <input id="wf_ar_id" name="wf_ar_id" 
                                data-bind="value:coreWebApp.wf_userdocs.docdata.wf_ar_id" type="hidden">
                            <div class="row col-md-8 col-xs-12" style="margin-bottom:10px;margin-top:10px;">
                                <div id="div_wfar_11" class="col-md-6  col-xs-12">
                                    <strong><span id="apr_type_text" data-bind="text:coreWebApp.wf_userdocs.docdata.apr_type"></span></strong>
                                    <span id="doc_id_label" class="form-group" style="margin-left:25px;"> Doc#</span>
                                    <strong><span id="doc_id_text" data-bind="text:__doc_id"></span></strong>
                                </div>
                                <div id="div_wfar_12" class="col-md-6  col-xs-12">
                                    <span>Sent by </span>
                                    <strong><span id="docwf_user_from_name" 
                                        data-bind="text:coreWebApp.wf_userdocs.docdata.from_user"></span></strong>
                                    <span> On </span>
                                    <strong><span id="docwf_sent_time" 
                                        data-bind="datetimetext:coreWebApp.wf_userdocs.docdata.added_on"></span></strong>
                                </div>
                            </div>
                            <div class="row col-md-8 col-xs-12">
                                <div id="div_wfar_21" class="row" style="margin-bottom:15px;">
                                    <div class="col-md-6 col-xs-12">
                                    <span style="color:grey;">Sender Comments</span>
                                        <textarea type="text" id="docwf_userfrom_comments" name="docwf_userfrom_comments" 
                                        maxlength="500" rows="3" autocomplete="off" 
                                        data-validation="length" data-validation-length="1-500" tabindex="0"
                                        class="textarea form-control" disabled="" style="width:100%;margin-top:5px;"
                                        data-bind="value:coreWebApp.wf_userdocs.docdata.wf_desc"></textarea>
                                    </div>
                                    <div id="div_wfar_22" class="col-md-6  col-xs-12">
                                    <span style="color:grey;">Your comments</span>
                                        <textarea type="text" id="docwf_userto_comments" name="docwf_userto_comments" 
                                        maxlength="500" rows="3" autocomplete="off"
                                        data-validation="length" data-validation-length="1-500" tabindex="0" 
                                        class="textarea form-control" style="width:100%;margin-top:5px;"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row col-md-4 col-xs-12">
                                <button id="docwf_cmd_accept" name="docwf_cmd_accept" class="btn btn-success" type="button" 
                                    style="margin-top: 30px;" onclick="coreWebApp.wf_userdocs.setData(true)">
                                    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Approve
                                </button>
                                <button id="docwf_cmd_reject" name="docwf_cmd_reject" class="btn btn-danger" type="button" 
                                    style="margin-left: 40px;margin-top: 30px;"  onclick="coreWebApp.wf_userdocs.setData(false)">
                                    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Reject
                                </button>
                            </div>
                            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                                <ul id="brules" name="brules" style="color: #a94442;"></ul>
                            </div>
                    </div>';
        return $content;
    }

}
