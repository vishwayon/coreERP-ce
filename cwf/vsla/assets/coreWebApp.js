// Declare coreWebApp Namespace
window.coreWebApp = {};
coreWebApp.utils = {};

//Fetch Model for Binding
(function (coreWebApp) {
    var ModelBo;
    var coreSessionID;
    var dateFormat;
    var ccySystem;
    var submitInProcess = false;
    coreWebApp.branch_gst_info;

    // GetModel
    function GetModel(formid, afterloadevent, after) {
        var boPath = $(formid).find('#bindingBO').val();
        $.ajax({
            url: boPath,
            type: 'GET',
            dataType: 'json',
            data: {params: $(formid).find('#formParams').val(), reqtime: new Date().getTime(), formName: $(formid).find('#formName').val()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'NOACCESS') {
                    toastmsg('error', 'Status', 'Requested data is not accessible to this user.', false);
                    return;
                }
                // Build the Model using mapping plug-in
                coreWebApp.ModelBo = ko.mapping.fromJS(jsonResult['boData']);
                coreWebApp.ModelBo.self = coreWebApp.ModelBo;
                coreWebApp.ModelBo.preLookupData = jsonResult['preLookupData'];
                coreWebApp.ModelBo.docSecurity = ko.mapping.fromJS(jsonResult['docSecurity']);
                coreWebApp.ModelBo.docStageInfo = ko.mapping.fromJS(jsonResult['docStageInfo']);
                coreWebApp.ModelBo.docComments = ko.mapping.fromJS(jsonResult['docComments']);
                coreWebApp.ModelBo.docArchiveStatus = ko.mapping.fromJS(jsonResult['docArchiveStatus']);
                coreWebApp.ModelBo.tranDef = [];
                coreWebApp.dmfiles = [];

                if (typeof after != 'undefined' && after != '') {
                    var func = new Function('{' + after + '();}');
                    func();
                }
                for (var i = 0; i < jsonResult['tranMetaData'].length; i++) {
                    var tranMetaData = jsonResult['tranMetaData'][i];
                    AddTran(tranMetaData['tranName'], tranMetaData['tranMeta']);
                }
                coreWebApp.ModelBo.addNewRow = AddNewRow;
                coreWebApp.ModelBo.removeRow = RemoveRow;

                // apply methods
                coreWebApp.ModelBo.BoPath = $(formid).find('#bindingBO').val();
                coreWebApp.ModelBo.Params = $(formid).find('#formParams').val();
                coreWebApp.ModelBo.Submit = Submit;
                coreWebApp.ModelBo.Delete = Delete;
                coreWebApp.ModelBo.Archive = Archive;
                coreWebApp.ModelBo.docPrint = docPrint;

                // look for computed fields and replace observables with computed
                ReplaceComputedBinding($(formid)[0]);

                // apply bindings
                ko.cleanNode($(formid)[0]);
                ko.applyBindings(coreWebApp.ModelBo, $(formid)[0]);
                setStatusInfo();
                if (coreWebApp.ModelBo.docComments() != null && coreWebApp.ModelBo.docComments().length > 0) {
                    $('#cmtcnt').html(coreWebApp.ModelBo.docComments().length).show();
                }
                SubscribeModelEvents($(formid)[0]);

                applysmartcontrols($(formid)[0]);
                $.validate();
                if (jsonResult['dmfiles'].length > 0) {
                    coreWebApp.setFileList(jsonResult['dmfiles']);
                    $('#attcnt').html(jsonResult['dmfiles'].length).show();
                }
                if (typeof afterloadevent != 'undefined' && afterloadevent != '') {
                    var func = new Function('{' + afterloadevent + '();}');
                    func();
                }
                coreWebApp.setform();
                if (!coreWebApp.ModelBo.docSecurity.allowSave()) {
                    coreWebApp.toastmsg('warning', 'Status', 'View only, modifications will not be saved', true);
                }
                
                // reset lookup cache
                coreWebApp.lookupCache.reset();
//                $('table input,table select,table textarea').attr('disabled',true);
            },
            error: function (err) {
                var errMsg = err.responseText === undefined ? err.message : err.responseText;
                toastmsg('error', 'Failed to Fetch Data', errMsg, false);
                stoploading();
                // Disable Save/Action buttons when Get Model caused errors
                $('#cmdsave').hide();
                $('#btn-action').hide();
            }
        });
    }

    // Submit the model back to the server
    /* options { 
     *      formName: "", 
     *      action: "S/A/R/P/U", 
     *      afterPost: "", 
     *      afterUnpost: "", 
     *      saveOnWarn: true/false, 
     *      wfOption: {
     *          user_id_to: -1,
     *          doc_sender_comment: ""
     *      }    
     * }
     */
    function Submit(opts) {
        if ($('#hkBeforeSaveEvent').length > 0) {
            var hkBeforeSaveEvent = $('#hkBeforeSaveEvent').val();
            if (typeof hkBeforeSaveEvent != 'undefined' && hkBeforeSaveEvent != '') {
                var func = new Function("opts", '{return ' + hkBeforeSaveEvent + '(opts);}');
                var hkBeforeSaveEventResult = func(opts);
                if (typeof (hkBeforeSaveEventResult) != 'undefined' && hkBeforeSaveEventResult == false) {
                    return false;
                }
            }
        }
        $('#cmdsave').focus();
        var data = ko.mapping.toJS(this, {ignore: ['__el']});
        var postPath = this.BoPath;
        var params = encodeURIComponent(this.Params);
        postPath += '&params=' + params;
        postPath += '&formName=' + opts.formName;
        postPath += '&action=' + opts.action;
        if (opts.saveOnWarn == true) {
            $('#cmdsave, #cmdpost, #cmddelete').removeAttr('disabled', 'disabled');
            postPath += '&savewithwarnings=1';
        }
        var validate_result = $('#bo-form').isValid();
        if (!validate_result) {
            toastmsg('warning', 'Missing inputs', 'Document data incomplete. Not submitted.', false);
            return;
        }

        // append workflow information
        if (typeof opts.wfOption != 'undefined') {
            data.__wf_user_id_to = opts.wfOption.user_id_to;
            data.__wf_doc_sender_comment = opts.wfOption.doc_sender_comment;
            data.__wf_next_stage_id = opts.next_stage_id;
        } else {
            data.__wf_user_id_to = -1;
            data.__wf_doc_sender_comment = "";
            data.__wf_next_stage_id = "";
        }

        if (submitInProcess) {
            // Submit in process Do not make new request
            toastmsg('warning', 'Duplicate Submit', 'Document submit in process. Duplicate submit not allowed.', false);
            return;
        } else {
            submitInProcess = true;
        }
        $.ajax({
            url: postPath,
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify(data),
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            jsonp: false,
            success: function (resultdata) {
                submitInProcess = false;
                var jsonResult = resultdata;
                if (jsonResult['SaveStatus'] === 'NOACCESS') {
                    toastmsg('info', 'Access Level', 'Requested data is not accessible to this user.', false);
                    return;
                }
                $('#brokenrules').html('');
                if (jsonResult['SaveStatus'] === 'OK') {
                    $('#divbrule').hide();
                    ko.mapping.fromJS(jsonResult['BOPropertyBag'], coreWebApp.ModelBo);
                    ko.mapping.fromJS(jsonResult['docSecurity'], coreWebApp.ModelBo.docSecurity);
                    if (typeof jsonResult['docStageInfo'] != 'undefined') {
                        ko.mapping.fromJS(jsonResult['docStageInfo'], coreWebApp.ModelBo.docStageInfo);
                    }
                    ko.mapping.fromJS(jsonResult['docComments'], coreWebApp.ModelBo.docComments);
                    ko.mapping.fromJS(jsonResult['docArchiveStatus'], coreWebApp.ModelBo.docArchiveStatus);
                    $('#brokenrules').html('');
                    coreWebApp.ModelBo.Params = JSON.stringify(jsonResult['Params']);
                    coreWebApp.ModelBo.preLookupData = jsonResult['preLookupData'];
                    ReplaceComputedBinding($('#bo-form'));
                    SubscribeModelEvents($('#bo-form'));
                    applysmartcontrols($('#bo-form'));
                    setStatusInfo();
                    coreWebApp.ModelBo.__editMode(false);
                    if (coreWebApp.ModelBo.docComments() != null && coreWebApp.ModelBo.docComments().length > 0) {
                        $('#cmtcnt').html(coreWebApp.ModelBo.docComments().length).show();
                    }
                    if (coreWebApp.dmfiles.length > 0) {
                        coreWebApp.setFileList(coreWebApp.dmfiles);
                        $('#attcnt').html(coreWebApp.dmfiles.length).show();
                    }
                    if (Object.prototype.hasOwnProperty.call(coreWebApp.ModelBo, 'status')) {
                        if (typeof opts.afterSave !== 'undefined') {
                            var func = opts.afterSave;
                            func();
                        }
                        if (coreWebApp.ModelBo.status() === 5) {
                            if (typeof opts.afterPost != 'undefined' && opts.afterPost != '') {
                                var func = new Function('{' + opts.afterPost + '();}');
                                func();
                            }
                        } else {
                            if (typeof opts.afterUnpost != 'undefined' && opts.afterUnpost != '') {
                                var func = new Function('{' + opts.afterUnpost + '();}');
                                func();
                            }
                        }
                    }
                    getPendingStatus();
                    toastmsg('success', 'Status', 'Successfully saved.', false);
                } else if (jsonResult['SaveStatus'] === 'WARNING') {
                    $('#cmdsave, #cmdpost, #cmddelete').attr('disabled', 'disabled');
                    toastmsg('warning', 'Save Failed', 'Doc has warnings', false);
                    var warnings = jsonResult['Warnings'];
                    var litems = '<strong> Warnings </strong><div style="margin-top:5px;">';
                    for (var i = 0; i < warnings.length; i++) {
                        litems += "<li>" + warnings[i] + "</li>";
                    }
                    litems += '</div>';
                    litems += '<div style="margin-top:5px;">' + addWarningCommands() + '</div>';
                    $('#brokenrules').append(litems);
                    $('#divbrule').show();
                } else {
                    toastmsg('warning', 'Save Failed', 'Doc has broken rules', false);
                    var brules = jsonResult['BrokenRules'];
                    var litems = '<strong>Broken Rules</strong><div style="margin-top:5px;">';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    litems += '</div>';
                    $('#brokenrules').append(litems);
                    $('#divbrule').show();
                }
            },
            error: function (err) {
                submitInProcess = false;
                var errMsg = err.responseJSON === undefined ? err.statusText : err.responseJSON.message;
                toastmsg('error', 'Failed to Fetch Data', errMsg, false);
                stoploading();
            }
        });
    }

    function addWarningCommands() {
        var pdatabind = $('#cmdsave').attr('data-bind');
        pdatabind = pdatabind.replace(')', ',true)');
        pdatabind = pdatabind.replace('click: $root', 'coreWebApp.ModelBo');
        pdatabind = pdatabind.replace('$data,', '');
        pdatabind = pdatabind.replace('.bind', '');
        var btnProceed = '<button id="cmdproceed" type="button" class="btn btn-primary formoptions" ' +
                'style="float:none;margin:0px;" onclick="' + pdatabind +
                '" name="proceed-button"> Proceed with warnings</button>';
        var btnwcancel = '<button id="cmdwcancel" type="button" class="btn btn-danger formoptions" ' +
                'style="float:none;margin-left:10px;" onclick="coreWebApp.cancelWarning()"' +
                ' name="proceed-button"> Cancel </button>';
        return btnProceed + btnwcancel;
    }

    function cancelWarning() {
        $('#divbrule').hide();
        $('#cmdsave, #cmdpost, #cmddelete').removeAttr('disabled', 'disabled');
    }
    coreWebApp.cancelWarning = cancelWarning;

    function toggleEdit() {
        if (coreWebApp.ModelBo.__editMode()) {
            coreWebApp.ModelBo.__editMode(false);
            // Temp fix for toggle control to handle edit flag
            $('#cboformbodyin').find('[data-toggle=toggle]').attr( "disabled", "disabled" );
        } else {
            coreWebApp.ModelBo.__editMode(true);
            // Temp fix for toggle control to handle edit flag
            $('#cboformbodyin').find('[data-toggle=toggle]').removeAttr("disabled");
        }
    }
    coreWebApp.toggleEdit = toggleEdit;

    function cancelEdit() {
        if (coreWebApp.ModelBo.__editMode()) {
            coreWebApp.ModelBo.__editMode(false);
            // Temp fix for toggle control to handle edit flag
            $('#cboformbodyin').find('[data-toggle=toggle]').attr( "disabled", "disabled" );
        }
    }
    coreWebApp.cancelEdit = cancelEdit;

    //delete docmaster
    function Delete(formid) {
        //if (confirm("Are you sure you want to delete?") === false) {
        var res = bs_prompt('error', 'Are you sure you want to delete?', function () {
            var deletePath = formid.BoPath;
            deletePath += '&params=' + formid.Params;
            deletePath += '&formName=' + $('#formName').val();
            $.ajax({
                url: deletePath,
                type: 'DELETE',
                dataType: 'json',
                beforeSend: function () {
                    startloading();
                },
                complete: function () {
                    stoploading();
                },
                success: function (result) {
                    var jsonResult = result;
                    if (jsonResult['SaveStatus'] === 'NOACCESS') {
                        toastmsg('error', 'Status', 'Requested data is not accessible to this user.', false);
                        return;
                    }
                    $('#brokenrules').html('');
                    if (jsonResult['SaveStatus'] === 'OK') {
                        toastmsg('success', 'Status', 'Doc deleted successfully', false);
                        coreWebApp.closeDetail(true);
                    } else {
                        toastmsg('warning', 'Delete Failed', 'Doc has broken rules', false);
                        var brules = jsonResult['BrokenRules'];
                        var litems = '<strong>Broken Rules</strong>';
                        for (var i = 0; i < brules.length; i++) {
                            litems += "<li>" + brules[i] + "</li>";
                        }
                        $('#brokenrules').append(litems);
                        $('#divbrule').show();
                    }
                },
                error: function (data) {
                    toastmsg('error', 'Server Error', data.responseText, true);
                    stoploading();
                }
            });
        });
    }

    //archive docmaster
    function Archive(formid) {
        var msg1 = 'Are you sure you want to archive?';
        var msg2 = 'Enter reason for archiving';
        var archaction = 'C';
        if (coreWebApp.ModelBo.docArchiveStatus() === true) {
            msg1 = 'Are you sure you want to unarchive?';
            msg2 = 'Enter reason for unarchiving';
            archaction = 'O';
        }
        var res = bs_prompt('error', msg1, function () {
            var up_reason = window.prompt(msg2, "");
            if (up_reason == null || up_reason == '') {
                return;
            }
            var archivePath = formid.BoPath;
            archivePath += '&params=' + formid.Params;
            archivePath += '&formName=' + $('#formName').val();
            archivePath += '&action=' + archaction;
            archivePath += '&msg=' + up_reason;
            $.ajax({
                url: archivePath,
                type: 'PUT',
                dataType: 'json',
                beforeSend: function () {
                    startloading();
                },
                complete: function () {
                    stoploading();
                },
                success: function (result) {
                    var jsonResult = result;
                    if (jsonResult['SaveStatus'] === 'NOACCESS') {
                        toastmsg('error', 'Status', 'Requested data is not accessible to this user.', false);
                        return;
                    }
                    $('#brokenrules').html('');
                    if (jsonResult['SaveStatus'] === 'OK') {
                        toastmsg('success', 'Status', 'Doc archived successfully', false);
                        coreWebApp.closeDetail(true);
                    } else {
                        toastmsg('warning', 'Archive Failed', 'Doc has broken rules', false);
                        var brules = jsonResult['BrokenRules'];
                        var litems = '<strong>Broken Rules</strong>';
                        for (var i = 0; i < brules.length; i++) {
                            litems += "<li>" + brules[i] + "</li>";
                        }
                        $('#brokenrules').append(litems);
                        $('#divbrule').show();
                    }
                },
                error: function (data) {
                    toastmsg('error', 'Server Error', data.responseText, true);
                    stoploading();
                }
            });
        });
    }

    function showPrint() {
        var nondoc = 'doc';
        if ($('#rpt_nondoc').length > 0) {
            nondoc = $('#rpt_nondoc').val();
        }
        if (nondoc == 'rpt_nondoc') {
            showprintdialog();
        } else {
            if (coreWebApp.ModelBo.__doc_id() == -1) {
                toastmsg('warning', 'Print', 'This document is not saved. Print failed.');
                return;
            }
            $.ajax({
                url: '?r=cwf/fwShell/main/getprintaccess',
                type: 'GET',
                dataType: 'json',
                data: {bo_id: coreWebApp.ModelBo.__bo(), doc_id: coreWebApp.ModelBo.__doc_id(), doc_status: coreWebApp.ModelBo.status()},
                beforeSend: function () {
                    startloading();
                },
                complete: function () {
                    stoploading();
                },
                success: function (result) {
                    if (result.print_access == true) {
                        showprintdialog();
                    } else if (coreWebApp.ModelBo.status() == 5) {
                        showprintdialog();
                        $('#exprtopt').hide();
                        $('#mailopt').hide();
                        $('#prntlbl').html(' Request Print');
                        $('#noprint').val('true');
                    } else {
                        toastmsg('warning', 'Print', 'Print limit exceeded for this document.');
                    }
                    if (result.export_access == true) {
                        $('#exprtopt').show();
                    } else {
                        $('#exprtopt').hide();
                    }
                    if (result.report_mail_access == true) {
                        $('#mailopt').show();
                    } else {
                        $('#mailopt').hide();
                    }
                },
                error: function (data) {
                    toastmsg('error', 'Server Error', data.responseText, true);
                    stoploading();
                }
            });
        }
    }
    coreWebApp.showPrint = showPrint;

    function showPrintMaster() {
        if (coreWebApp.ModelBo.__doc_id() == -1) {
            toastmsg('warning', 'Print', 'This document is not saved. Print failed.');
            return;
        }
        $.ajax({
            url: '?r=cwf/fwShell/main/getprintaccess',
            type: 'GET',
            dataType: 'json',
            data: {bo_id: coreWebApp.ModelBo.__bo(), doc_id: coreWebApp.ModelBo.__doc_id(), doc_status: 5},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (result) {
                if (result.print_access == true) {
                    showprintdialog();
                }
                if (result.export_access == true) {
                    $('#exprtopt').show();
                } else {
                    $('#exprtopt').hide();
                }
                if (result.report_mail_access == true) {
                    $('#mailopt').show();
                } else {
                    $('#mailopt').hide();
                }
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.showPrintMaster = showPrintMaster;

    function showprintdialog() {
        var pdialog = $("#divprintdata").dialog({
            autoOpen: false,
            modal: true,
            title: 'Print',
            resizable: true,
            width: 400,
            buttons: [],
            close: function () {
                ko.cleanNode($('#cdialog')[0]);
            },
            open: function () {
                $(this).closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .hide();
            }
        });
        pdialog.dialog("open").prev().css('background', 'white');
        pdialog.on("dialogclose", function (event, ui) {});
        $("#divprintdata").parents().children().find(".ui-dialog").css('z-index', '999');
        $("#divprintdata").parents().children().find(".ui-dialog").css('top', '120px');
        $("#divprintdata").parents().children().find(".ui-dialog").css('left', '32%');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
        $("#divprintdata").parents().children().find(".ui-dialog").css('z-index', '999');
        $("#divprintdata").parents().children().find(".ui-widget-header").css('border', 'none');
        $("#divprintdata").parents().children().find(".ui-widget-header").css('border-bottom', '1px solid teal');
        $("#divprintdata").parents().children().find(".ui-widget-header").css('border-radius', '0');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('line-height', '30px');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('font-size', '15px');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('color', 'teal');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('padding-left', '20px');
        $("#divprintdata").parents().children().find(".ui-dialog .ui-dialog-title").css('width', '150px');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").addClass('btn btn-default');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").css('background-color', 'lightgray');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").css('margin-right', '1em');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").css('margin-top', '3px');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").css('padding', '3px 12px');
        $("#divprintdata").parents().children().find(".ui-dialog-titlebar button").focus();
        $("#divprintdata").parents().children().find('.ui-dialog').find('#cboformbodyin').css('border-bottom', '0');
        $("#divprintdata").parents().children().find('.ui-dialog .ui-dialog-buttonpane').css('border', '0');
    }
    coreWebApp.showprintdialog = showprintdialog;

    function printDialogSubmit() {
        var print_choice = $('[name=printact]:checked').val();
        var req_print = $('#noprint').val();
        if (req_print == 'true') {
            docPrintRequest();
            $('#divprintdata').dialog('destroy');
        } else if (print_choice == 'print') {
            docPrint();
            $('#divprintdata').dialog('destroy');
        } else if (print_choice == 'export') {
            docExport($('#btn-export-option').val());
            $('#divprintdata').dialog('destroy');
        } else if (print_choice == 'email') {
            //docPrintMail();
            emailClick();
            $('#divprintdata').dialog('destroy');
        }
    }
    coreWebApp.printDialogSubmit = printDialogSubmit;

    function emailClick() {
        var pdata = new Object();
        $('#divprintdata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^divp_')) {
                var fldid = ($(this).attr('id')).replace('divp_', '');
                pdata[fldid] = $(this).val();
            }
        });
        $('#mailopts :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^mail_')) {
                var fldid = ($(this).attr('id')).replace('mail_', '');
                pdata[fldid] = $(this).val();
            }
        });
        if (typeof coreWebApp != typeof undefined) {
            if (typeof coreWebApp.ModelBo != typeof undefined) {
                if ('__bo' in coreWebApp.ModelBo) {
                    pdata['bo_id'] = coreWebApp.ModelBo.__bo();
                }
                if ('status' in coreWebApp.ModelBo) {
                    pdata['status'] = coreWebApp.ModelBo.status();
                }
            }
        }
        var jdata = JSON.stringify(pdata);
        $.ajax({
            url: '?r=cwf/fwShell/jreport/maildata',
            type: 'POST',
            data: {rptparams: jdata},
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata, status, jqXHR) {
                mailInfo = $.parseJSON(resultdata);
                cwf_jrpt.showemaildialog();
                if (mailInfo != null) {
                    if (mailInfo.hasOwnProperty('mail_send_to')) {
                        $('#mail_send_to').val(mailInfo.mail_send_to);
                    }
                    if (mailInfo.hasOwnProperty('mail_cc_to')) {
                        $('#mail_cc_to').val(mailInfo.mail_cc_to);
                    }
                    if (mailInfo.hasOwnProperty('mail_subject')) {
                        $('#mail_subject').val(mailInfo.mail_subject);
                    }
                    if (mailInfo.hasOwnProperty('mail_body')) {
                        $('#mail_body').val(mailInfo.mail_body);
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
    }
    coreWebApp.emailClick = emailClick;

    function docPrintMail() {
        var pdata = new Object();
        $('#divprintdata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^divp_')) {
                var fldid = ($(this).attr('id')).replace('divp_', '');
                pdata[fldid] = $(this).val();
            }
        });
        $('#mailopts :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^mail_')) {
                var fldid = ($(this).attr('id')).replace('mail_', '');
                pdata[fldid] = $(this).val();
            }
        });
        $('#divmaildata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^mail_')) {
                var fldid = ($(this).attr('id')).replace('mail_', '');
                pdata[fldid] = $(this).val();
            }
        });
        if (typeof coreWebApp != typeof undefined) {
            if (typeof coreWebApp.ModelBo != typeof undefined) {
                if ('__bo' in coreWebApp.ModelBo) {
                    pdata['bo_id'] = coreWebApp.ModelBo.__bo();
                }
                if ('status' in coreWebApp.ModelBo) {
                    pdata['status'] = coreWebApp.ModelBo.status();
                }
            }
        }
        var jdata = JSON.stringify(pdata);
        $.ajax({
            url: '?r=cwf/fwShell/jreport/mailreport',
            type: 'POST',
            data: {rptparams: jdata},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (result) {
                $('#divmaildata').dialog('destroy');
                toastmsg('success', 'Email', 'Email is sent for this document.');
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.docPrintMail = docPrintMail;

    function docPrintRequest() {
        $.ajax({
            url: '?r=cwf/fwShell/jreport/requestprint',
            type: 'GET',
            data: {doc_id: coreWebApp.ModelBo.__doc_id()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (result) {
                toastmsg('success', 'Print', 'Print request is sent for this document.');
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.docPrintRequest = docPrintRequest;

    function docPrint() {
        var printurl = $('#divprintdata').attr('printurl');
        var pdata = new Object();
        $('#divprintdata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^divp_')) {
                var fldid = ($(this).attr('id')).replace('divp_', '');
                pdata[fldid] = $(this).val();
            }
        });
        if ('__bo' in coreWebApp.ModelBo) {
            pdata['bo_id'] = coreWebApp.ModelBo.__bo();
        }
        if ('status' in coreWebApp.ModelBo) {
            pdata['status'] = coreWebApp.ModelBo.status();
        }
        var jdata = JSON.stringify(pdata);
        $.ajax({
            url: printurl,
            type: 'POST',
            jsonp: true,
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            data: {rptparams: jdata},
            success: function (result, status, jqXHR) {
                if (jqXHR.getResponseHeader("Output-Type") == "text/html") {
                    var printWindow = window.open('', '', 'height=0,width=0');
                    if (printWindow === null || typeof (printWindow) === 'undefined') {
                        toastmsg('warning', 'Info', 'Please enable pop-ups and try again', false);
                    } else if (typeof (printWindow) === 'BrowserWindowProxy') {

                    } else {
                        printWindow.focus();
                        printWindow.document.write(result);
                        printWindow.document.close();
                        printWindow.print();
                        printWindow.close();
                    }
                } else if (jqXHR.getResponseHeader("Output-Type") == "application/json") {
                    var rptInfo = $.parseJSON(result);
                    var pwin = window.open('');
                    if (pwin === null || typeof (pwin) === 'undefined') {
                        toastmsg('warning', 'Info', 'Please enable pop-ups and try again', false);
                        return;
                    }
                    var htmldoc = $('<html></html>');
                    var head = $('<head>' + rptInfo.PageStyle + '</head>');
                    htmldoc.append(head);
                    var data = rptInfo.Data;
                    // This should be a simple parent div to ensure that it does not take printer page space
                    var rptParent = $('<div id="rptParent" name="rptParent"></div>');
                    for (i = 0; i < rptInfo.PageCount; i++) {
                        var rptPage = $('<div id="rptPage' + i + '" class="print-format"></div>');
                        var rptContainer = $('<div id="t' + i + '"></div>');
                        var prop = 'Page' + i;
                        var pagelink = data[prop];
                        $.ajax({
                            async: false,
                            url: pagelink,
                            type: 'GET',
                            success: function (pagedata) {
                                var phtml = $(pagedata);
                                phtml.find('img').each(function () {
                                    $(this).attr('src', rptInfo.ReportRenderedPath.substring(1, rptInfo.ReportRenderedPath.length) + '/' + $(this).attr('src'));
                                });
                                var t = phtml.find('table[class=jrPage]');
                                t.attr('id', 'jrPage-' + i);
                                rptContainer.append(t);
                            }
                        })
                        rptPage.append(rptContainer);
                        rptParent.append(rptPage);
                        // set the last page margin to Zero.
                        // This would suppress the blank page being printed
                        if (i == rptInfo.PageCount - 1) {
                            rptPage.attr('style', "margin-bottom: 0px;");
                        }
                    }
                    var body = $('<body></body>');
                    body.append(rptParent);
                    if (!coreWebApp.detectIE()) {
                        var script = pwin.document.createElement('script');
                        script.type = 'text/javascript';
                        script.text = 'function pageLoaded() {' +
                                '     setTimeout(function() {' +
                                '        window.print(); ' +
                                '        window.close(); ' +
                                '     }, 500); ' +
                                '};' +
                                'window.onload = pageLoaded;';
                        body.append(script);
                    }
                    htmldoc.append(body);
                    pwin.document.write(htmldoc.html());
                    pwin.document.close();
                }
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }

    function docExport(exptype) {
        var printurl = $('#divprintdata').attr('printurl');
        if (exptype == 'pdf') {
            printurl += 'topdf';
        } else if (exptype == 'ms-doc') {
            printurl += '-ms-doc';
        } else if (exptype == 'ms-xls') {
            printurl += '-ms-xls';
        } else if (exptype == 'open-doc') {
            printurl += '-open-doc';
        } else if (exptype == 'open-calc') {
            printurl += '-open-calc';
        }
        var pdata = new Object();
        $('#divprintdata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^divp_')) {
                var fldid = ($(this).attr('id')).replace('divp_', '');
                pdata[fldid] = $(this).val();
            }
        });
        if ('__bo' in coreWebApp.ModelBo) {
            pdata['bo_id'] = coreWebApp.ModelBo.__bo();
        }
        if ('status' in coreWebApp.ModelBo) {
            pdata['status'] = coreWebApp.ModelBo.status();
        }
        var jdata = JSON.stringify(pdata);
        $.ajax({
            url: printurl,
            type: 'POST',
            jsonp: true,
            beforeSend: function () {
                toastmsg('info', 'Export Status', 'Submitting report generation request');
            },
            complete: function () {
                toastmsg('info', 'Export Status', 'Report export process completed', false);
            },
            data: {rptparams: jdata},
            success: function (resultdata, status, jqXHR) {
                if (jqXHR.getResponseHeader("Output-Type") == "text/html") {
                    toastmsg('error', 'Export Status', resultdata, false);
                } else if (jqXHR.getResponseHeader("Output-Type") == "application/json") {
                    pdfInfo = $.parseJSON(resultdata);
                    var pwin = window.open(pdfInfo.ReportRenderedPath);
                }
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.docExport = docExport;

    function docSave(opts) {
        opts.action = '';
        coreWebApp.ModelBo.Submit(opts);
    }
    coreWebApp.DocSave = docSave;

    function docSendTo(opts) {
        opts.action = 'S';
        opts.next_stage_id = coreWebApp.ModelBo.docSecurity.next_stage_id();
        $.ajax({
            url: '?r=cwf/fwShell/main/role-users-view',
            type: 'GET',
            data: {'role_id': coreWebApp.ModelBo.docSecurity.next_role_id(), 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            success: function (resultdata) {
                var dlg = $(resultdata);
                coreWebApp.DocRoleData(dlg, opts);
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocSendTo = docSendTo;

    function docApproveTo(opts) {
        opts.action = 'A';
        opts.next_stage_id = coreWebApp.ModelBo.docSecurity.next_stage_id();
        $.ajax({
            url: '?r=cwf/fwShell/main/role-users-view',
            type: 'GET',
            data: {'role_id': coreWebApp.ModelBo.docSecurity.next_role_id(), 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            success: function (resultdata) {
                var dlg = $(resultdata);
                coreWebApp.DocRoleData(dlg, opts);
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocApproveTo = docApproveTo;

    function docAssignTo(opts) {
        opts.action = 'I';
        opts.next_stage_id = '';
        $.ajax({
            url: '?r=cwf/fwShell/main/assign-users-view',
            type: 'GET',
            beforeSend: function () {
                startloading();
            },
            success: function (resultdata) {
                var dlg = $(resultdata);
                coreWebApp.DocAssignData(dlg, opts);
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocAssignTo = docAssignTo;

    function docReject(opts) {
        opts.action = 'R';
        opts.next_stage_id = '';
        $.ajax({
            url: '?r=cwf/fwShell/main/reject-to-sender',
            type: 'GET',
            data: {'doc_id': coreWebApp.ModelBo['__doc_id'], 'reqtime': new Date().getTime()},
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var dinst = $(resultdata).dialog({
                    closeOnEscape: false,
                    height: 280,
                    width: 550,
                    modal: true,
                    buttons: [
                        {
                            text: "Reject",
                            id: "wf-reject-btn",
                            click: function () {
                                if (coreWebApp.SubmitReject(opts) == true) {
                                    dinst.dialog("destroy").remove();
                                }
                            },
                            class: "btn btn-danger",
                            style: "padding: .25em"
                        },
                        {
                            text: "Cancel",
                            id: "wf-cancel-btn",
                            click: function () {
                                dinst.dialog("destroy").remove();
                            },
                            class: "btn btn-cancel",
                            style: "padding: .25em"
                        }
                    ],
                    open: function (ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog | ui).hide();
                        var user_id_to = $('#reject-doc-view #user_id_from');
                        if (typeof user_id_to.val() === 'undefined' || user_id_to.val() === null) {
                            $('#wf-reject-btn').addClass('disabled');
                            return false;
                        }
                    }
                });
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocReject = docReject;

    function docPost(opts) {
        if (coreWebApp.check_confirm_post()) {
            bs_prompt('warning', 'This action will post this document.<br/>Proceed to post?', function () {
                opts.action = 'P';
                opts.next_stage_id = coreWebApp.ModelBo.docSecurity.next_stage_id();
                opts.wfOption = {user_id_to: -1, doc_sender_comment: 'Document posted'};
                coreWebApp.ModelBo.Submit(opts);
            });
        } else {
            opts.action = 'P';
            opts.next_stage_id = coreWebApp.ModelBo.docSecurity.next_stage_id();
            opts.wfOption = {user_id_to: -1, doc_sender_comment: 'Document posted'};
            coreWebApp.ModelBo.Submit(opts);
        }
    }
    coreWebApp.DocPost = docPost;

    function check_confirm_post() {
        if ($('#confirm_post').length > 0) {
            if ($('#confirm_post').val() === "1" || $('#confirm_post').val() === "true") {
                return true;
            }
        }
        return false;
    }
    coreWebApp.check_confirm_post = check_confirm_post;

    function docUnpost(opts) {
        if (coreWebApp.check_confirm_post()) {
            bs_prompt('warning', 'This action will unpost this document.<br/>Proceed to unpost?', function () {
                var up_reason = window.prompt("Enter reason for unposting", "");
                if (up_reason == null || up_reason == '') {
                    return;
                } else {
                    opts.action = 'U';
                    opts.next_stage_id = '';
                    opts.wfOption = {user_id_to: -1, doc_sender_comment: up_reason};
                    coreWebApp.ModelBo.Submit(opts);
                }
            });
        } else {
            opts.action = 'U';
            opts.next_stage_id = '';
            opts.wfOption = {user_id_to: -1, doc_sender_comment: 'Document unposted'};
            coreWebApp.ModelBo.Submit(opts);
        }
    }
    coreWebApp.DocUnpost = docUnpost;

    function docRoleData(dlg, opts) {
        $.ajax({
            url: '?r=cwf/fwShell/main/role-users',
            type: 'GET',
            data: {'role_id': coreWebApp.ModelBo.docSecurity.next_role_id(), 'reqtime': new Date().getTime()},
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var raw = $.parseJSON(resultdata);
                var data = ko.mapping.fromJS(raw);
                coreWebApp.RoleUsers = data;
                ko.applyBindings(coreWebApp.RoleUsers, $(dlg)[0]);
                var dinst = $(dlg).dialog({
                    closeOnEscape: false,
                    height: 400,
                    width: 550,
                    modal: true,
                    buttons: [
                        {
                            text: "Send/Approve",
                            click: function () {
                                if (coreWebApp.SubmitWf(opts) == true) {
                                    dinst.dialog("destroy").remove();
                                }
                            },
                            class: "btn btn-primary",
                            style: "padding: .25em"
                        },
                        {
                            text: "Cancel",
                            click: function () {
                                //($('#tbl-role-users').dataTable()).destroy();
                                dinst.dialog("destroy").remove();
                            },
                            class: "btn btn-cancel",
                            style: "padding: .25em"
                        }
                    ],
                    open: function (ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog | ui).hide();
                        $('#tbl-role-users').dataTable({
                            paging: false,
                            scrollY: "130px",
                        });
                        $('#tbl-role-users_info').hide();
                    }
                });
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocRoleData = docRoleData;

    function docAssignData(dlg, opts) {
        $.ajax({
            url: '?r=cwf/fwShell/main/assign-users',
            type: 'GET',
            dataType: 'json',
            data: {'bo_id': coreWebApp.ModelBo.__bo(), 'branch_id': coreWebApp.ModelBo.branch_id(), 'reqtime': new Date().getTime()},
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var data = ko.mapping.fromJS(resultdata);
                coreWebApp.RoleUsers = data;
                ko.applyBindings(coreWebApp.RoleUsers, $(dlg)[0]);
                var dinst = $(dlg).dialog({
                    closeOnEscape: false,
                    height: 400,
                    width: 550,
                    modal: true,
                    buttons: [
                        {
                            text: "Assign",
                            click: function () {
                                if (coreWebApp.SubmitWf(opts) == true) {
                                    dinst.dialog("destroy").remove();
                                }
                            },
                            class: "btn btn-primary",
                            style: "padding: .25em"
                        },
                        {
                            text: "Cancel",
                            click: function () {
                                //($('#tbl-role-users').dataTable()).destroy();
                                dinst.dialog("destroy").remove();
                            },
                            class: "btn btn-cancel",
                            style: "padding: .25em"
                        }
                    ],
                    open: function (ui) {
                        $(".ui-dialog-titlebar-close", ui.dialog | ui).hide();
                        $('#tbl-role-users').dataTable({
                            paging: false,
                            scrollY: "130px",
                        });
                        $('#tbl-role-users_info').hide();
                    }
                });
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.DocAssignData = docAssignData;

    function selectRoleUser(data) {
        ko.utils.arrayForEach(coreWebApp.RoleUsers.user_list(), function (item) {
            if (data.user_id() != item.user_id()) {
                item.selected(false);
            }
        });
        //return true;
    }
    coreWebApp.SelectRoleUser = selectRoleUser;

    function submitWf(opts) {
        var result = false;
        ko.utils.arrayForEach(coreWebApp.RoleUsers.user_list(), function (data) {
            if (data.selected()) {
                opts.wfOption = {user_id_to: data.user_id(), doc_sender_comment: coreWebApp.RoleUsers.doc_sender_comment()};
                coreWebApp.ModelBo.Submit(opts);
                result = true;
            }
        });
        return result;
    }
    coreWebApp.SubmitWf = submitWf;

    function submitReject(opts) {
        var user_id_to = $('#reject-doc-view #user_id_from');
        if (typeof user_id_to.val() === 'undefined' || user_id_to.val() === null) {
            $('#wf-reject-btn').addClass('disabled');
            return false;
        }
        var doc_comment = $('#reject-doc-view #sender-comment');
        opts.wfOption = {user_id_to: user_id_to.val(), doc_sender_comment: doc_comment.val()};
        var regress_stage_id = $('#reject-doc-view #regress_stage_id');
        opts.next_stage_id = regress_stage_id.val();
        coreWebApp.ModelBo.Submit(opts);
        return true;
    }
    coreWebApp.SubmitReject = submitReject;

    function hasWfAction() {
        var dc = coreWebApp.ModelBo.docSecurity;
        if (dc.allowSend() || dc.allowApprove() || dc.allowPost() || dc.allowReject() || dc.allowUnpost() || dc.allowAssign()) {
            return true;
        }
        return false;
    }
    coreWebApp.HasWfAction = hasWfAction;

    // maintain collection of tran
    function AddTran(tranName, tranMetaData) {
        var tranDef = {
            tranName: tranName,
            tranMetaData: tranMetaData
        };
        coreWebApp.ModelBo.tranDef.push(tranDef);
        var obj = tranDef;
        obj.selectedItem = ko.observable();
        obj.addNew = function () {
            var cobj = new Object();
            tranMetaData.forEach(function (col) {
                if (col.default instanceof Array) {
                    cobj[col.columnName] = ko.observableArray();
                } else {
                    cobj[col.columnName] = ko.observable(col.default);
                }
            });
            return cobj;
        };
        /*obj.commit = function() {
         var r = this.selectedItem();
         // ignore if it is null
         if (!r) {
         return;
         }
         // already in the array
         if (this.indexOf(r) > -1) {
         return;
         }
         this.push(r);
         this.selectedItem(null);
         };
         obj.removeRow = function(crow) {
         if (this.indexOf(crow) > -1) {
         this.remove(crow);
         //toastmsg('info','Info','Row deleted!',false);
         }
         };*/
    }

    function AddNewRow(tranName, boparent, notify, addfirst) {
        notify = typeof notify === 'undefined' ? false : notify;
        addfirst = typeof addfirst === 'undefined' ? false : addfirst;
        var cobj;
        for (var cnt = 0; cnt < coreWebApp.ModelBo.tranDef.length; cnt++) {
            if (coreWebApp.ModelBo.tranDef[cnt].tranName == tranName) {
                cobj = coreWebApp.ModelBo.tranDef[cnt];
                break;
            }
        }

        if (typeof cobj == 'undefined')
            return null;
        // call new row constructor on model
        var r = cobj.addNew();
//        if(boparent == coreWebApp.ModelBo ) {
//            cobj.selectedItem(r);
//            cobj.commit();
//        } else {
        tranPath = tranName.split(".");
        var target = boparent;
        tranPath.forEach(function (item) {
            target = target[item];
        });
        if (notify) {
            if (addfirst) {
                target.unshift(r);
            } else {
                target.push(r);
            }
        } else {
            if (addfirst) {
                target().unshift(r);
            } else {
                target().push(r);
            }
        }
//        }
        $.validate({
            errorMessagePosition: $('#' + tranName + '-errors')
        });
        return r;
    }

    function RemoveRow(tranName, tranItem) {
        //if (confirm("Are you sure you want to delete this row?") === true) {
        var res = bs_prompt('warning', 'Are you sure you want to delete this row?', function () {
            var obj = coreWebApp.ModelBo[tranName];
            obj.removeRow(tranItem);
            $('#cmd_addnew_' + tranName).focus();
        });
    }

    function RemoveRowFromParent(parentTran, tranName, tranItem, beforeDelete, afterDelete) {
        var b4res = false;
        var tempFunction = new Function("parentTran", "tranName", "tranItem", "return " + beforeDelete + "(parentTran, tranName, tranItem)");
        b4res = tempFunction(parentTran, tranName, tranItem);
        //if (confirm("Are you sure you want to delete this row?") === true) {
        if ((typeof beforeDelete !== 'undefined' && beforeDelete !== '' && b4res == true) || (typeof beforeDelete == 'undefined' || beforeDelete == '')) {
            BootstrapDialog.show({
                title: 'Confirm',
                type: BootstrapDialog.TYPE_DANGER,
                message: 'Are you sure you want to delete this row?',
                buttons: [{
                    label: 'Yes',
                    cssClass: 'btn-danger',
                    action: function (dialog) {
                        tranPath = tranName.split(".");
                        var target = parentTran;
                        tranPath.forEach(function (item) {
                            target = target[item];
                        });
                        target.remove(tranItem);
                        $('#cmd_addnew_' + tranName).focus();
                        if (typeof afterDelete !== 'undefined' && afterDelete !== '') {
                            var tempFunction = new Function("parentTran", "tranName", "tranItem", "return " + afterDelete + "(parentTran, tranName, tranItem)");
                            afres = tempFunction(parentTran, tranName, tranItem);
                        }
                        dialog.close();
                    }
                }, 
                {
                    label: 'No',
                    cssClass: 'btn-default',
                    action: function(dialog) {
                        dialog.close();
                    }
                }]
            });
        }
    }
    coreWebApp.RemoveRowFromParent = RemoveRowFromParent;

    function ReplaceComputedBinding(formid) {
        // look for each form element with data-computed element
        // we get the scriptid of the related function
        // replace the property in the model with the new script function
        // e.g.: coreWebApp.ModelBo['gross_credit_amt'] = ko.computed(eval('gross_credit_amt_computed'));
        $(formid).find('[data-computed]').each(function () {
            if (typeof coreWebApp.ModelBo.status != 'undefined') {
                if (coreWebApp.ModelBo.status() == 5 &&
                        typeof $(this).attr('forcecalonpost') == 'undefined') {
                    return;
                }
            }

            coreWebApp.ModelBo[$(this).attr('id')] = ko.computed({
                read: window[$(this).attr('data-computed')]
                , write: eval($(this).attr('data-computed') + '_w')
            }, coreWebApp.ModelBo);
        });
    }
    coreWebApp.ReplaceComputedBinding = ReplaceComputedBinding;

    function SubscribeModelEvents(element) {
        var items = $(element).find('*[mdata-events]');
        $.each(items, function (i, item) {
            var dv = $(item).attr('mdata-events');
            var fldid = $(item).attr('id');
            var con = ko.dataFor(item);
            if (typeof fldid == 'undefined')
                return;
            var temp = dv.split(",");
            var mevents = [];
            for (var j = 0; j < temp.length; j++) {
                var tp = (temp[j]).split(':');
                mevents[tp[0]] = tp[1];
                if ((tp[0].toLowerCase()) == 'subscribe') {
                    var modelfunc;
                    if (fldid.indexOf('.') > 0) {
                        // Resolves nested fields of json objects
                        fldPath = fldid.split(".");
                        fldPath.forEach(function (child) {
                            con = con[child];
                        });
                        modelfunc = con;
                    } else {
                        modelfunc = (con[fldid]);
                    }
                    if (typeof modelfunc == 'undefined')
                        return;
                    var func = new Function("{ " + tp[1] + "(this,arguments); }");
                    modelfunc.subscribe(func, con);
                }
            }
        });
    }
    coreWebApp.SubscribeModelEvents = SubscribeModelEvents;

    function setStatusInfo() {
        if ($('#spanstatus').length > 0) {
            var statusinfo = '';
            var backcol = '';
            switch (coreWebApp.ModelBo.status()) {
                case 1:
                    statusinfo = 'Created';
                    backcol = 'green';
                    break;
                case 3:
                    statusinfo = 'In Workflow';
                    backcol = 'green';
                    break;
                case 5:
                    statusinfo = 'Posted';
                    backcol = 'darkred';
                    break;
                default:
                    statusinfo = 'New';
                    backcol = 'blue';
                    break;
            }
            if (coreWebApp.ModelBo.docArchiveStatus() === true) {
                statusinfo = 'Archived';
                backcol = 'darkred';
            }
            $('#spanstatus').html(' [' + statusinfo + ']');
            $('#spanstatus').css('color', backcol);
        }

        //Set archive info
        if (coreWebApp.ModelBo.docArchiveStatus() === true) {
            $('#btn-archive').html('<span><i class="glyphicon glyphicon-open" style="margin-right: 10px;"></i>  Unarchive</span>');
        }
    }
    coreWebApp.setStatusInfo = setStatusInfo;

    function setWFColor(wfStatus) {
        switch (wfStatus) {
            case 'S':
                return 'blue';
            case 'A':
                return 'green';
            case 'R':
                return 'maroon';
            case 'U':
                return 'maroon';
            case 'P':
                return 'green';
            case 'I':
                return 'blue';
        }
        return 'grey';
    }
    coreWebApp.setWFColor = setWFColor;

    // add to namespace
    coreWebApp.GetModel = GetModel;

    function getFilteredCollectionWrapper() {
        myform = $('#collectionfilter');
        posturl = $('#collrefresh').attr('posturl');
        if (typeof posturl !== 'undefined' && posturl !== '') {
            coreWebApp.getFilteredCollection(posturl);
        }
    }
    coreWebApp.getfilteredcollectionwrapper = getFilteredCollectionWrapper;

    function closeDetail(refresh) {
        if ($('#hkBeforeCloseEvent').length > 0) {
            var hkBeforeCloseEvent = $('#hkBeforeCloseEvent').val();
            if (typeof hkBeforeCloseEvent != 'undefined' && hkBeforeCloseEvent != '') {
                var func = new Function('refresh', '{return ' + hkBeforeCloseEvent + '(refresh);}');
                hkBeforeCloseEventResult = func(refresh);
                if (typeof (hkBeforeCloseEventResult) != 'undefined' && hkBeforeCloseEventResult == false) {
                    return false;
                }
            }
        }
        $('#details').html('');
        $('#details').hide();
        if (typeof $('#contentholder').html() !== 'undefined') {
            if ($('#qp').length == 0) {
                $('#contentholder').show();
                return false;
            }
            var onunhide = $('#contentholder').attr('onunhide');
            if (typeof onunhide !== 'undefined') {
                var func = new Function('{' + onunhide + '();}');
                func();
            } else {
                $('#contentholder').show();
                var qpType = $('#qp').attr('qp-bizobj');
                if (qpType == 'tree') {
                    return false;
                }
                if (refresh) {
                    $('.select2-drop').remove();
                    $('.select2-hidden-accessible').remove();
                    $('.select2-drop-mask').remove();
                    coreWebApp.collectionView.fetch();
                }
            }
        } else if ($('#contents').length != 0) {
            $('#contents').show();
            return false;
        }
        return false;
    }
    coreWebApp.closeDetail = closeDetail;

    function closedetailonmenu() {
        $('.select2-drop').remove();
        $('.select2-hidden-accessible').remove();
        $('.select2-drop-mask').remove();
        $('#details').html('');
        $('#details').hide();
        if (typeof $('#contentholder').html() !== 'undefined') {
            var onunhide = $('#contentholder').attr('onunhide');
            if (typeof onunhide !== 'undefined') {
                var func = new Function('{' + onunhide + '();}');
                func();
            } else {
                $('#contentholder').show();
            }
        }
        return false;
    }
    coreWebApp.closedetailonmenu = closedetailonmenu;

    function filtercollection(posturl) {
        if ($('#viewer').length > 0) {
            if ($('#viewer').val() == 'treeviewflag') {
                rendercontents(posturl);
            }
            return;
        }
        $('#status').val($('#statusselect').val());
        filterdata = $('#collectionfilter').serializeArray();
        var res = {};
        $.each($('form').serializeArray(), function () {
            res[this.name] = this.value;
        });
        if (typeof res['from_date'] !== 'undefined' && typeof res['to_date'] !== 'undefined') {
            if (res['from_date'] !== '' && res['to_date'] !== '') {
                var frmdt = coreWebApp.unformatDate(res['from_date']);
                var todt = coreWebApp.unformatDate(res['to_date']);
                if (frmdt > todt) {
                    toastmsg('error', 'Filter', 'From date cannot be later than To date', false);
                    return;
                }
            }
        }
        $.ajax({
            url: posturl,
            type: 'GET',
            data: {'filters': $('#collectionfilter').serialize(), 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var rawdata = $.parseJSON(resultdata);
                if ($.fn.dataTable.isDataTable('#thelist')) {
                    var t = $('#thelist').DataTable();
                    t.destroy(true);
                }
                var p = $('#collectiondata');
                p.append('<table id="thelist" class="row-border hover"></table>');

                $('#contents').height($('#content-root').height() * 0.965);
                var tbl = $('#thelist').DataTable({
                    data: rawdata.data,
                    columns: rawdata.columns,
                    deferRender: true,
                    scrollY: getscrollheight() + 'px',
                    scrollCollapse: true,
                    scroller: true,
                });
                $('.dataTables_scrollBody').height(getscrollheight());
                $('.dataTables_scrollBody').css('background', 'white');
                var l = $('#thelist_length');
                if (l !== 'undefined') {
                    l.hide();
                }
                $('.dataTables_empty').text('No data to display');

                // Add event listener for opening and closing details
                $('#thelist tbody').on('click', 'td.details-control', function () {
                    var tr = $(this).closest('tr');
                    var row = tbl.row(tr);

                    if (row.child.isShown()) {
                        row.child.hide();
                        tr.removeClass('shown');
                    } else {
                        thelistdetail(row, tr);
                    }
                });

                // Added by Girish on 23 Jan, 2018
                if ($('#after_fetch')) {
                    var funcbody = $('#after_fetch').val();
                    if (funcbody != undefined || funcbody != '') {
                        var func = new Function('data', 'tbl', '{' + funcbody + '(data, tbl); }');
                        func(rawdata.data, tbl);
                    }
                }
//                $('.dataTables_scrollBody').width($('#thelist').children('tbody').width());
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
        return false;
    }
    coreWebApp.getFilteredCollection = filtercollection;

    function refreshCollectData(posturl, redraw) {
        redraw == undefined ? redraw = false : redraw = true;
        if ($('#viewer').length > 0) {
            if ($('#viewer').val() == 'treeviewflag') {
                rendercontents(posturl);
            }
            return;
        }
        $('#status').val($('#statusselect').val());
        filterdata = $('#collectionfilter').serializeArray();
        var res = {};
        $.each($('form').serializeArray(), function () {
            res[this.name] = this.value;
        });
        if (typeof res['from_date'] !== 'undefined' && typeof res['to_date'] !== 'undefined') {
            if (res['from_date'] !== '' && res['to_date'] !== '') {
                var frmdt = coreWebApp.unformatDate(res['from_date']);
                var todt = coreWebApp.unformatDate(res['to_date']);
                if (frmdt > todt) {
                    toastmsg('error', 'Filter', 'From date cannot be later than To date', false);
                    return;
                }
            }
        }
        $.ajax({
            url: posturl,
            type: 'GET',
            data: {'filters': $('#collectionfilter').serialize(), 'reqtime': new Date().getTime()},
            dataType: 'json',
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (rawdata) {
                var tbl;
                if ($.fn.dataTable.isDataTable('#thelist') && !redraw) {
                    tbl = $('#thelist').DataTable();
                    tbl.clear();
                    tbl.rows.add(rawdata.data);
                    tbl.draw();
                } else {
                    if ($.fn.dataTable.isDataTable('#thelist')) {
                        var t = $('#thelist').DataTable();
                        t.destroy(true);
                    }
                    var p = $('#collectiondata');
                    p.append('<table id="thelist" class="row-border hover"></table>');

                    $('#contents').height($('#content-root').height() * 0.965);
                    tbl = $('#thelist').DataTable({
                        data: rawdata.data,
                        columns: rawdata.columns,
                        deferRender: true,
                        scrollY: getscrollheight() + 'px',
                        scrollCollapse: true,
                        scroller: true,
                    });
                }
                $('.dataTables_scrollBody').height(getscrollheight());
                $('.dataTables_scrollBody').css('background', 'white');
                var l = $('#thelist_length');
                if (l !== 'undefined') {
                    l.hide();
                }
                $('.dataTables_empty').text('No data to display');

                // Add event listener for opening and closing details
                $('#thelist tbody').on('click', 'td.details-control', function () {
                    var tr = $(this).closest('tr');
                    var row = tbl.row(tr);

                    if (row.child.isShown()) {
                        row.child.hide();
                        tr.removeClass('shown');
                    } else {
                        thelistdetail(row, tr);
                    }
                });

                // Added by Girish on 23 Jan, 2018
                if ($('#after_fetch')) {
                    var funcbody = $('#after_fetch').val();
                    if (funcbody != undefined || funcbody != '') {
                        var func = new Function('data', 'tbl', '{' + funcbody + '(data, tbl); }');
                        func(rawdata.data, tbl);
                    }
                }
//                $('.dataTables_scrollBody').width($('#thelist').children('tbody').width());
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
        return false;
    }
    coreWebApp.refreshCollectData = refreshCollectData;

    function getmcollection() {

        var qpRoute = $('#qp').attr('qp-route');
        var qpColl = $('#qp').attr('qp-CollName');
        var qpType = $('#qp').attr('qp-bizobj');
        var lnk;
        if (typeof qpType != 'undefined' && qpType != '') {
            if (qpType == 'Master') {
                lnk = '?r=' + qpRoute + '/mob-form/filter-collection&formName=' + qpColl;
            } else if (qpType == 'Document') {
                lnk = '?r=' + qpRoute + '/mob-form/filter-collection&formName=' + qpColl;
            }
        } else {
            lnk = $('#collrefresh').attr('posturl');
        }

        $.ajax({
            url: lnk,
            type: 'GET',
            data: {'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var rawdata = resultdata;
                $('#mcollcontents').html('');
                var p = $('#mcollcontents');
                p.append(rawdata);
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
        return false;
    }

    coreWebApp.getmcollection = getmcollection;

    function closeMobDetail(refresh) {
        $('#details').html('');
        $('#details').hide();
        if (typeof $('#contentholder').html() !== 'undefined') {
            var onunhide = $('#contentholder').attr('onunhide');
            if (typeof onunhide !== 'undefined') {
                var func = new Function('{' + onunhide + '();}');
                func();
            } else {
                $('#contentholder').show();
                if (refresh) {
                    $('.select2-drop').remove();
                    $('.select2-hidden-accessible').remove();
                    $('.select2-drop-mask').remove();
                    coreWebApp.getmcollection();
                }
            }
        }
        return false;
    }
    coreWebApp.closeMobDetail = closeMobDetail;

    function getjsondata(formid) {
        var res = {};
        $.each($('#' + formid).serializeArray(), function () {
            res[this.name] = this.value;
        });
        $('#' + formid + ' input[type=checkbox]:not(:checked)').each(
                function () {
                    res[this.name] = '0';
                });
        form_method = $('#' + formid).attr('method');
        form_action = $('#' + formid).attr('action');
        form_target = $('#' + formid).attr('target');
        $('#vch_tran').hide();
        if (typeof coreWebApp !== typeof undefined && coreWebApp !== false) {
            if (typeof coreWebApp.ModelBo !== typeof undefined && coreWebApp.ModelBo !== false) {
                coreWebApp.ModelBo.dt = null;
                var table = $('#vch_tran').DataTable();
                table.destroy();
            }
        }
        $.ajax({
            url: form_action,
            type: form_method,
            data: {'params': $('#' + formid).serialize(), 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                ko.cleanNode($('#' + form_target)[0]);
                $('#vch_tran').show();
                $('#brules').html('');
                coreWebApp.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                ko.applyBindings(coreWebApp.ModelBo, $('#' + form_target)[0]);
                initCollection('vch_tran');
                applyDatepicker($('#vch_tran'));
                toggleUpdate();
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
        return false;
    }

    coreWebApp.getJsonData = getjsondata;

    function setjsondata(formaction, formmethod, contentid) {
        form_method = formmethod;
        form_action = formaction;
        form_target = contentid;
        var data = ko.mapping.toJSON(coreWebApp.ModelBo);
        $('#vch_tran').hide();
        $.ajax({
            url: form_action,
            type: form_method,
            data: data,
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                var table = $('#vch_tran').DataTable();
                table.destroy();
                $('#vch_tran').show();
                $('#brules').html('');
                if (jsonResult.jsondata.brokenrules.length > 0) {
                    toastmsg('warning', 'Save Failed', '', false);
                    var brules = jsonResult.jsondata.brokenrules;
                    var litems = '<strong>Broken Rules</strong>';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    $('#brules').append(litems);
                    $('#divbrules').show();
                } else {
                    ko.cleanNode($('#' + form_target)[0]);
                    coreWebApp.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                    ko.applyBindings(coreWebApp.ModelBo, $('#' + form_target)[0]);
                }
                initCollection('vch_tran');
                applyDatepicker($('#vch_tran'));
                toggleUpdate();
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
        return false;
    }

    coreWebApp.setJsonData = setjsondata;

    function showAlloc(cmodule, ppath, fninit, fnupdate, fncancel, dataitem) {
        var boform = $('#bo-form');
        var temp = '?r=' + cmodule + '/form/popupview&alloc=' + ppath + '&formName=' + $('#formName').val();
        if (boform.length == 0) {
            return;
        }
        var finit, fupdate, fcancel;
        if (typeof fninit != 'undefined' && fninit != null) {
            finit = new Function("{ " + fninit + "(this,arguments); }");
        }
        if (typeof fnupdate != 'undefined' && fnupdate != null) {
            fupdate = new Function("{ return " + fnupdate + "(this,arguments); }");
        }
        if (typeof fncancel != 'undefined' && fncancel != null) {
            fcancel = new Function("{ return " + fncancel + "(this,arguments); }");
        }
        var dataitemclone, dataitemo;
        $.ajax({
            url: temp,
            type: 'GET',
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var scopedstyle = '<div><style scoped>.row{margin-left:0px;margin-right:0px;}</style>' + resultdata + '</div>';
                $("#cdialog").html(scopedstyle);
                //$("#bo-form").trigger('create');
                var temp = $("#cdialog").find("[data-bind*='template:']");
                for (var cnt = 0; cnt < temp.length; cnt++) {
                    var db = $(temp[cnt]).attr('data-bind');
                    var tb = db.replace(/\s+/g, '');
//                  var tc=tb.replace("template:{"
//                                    ,"template:{afterRender: function() {coreWebApp.reinitcontrols($('#cdialog')[0]);},")
//                  $(temp[cnt]).attr('data-bind',tc);
                }
                var headertitle = $('#pheader').val();
                if (typeof headertitle == 'undefined') {
                    headertitle = '';
                }
                var btns = [];
                if (typeof fninit != 'undefined' && fninit != null && fninit != '') {
                    if (dataitem != null) {
                        dataitemd = ko.mapping.fromJS(ko.mapping.toJS(dataitem, {ignore: ['__el']}));
                        finit(dataitemd);
                    } else {
                        finit();
                    }
                }
                if (typeof fnupdate != 'undefined' && fnupdate != null && fnupdate != '') {
                    btns = [{text: "Update", id: "cdUpdate",
                            click: function () {
                                var isvalid;
                                if (dataitem != null) {
                                    dataitemd = ko.mapping.fromJS(ko.mapping.toJS(dataitemclone, {ignore: ['__el']}));
                                    isvalid = fupdate(dataitemd);
                                } else {
                                    isvalid = fupdate();
                                }

                                if (isvalid == 'OK') {
                                    $(this).dialog("close");
                                    applysmartcontrols($("#cdialog"));
                                } else {
                                    alert(isvalid);
                                }
                            }
                        }];
                }
                var dlgwd = $($("#cdialog").find('#cboformbody')[0]).width();
                if (dlgwd != 0) {
                    dlgwd += 40;
                } else {
                    dlgwd = 'auto';
                }
                var cdialog = $("#cdialog").dialog({
                    autoOpen: false,
                    modal: true,
                    title: headertitle,
                    resizable: true,
                    width: dlgwd,
                    buttons: btns,
                    close: function () {
                        if (typeof fncancel != 'undefined' && fncancel != null) {
                            fcancel();
                        }
                        ko.cleanNode($('#cdialog')[0]);
                    },
                    open: function () {
                        ReplaceComputedBinding($('#cdialog')[0]);
                        if (typeof dataitem === 'undefined') {
                            ko.applyBindings(coreWebApp.ModelBo, $('#cdialog')[0]);
                        } else {
                            dataitemo = dataitem;
                            dataitemclone = ko.mapping.fromJS(ko.mapping.toJS(dataitem, {ignore: ['__el']}));
                            ko.applyBindings(dataitemclone, $('#cdialog')[0]);
                        }
                        SubscribeModelEvents(this);
                        applysmartcontrols($("#cdialog"));
//                                    var element1= $("#cdialog").find(":input:not([disabled='disabled']):visible").first();
//                                    if(element1.hasClass('smartcombo')){
//                                        $(element1).parent().focus();
//                                    }else{
//                                        element1.focus();
//                                    }
                    }
                });
                cdialog.dialog("open").prev().css('background', 'white');
                cdialog.on("dialogclose", function (event, ui) {});
                $(".ui-dialog").css('max-width', '80%');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-dialog").css('top', '100px');
                $(".ui-dialog").css('left', '16%');
                $(".ui-dialog").css('max-height', $('#bo-form').height() - 50);
                $(".ui-dialog-content").css('max-height', parseInt($('#bo-form').height()) - 150);
                $(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-widget-header").css('border', 'none');
                $(".ui-widget-header").css('border-bottom', '1px solid teal');
                $(".ui-widget-header").css('border-radius', '0');
                $(".ui-dialog .ui-dialog-title").css('line-height', '30px');
                $(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
                $(".ui-dialog .ui-dialog-title").css('font-size', '15px');
                $(".ui-dialog .ui-dialog-title").css('color', 'teal');
                $(".ui-dialog .ui-dialog-title").css('padding-left', '20px');
                $(".ui-dialog-titlebar button").addClass('btn btn-default');
                $(".ui-dialog-titlebar button").focus();
                $('.ui-dialog').find('#cboformbodyin').css('border-bottom', '0');
                $('#cdUpdate').css('padding', '6px 12px');
                $('#cdUpdate').addClass('btn btn-success');
                $('.ui-dialog .ui-dialog-buttonpane').css('border', '0');
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.showAlloc = showAlloc;

    function showAllocV2(opts) {
        var boform = $('#bo-form');
        var req_url = '?r=' + opts.module + '/form/popupview&alloc=' + opts.alloc_view + '&formName=' + $('#formName').val();
        if (boform.length === 0) {
            return;
        }
        $.ajax({
            url: req_url,
            type: 'GET',
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var scopedstyle = '<div><style scoped>.row{margin-left:0px;margin-right:0px;}</style>' + resultdata + '</div>';
                $("#cdialog").html(scopedstyle);
                //$("#bo-form").trigger('create');
                var temp = $("#cdialog").find("[data-bind*='template:']");
                for (var cnt = 0; cnt < temp.length; cnt++) {
                    var db = $(temp[cnt]).attr('data-bind');
                    var tb = db.replace(/\s+/g, '');
//                  var tc=tb.replace("template:{"
//                                    ,"template:{afterRender: function() {coreWebApp.reinitcontrols($('#cdialog')[0]);},")
//                  $(temp[cnt]).attr('data-bind',tc);
                }
                var headertitle = $('#pheader').val();
                if (typeof headertitle === 'undefined') {
                    headertitle = '';
                }
                var btns = [];
                if (typeof opts.call_init !== 'undefined') {
                    var after_init = function () {
                        ko.applyBindings(opts.model, $('#cdialog')[0]);
                        SubscribeModelEvents($('#cdialog'));
                        applysmartcontrols($('#cdialog'));
                    };
                    opts.call_init(opts, after_init);
                }
                if (typeof opts.call_update !== 'undefined') {
                    btns = [];
                    if (coreWebApp.ModelBo.__editMode()) {
                        btns = [{text: "Update", id: "cdUpdate",
                                click: function () {
                                    var isvalid = opts.call_update(opts);
                                    if (isvalid) {
                                        $(this).dialog("close");
                                    }
                                    if (typeof opts.after_update !== 'undefined') {
                                        opts.after_update(opts);
                                    }
                                }
                            }];
                    }
                }
                var dlgwd = $($("#cdialog").find('#cboformbody')[0]).width();
                if (dlgwd != 0) {
                    dlgwd += 40;
                } else {
                    dlgwd = 'auto';
                }
                var cdialog = $("#cdialog").dialog({
                    autoOpen: false,
                    modal: true,
                    title: headertitle,
                    resizable: true,
                    width: dlgwd,
                    buttons: btns,
                    close: function () {
                        ko.cleanNode($('#cdialog')[0]);
                        $('#cdialog').empty();
                        if (typeof opts.call_dispose !== 'undefined') {
                            opts.call_dispose(opts);
                        }
                    },
                    open: function () {
                        ReplaceComputedBinding($('#cdialog')[0]);
                        if (typeof opts.model !== 'undefined') {
                            ko.applyBindings(opts.model, $('#cdialog')[0]);
                            SubscribeModelEvents($('#cdialog'));
                            applysmartcontrols($('#cdialog'));
                        }
                    }
                });
                cdialog.dialog("open").prev().css('background', 'white');
                cdialog.on("dialogclose", function (event, ui) {});
                $(".ui-dialog").css('max-width', '80%');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-dialog").css('top', '100px');
                $(".ui-dialog").css('left', '16%');
                $(".ui-dialog").css('max-height', $('#bo-form').height() - 50);
                $(".ui-dialog-content").css('max-height', parseInt($('#bo-form').height()) - 150);
                $(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-widget-header").css('border', 'none');
                $(".ui-widget-header").css('border-bottom', '1px solid teal');
                $(".ui-widget-header").css('border-radius', '0');
                $(".ui-dialog .ui-dialog-title").css('line-height', '30px');
                $(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
                $(".ui-dialog .ui-dialog-title").css('font-size', '15px');
                $(".ui-dialog .ui-dialog-title").css('color', 'teal');
                $(".ui-dialog .ui-dialog-title").css('padding-left', '20px');
                $(".ui-dialog-titlebar button").addClass('btn btn-default');
                $(".ui-dialog-titlebar button").focus();
                $('.ui-dialog').find('#cboformbodyin').css('border-bottom', '0');
                $('#cdUpdate').css('padding', '6px 12px');
                $('#cdUpdate').addClass('btn btn-success');
                $('.ui-dialog .ui-dialog-buttonpane').css('border', '0');                
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.showAllocV2 = showAllocV2;

    // For non form based popups
    function showAllocV3(opts) {
        var boform = $('#bo-form');
        var req_url = '?r=' + opts.module + '/form/popupview&alloc=' + opts.alloc_view + '&formName=' + $('#formName').val();
        if (boform.length === 0) {
//            return;
        }
        $.ajax({
            url: req_url,
            type: 'GET',
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var scopedstyle = '<div><style scoped>.row{margin-left:0px;margin-right:0px;}</style>' + resultdata + '</div>';
                $("#cdialog").html(scopedstyle);
                //$("#bo-form").trigger('create');
                var temp = $("#cdialog").find("[data-bind*='template:']");
                for (var cnt = 0; cnt < temp.length; cnt++) {
                    var db = $(temp[cnt]).attr('data-bind');
                    var tb = db.replace(/\s+/g, '');
//                  var tc=tb.replace("template:{"
//                                    ,"template:{afterRender: function() {coreWebApp.reinitcontrols($('#cdialog')[0]);},")
//                  $(temp[cnt]).attr('data-bind',tc);
                }
                var headertitle = $('#pheader').val();
                if (typeof headertitle === 'undefined') {
                    headertitle = '';
                }
                var btns = [];
                if (typeof opts.call_init !== 'undefined') {
                    var after_init = function () {
                        ko.applyBindings(opts.model, $('#cdialog')[0]);
                        SubscribeModelEvents($('#cdialog'));
                        applysmartcontrols($('#cdialog'));
                    };
                    opts.call_init(opts, after_init);
                }
                if (typeof opts.call_update !== 'undefined') {
                    btns = [{text: "Update", id: "cdUpdate",
                            click: function () {
                                var isvalid = opts.call_update(opts);
                                if (isvalid) {
                                    $(this).dialog("close");
                                }
                                if (typeof opts.after_update !== 'undefined') {
                                    opts.after_update(opts);
                                }
                            }
                        }];
                }
                var dlgwd = $($("#cdialog").find('#cboformbody')[0]).width();
                if (dlgwd != 0) {
                    dlgwd += 40;
                } else {
                    dlgwd = 'auto';
                }
                var cdialog = $("#cdialog").dialog({
                    autoOpen: false,
                    modal: true,
                    title: headertitle,
                    resizable: true,
                    width: dlgwd,
                    buttons: btns,
                    close: function () {
                        ko.cleanNode($('#cdialog')[0]);
                        $('#cdialog').empty();
                        if (typeof opts.call_dispose !== 'undefined') {
                            opts.call_dispose(opts);
                        }
                    },
                    open: function () {
                        ReplaceComputedBinding($('#cdialog')[0]);
                        if (typeof opts.model !== 'undefined') {
                            ko.applyBindings(opts.model, $('#cdialog')[0]);
                            SubscribeModelEvents($('#cdialog'));
                            applysmartcontrols($('#cdialog'));
                        }
                    }
                });
                cdialog.dialog("open").prev().css('background', 'white');
                cdialog.on("dialogclose", function (event, ui) {});
                $(".ui-dialog").css('max-width', '80%');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-dialog").css('top', '100px');
                $(".ui-dialog").css('left', '16%');
                $(".ui-dialog").css('max-height', $('#bo-form').height() - 50);
                $(".ui-dialog-content").css('max-height', parseInt($('#bo-form').height()) - 150);
                $(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
                $(".ui-dialog").css('z-index', '999');
                $(".ui-widget-header").css('border', 'none');
                $(".ui-widget-header").css('border-bottom', '1px solid teal');
                $(".ui-widget-header").css('border-radius', '0');
                $(".ui-dialog .ui-dialog-title").css('line-height', '30px');
                $(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
                $(".ui-dialog .ui-dialog-title").css('font-size', '15px');
                $(".ui-dialog .ui-dialog-title").css('color', 'teal');
                $(".ui-dialog .ui-dialog-title").css('padding-left', '20px');
                $(".ui-dialog-titlebar button").addClass('btn btn-default');
                $(".ui-dialog-titlebar button").focus();
                $('.ui-dialog').find('#cboformbodyin').css('border-bottom', '0');
                $('#cdUpdate').css('padding', '6px 12px');
                $('#cdUpdate').addClass('btn btn-success');
                $('.ui-dialog .ui-dialog-buttonpane').css('border', '0');
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.showAllocV3 = showAllocV3;

    function handleFileSelect(e) {
        if (!e.target.files || !window.FileReader)
            return;
        $("#attachingFileList").empty();
        for (var i = 0; i < e.target.files.length; i++) {
            var flist = $("#attachedFileList").text();
            var flist2 = $("#attachingFileList").text();
            var umsg = "to be uploaded";
            if (flist.indexOf(e.target.files[i].name) != -1) {
                umsg = "will replace current file";
            } else if (flist2.indexOf(e.target.files[i].name) != -1) {
                return;
            }
            var filestr = "<tr><td><span>" + e.target.files[i].name + "</span>" +
                    "<span style=\"font-style:italic;float:right;\">" + umsg + "</span></td></tr>";
            $("#attachingFileList").append(filestr);
        }
        $("#btnuploadfile").show();
    }
    coreWebApp.handleFileSelect = handleFileSelect;

    function ShowDMForm() {
        var cdialog = $("#cdmfile").dialog({
            autoOpen: false,
            modal: true,
            resizable: true,
            width: 460,
            position: {my: 'left top', at: 'left top+30', of: '#dmclip'},
            buttons: [],
            close: function () {
                ko.cleanNode($('#cdmfile')[0]);
                $("#cfile").bind("change", coreWebApp.handleFileSelect);
            },
            open: function () {
                $("#cfile").bind("change", coreWebApp.handleFileSelect);
                $(this).closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon-closethick' style='font-weight:bold;'>X</span>")
                        .css('float', 'right').height('25px');
                $(".ui-dialog-title").append(
                        '<span class="glyphicon glyphicon-paperclip" style="color:teal;transform: rotate(135deg);" aria-hidden="true"></span> Attachments');
            }
        });
        cdialog.dialog("open").prev().css('background', 'white');
        $(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
        $(".ui-dialog").css('z-index', '9999');
        $(".ui-widget-header").css('border', 'none');
        $(".ui-widget-header").css('border-bottom', '1px solid teal');
        $(".ui-widget-header").css('border-radius', '0');
        $(".ui-dialog .ui-dialog-title").css('line-height', '30px');
        $(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
        $(".ui-dialog .ui-dialog-title").css('font-size', '15px');
        $(".ui-dialog .ui-dialog-title").css('color', 'teal');
        $(".ui-dialog button").addClass('btn');
    }
    coreWebApp.showDMForm = ShowDMForm;

    function reinitcontrols(ctr) {
        applysmartcontrols(ctr);
        ReplaceComputedBinding(ctr);
        SubscribeModelEvents(ctr);
    }
    coreWebApp.reinitcontrols = reinitcontrols;

    function openHelp(helppath) {
        var sroot = location.protocol + '//' + location.host;
        var hpath = sroot + "/help/" + helppath;
        var hwindow = window.open(hpath);
        if (typeof hwindow == 'undefined' || hwindow == null) {
            toastmsg('error', 'Helper', 'Failed to open help.', false);
        }
    }
    coreWebApp.openHelp = openHelp;

    function openComments() {
        $('#cboformbody').toggleClass('col-md-9');
        $('#wfcomments').height($('#cboformbody').height());
        $('#wfcommentsin').height($('#cboformbody').height() - 50);
        $('#wfcommentsin').css('overflow-y', 'auto');
        $('#wfcomments').toggle();
    }
    coreWebApp.openComments = openComments;

    coreWebApp.latestElement = null;
    function latestElementadded() {
        if (arguments.length >= 2) {
            var row = arguments[1];
            //row.__el = arguments[0];
        }
        coreWebApp.latestElement = arguments;
    }
    coreWebApp.latestElementadded = latestElementadded;

    function afterNewRowAdded(ignoreFocus) {
        ignoreFocus = typeof ignoreFocus == 'undefined' ? false : ignoreFocus;
        ReplaceComputedBinding(coreWebApp.latestElement[0]);
        SubscribeModelEvents(coreWebApp.latestElement[0]);
        applysmartcontrols(coreWebApp.latestElement[0]);
        if (coreWebApp.latestElement != null && ignoreFocus) {
            var element1 = $(coreWebApp.latestElement[0][0]).find(":input:not([readonly='true']):not([disabled='disabled']):visible").first();
            if (element1.hasClass('smartcombo')) {
                $(element1).parent().focus();
            } else {
                element1.focus();
            }
            coreWebApp.latestElement = null;
        }
    }
    coreWebApp.afterNewRowAdded = afterNewRowAdded;

    coreWebApp.collectionView = {
        fetch: function (redraw) {
            redraw == undefined ? redraw = false : redraw = true;
            var img = $('#collrefresh_image');
            if (typeof img !== undefined) {
                $(img).addClass('fa-spin');
            }
            var qpRoute = $('#qp').attr('qp-route');
            var qpColl = $('#qp').attr('qp-CollName');
            var qpType = $('#qp').attr('qp-bizobj');
            var lnk;
            if (typeof qpType != 'undefined' && qpType != '') {
                lnk = '?r=/' + qpRoute + '/form/filter-collection&formName=' + qpColl;
            } else {
                lnk = $('#collrefresh').attr('posturl');
            }

            $.ajax({
                url: lnk,
                type: 'GET',
                data: {
                    filters: $('#collectionfilter').serialize()
                },
                dataType: 'json',
                success: function (jdata) {
                    coreWebApp.collectionView.drawTable(jdata, redraw);
                },
                error: function (err) {
                    coreWebApp.toastmsg('error', 'Fetch Collection Data Error', err.responseText, true);
                },
                complete: function () {
                    var img = $('#collrefresh_image');
                    if (typeof img !== undefined) {
                        $(img).removeClass('fa-spin');
                    }
                }
            });
        },
        drawTable: function (jdata, redraw) {
            var tableCols = [];
            var colCnt = jdata.cols.length;
            $.each(jdata.cols, function (colid, col) {
                var colDef = {
                    data: col.columnName,
                    title: col.displayName,
                    width: (100 / colCnt).toFixed(2) + "%"
                };
                if (typeof col.format !== undefined) {
                    switch (col.format) {
                        case "Link":
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html('<a style="color: brown;" href="#" onclick="coreWebApp.collectionView.getDoc(\'' + rowData[jdata.def.keyField] + '\',\'' + jdata.def.afterLoad + '\')">' + cellData + '</a>');
                            };
                            break;
                        case "Date":
                            // Create display format for date filter
                            colDef.data = {
                                _: col.columnName,
                                filter: col.columnName + '_filter'
                            };
                            var fcol = col.columnName + '_filter';
                            $.each(jdata.data, function (rid, row) {
                                row[fcol] = coreWebApp.formatDate(row[col.columnName]);
                            });
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html(coreWebApp.formatDate(cellData));
                            };
                            break;
                        case "Amount":
                            colDef.className = "dt-right";
                            switch (col.scale) {
                                case "amt":
                                    colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                        $(td).html(coreWebApp.formatNumber(cellData, 2));
                                    };
                                    break;
                                case "rate":
                                    colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                        $(td).html(coreWebApp.formatNumber(cellData, 3));
                                    };
                                    break;
                                case "qty":
                                    colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                        $(td).html(coreWebApp.formatNumber(cellData, 3));
                                    };
                                    break;
                                default:
                                    colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                        $(td).html(coreWebApp.formatNumber(cellData, 0));
                                    };
                                    break;
                            }
                            break;
                        case 'Datetime':
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                var dtm = moment(new Date(cellData));
                                $(td).html(dtm.tz(coreWebApp.userTimeZone).format((coreWebApp.dateFormat).toUpperCase() + ' HH:mm:ss z'));
                            };
                            break;
                        case 'Status':
                            colDef.render = function (cellData) {
                                return cellData == 0 || cellData == 1 ? 'Pending' : cellData == 3 ? 'Workflow' : cellData == 5 ? 'Posted' : '';
                            };
                            break;
                        case 'Rate':
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html(coreWebApp.formatNumber(cellData, 4));
                            };
                            break;
                        case 'Qty':
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html(coreWebApp.formatNumber(cellData, 3));
                            };
                            break;
                        case 'FC':
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html(coreWebApp.formatNumber(cellData, 4));
                            };
                            break;
                        case 'Html':
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html(cellData);
                            };
                            break;
                        default:
                            colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                                $(td).html($('<div>').text(cellData).html());
                            };
                            break;
                    }
                }
                if (col.wrapIn !== null) {
                    // This would overwrite format information and create wrapin
                    colDef.createdCell = function (td, cellData, rowData, crow, ccol) {
                        var content = '<' + col.wrapIn + ' style="' + col.style + '">' + cellData + '</' + col.wrapIn + '>';
                        $(td).html(content);
                    };
                }
                tableCols.push(colDef);
            });
            // create Edit/View link column
            tableCols.push({
                data: jdata.def.keyField,
                orderable: false,
                createdCell: jdata.def.al > 1 ? function (td, cellData, rowData, row, col) {
                    $(td).html('<a href="#" onclick="coreWebApp.collectionView.getDoc(\'' + rowData[jdata.def.keyField] + '\',\'' + jdata.def.afterLoad + '\')" ><i class="glyphicon glyphicon-pencil"></i></a>');
                } : function (td, cellData, rowData, row, col) {
                    $(td).html('<a href="#" onclick="coreWebApp.collectionView.getDoc(\'' + rowData[jdata.def.keyField] + '\',\'' + jdata.def.afterLoad + '\')" ><i class="glyphicon glyphicon-eye-open"></i></a>');
                }
            });

            if ($.fn.dataTable.isDataTable('#thelist') && !redraw) {
                tbl = $('#thelist').DataTable();
                tbl.clear();
                tbl.rows.add(jdata.data);
                tbl.draw();
            } else {
                if ($.fn.dataTable.isDataTable('#thelist')) {
                    var t = $('#thelist').DataTable();
                    t.destroy(true);
                }
                var p = $('#collectiondata');
                p.append('<table id="thelist" class="row-border hover"></table>');
                $('#contentholder').height($('#content-root').height() - 10);
                $('#contents').height($('#content-root').height() - 20);
                tbl = $('#thelist').DataTable({
                    createdRow: function (tr, rowData, dataIndex) {
                        $(tr).on('dblclick', function () {
                            coreWebApp.collectionView.getDoc(rowData[jdata.def.keyField], jdata.def.afterLoad);
                        });
                    },
                    data: jdata.data,
                    autoWidth: false,
                    columns: tableCols,
                    deferRender: true,
                    scrollY: getscrollheight() + 'px',
                    scrollX: true,
                    searching: true,
                    scroller: true
                });
            }
            $('.dataTables_empty').text('No data to display');
            $('.dataTables_scrollBody').css("min-height", ($('.dataTables_scrollBody').height()).toString() + 'px');
            $('.dataTables_scrollBody').css("height", ($('.dataTables_scrollBody').height()).toString() + 'px');
            $('.dataTables_scrollBody').css("background", "transparent");
            $('#thelist_length').hide();
            if ($('#after_fetch')) {
                var funcbody = $('#after_fetch').val();
                if (funcbody != undefined || funcbody != '') {
                    var func = new Function('data', 'tbl', '{' + funcbody + '(data, tbl); }');
                    func(jdata.data, tbl);
                }
            }
        }
    };

    function getDoc(id, afterloadevent) {
        var qpRoute = $('#qp').attr('qp-route');
        var qpForm = $('#qp').attr('qp-formname');
        var qpKey = $('#qp').attr('qp-keyfield');
        var qpType = $('#qp').attr('qp-doctype');
        var lnk = '';
        if (id == '-1') {
            lnk = '?r=/' + qpRoute + '/form&formName=' + qpForm + '&formParams={"' + qpKey + '": -1,"doc_type":"' + qpType + '"}';
        } else {
            if (typeof (qpType) == 'undefined' || qpType == '') {
                lnk = '?r=/' + qpRoute + '/form&formName=' + qpForm + '&formParams={"' + qpKey + '":' + id + '}';
            } else {
                lnk = '?r=/' + qpRoute + '/form&formName=' + qpForm + '&formParams={"' + qpKey + '":"' + id + '"}';
            }
        }
        rendercontents(lnk, 'details', 'contentholder', afterloadevent);
    }

    coreWebApp.collectionView.getDoc = getDoc;

    function getDocATT() {
        var qpForm = $('#qp').attr('qp-formname');
        if ($('#detailsat').length == 0) {
            $('#content-root').append('<div id="detailsat" class="view-min-width view-window2" style="display: none;"></div>');
        }
        if (typeof qpForm != 'undefined' && qpForm != '') {
            var kf = $('#qp').attr('qp-keyfield');
            var kfid = coreWebApp.ModelBo[kf]();
            var qpRoute = $('#qp').attr('qp-route');
            var qpKey = $('#qp').attr('qp-keyfield');
            var lnk = '?r=/cwf/sys/main/audittrail&formName=/' + qpRoute + '/' + qpForm + '&formParams={"' + qpKey + '":"' + kfid + '"}&formUrl=/' + qpRoute;
            rendercontents(lnk, 'detailsat', 'details');
        } else {
            // only for treeview collection based masters
            var qpRoute = $('#formModulePath').val();
            var qpForm = $('#formName').val();
            var qparam = $('#formParams').val();
            var lnk = '?r=/cwf/sys/main/audittrail&formName=/' + qpRoute + '/' + qpForm + '&formParams=' + qparam + '&formUrl=/' + qpRoute;
            rendercontents(lnk, 'detailsat', 'details');
        }
    }
    coreWebApp.getDocATT = getDocATT;

    function getDocAT(id) {
        var qpRoute = $('#qp').attr('qp-route');
        var qpForm = $('#qp').attr('qp-formname');
        var qpKey = $('#qp').attr('qp-keyfield');
        var lnk = '?r=/cwf/sys/main/audittrail&formName=/' + qpRoute + '/' + qpForm + '&formParams={"' + qpKey + '":"' + id + '"}&formUrl=/' + qpRoute;
        rendercontents(lnk, 'details', 'contentholder');
    }

    coreWebApp.collectionView.getDocAT = getDocAT;

    function getData(redrawTable) {
        var qpRoute = $('#qp').attr('qp-route');
        var qpColl = $('#qp').attr('qp-CollName');
        var qpType = $('#qp').attr('qp-bizobj');
        var lnk;
        if (typeof qpType != 'undefined' && qpType != '') {
            if (qpType == 'Master') {
                lnk = '?r=/' + qpRoute + '/form/filter-collection&formName=' + qpColl;
            } else if (qpType == 'Document') {
                lnk = '?r=/' + qpRoute + '/form/filter-collection&formName=' + qpColl;
            }
            //coreWebApp.getFilteredCollection(lnk);
            coreWebApp.refreshCollectData(lnk, redrawTable);
        } else {
            posturl = $('#collrefresh').attr('posturl');
            if (typeof posturl !== 'undefined' && posturl !== '') {
                //coreWebApp.getFilteredCollection(posturl);
                coreWebApp.refreshCollectData(posturl, redrawTable);
            }
        }
    }

    coreWebApp.collectionView.getData = getData;

    function getWiz() {
        var qpRoute = $('#qp').attr('qp-route');
        var qpWiz = $('#qp').attr('qp-wizPath');
        var qpStep = $('#qp').attr('qp-wizStep');
        var lnk = '?r=/' + qpRoute + '/form/wizard&formName=' + qpWiz + '&step=' + qpStep;
        rendercontents(lnk, 'details', 'contentholder');
    }

    coreWebApp.collectionView.getWiz = getWiz;

    coreWebApp.lastka = Date.now();
    function KeepAlive() {
        if (typeof coreWebApp.branch_gst_info.gstin !== 'undefined') {
            if ((Date.now() - coreWebApp.lastka) > 120000) {
                coreWebApp.lastka = Date.now();
                $.ajax({
                    url: '?r=/cwf/fwShell/main/keepalive',
                    type: 'GET',
                    data: {
                        reqt: Date.now() 
                    },
                    success: function (resultdata, status, jqXHR) {
                        if (jqXHR.statusText == 'OK' && resultdata != '') {
                            var jsonResult = $.parseJSON(resultdata);
                            if (jsonResult.status == 'OK') {
                                // Do nothing
                            } else {
                                window.location.replace('index.php');
                            }
                        } else {
                            window.location.replace('index.php');
                        }
                    },
                    error: function (data) {
                        toastmsg('warning', 'Server Error', 'Could not connect to server.', true);
                    }
                });
                
            }
        }
    }
    coreWebApp.keepAlive = KeepAlive;

    function getPendingStatus() {
        $.ajax({
            url: '?r=/cwf/fwShell/main/getpendingcnt',
            type: 'GET',
            success: function (resultdata, status, jqXHR) {
                if (jqXHR.statusText == 'OK' && resultdata != '') {
                    var jsonResult = $.parseJSON(resultdata);
                    for (var i = 0; i < jsonResult.length; i++) {
                        var ctrid = '#pc_' + jsonResult[i]['menu_id'];
                        if ($(ctrid).lenght > 0) {
                            $(ctrid).empty();
                            $(ctrid).hide();
                        }
                        if ($(ctrid).parents().children('.opened').is(':visible')) {
                            $(ctrid).html(jsonResult[i]['pending_cnt']);
                        } else {
                            $(ctrid).html(jsonResult[i]['pending_cnt']).show();
                        }
                        if (jsonResult[i]['pending_cnt'] == 0) {
                            $(ctrid).empty();
                        }
                    }
                }
            },
            error: function (data) {
                toastmsg('warning', 'Server Error', 'Could not connect to server.', true);
            }
        });
    }
    coreWebApp.getPendingStatus = getPendingStatus;

    function GetData(ajaxreq) {
        $.ajax(ajaxreq);
    }
    coreWebApp.utils.getData = GetData;

    function beginProfile(id) {
        coreWebApp.utils.profile_data == undefined ? coreWebApp.utils.profile_data = new Map() : '';
        coreWebApp.utils.profile_data.set(id, {
            start_time: Date.now(),
            end_time: null,
            duration: 0
        });
    }
    coreWebApp.utils.beginProfile = beginProfile;

    function endProfile(id) {
        if (coreWebApp.utils.profile_data.has(id)) {
            var mi = coreWebApp.utils.profile_data.get(id);
            mi.end_time = Date.now();
            mi.duration = mi.end_time - mi.start_time;
        }
    }
    coreWebApp.utils.endProfile = endProfile;

    function printProfile() {
        var mkeys = coreWebApp.utils.profile_data.keys();
        for (let mkey of mkeys) {
            var mi = coreWebApp.utils.profile_data.get(mkey);
            console.log('Profile: ' + mkey + ' duration: ' + mi.duration);
        }
    }
    coreWebApp.utils.printProfile = printProfile;

    function clearProfile() {
        coreWebApp.utils.profile_data == undefined ? coreWebApp.utils.profile_data = new Map() : coreWebApp.utils.profile_data.clear();
    }
    coreWebApp.utils.clearProfile = clearProfile;

    function AjaxSetup(sessionid) {
        coreWebApp.coreSessionID = sessionid;
        $.ajaxSetup({
            headers: {'core-sessionid': sessionid}
        });
    }
    coreWebApp.ajaxSetup = AjaxSetup;

    function UploadFile() {
        var fd = new FormData(document.getElementById('fileupload'));

        var bo = coreWebApp.ModelBo.__bo();
        var doc_id = coreWebApp.ModelBo.__doc_id();

        fd.append('bo', bo);
        fd.append('doc_id', doc_id);

        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=cwf/fwShell/dmfile/upload',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            beforeSend: function () {
                var xhr = new window.XMLHttpRequest();
                //Upload progress
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                    }
                }, false);
                $('#btnuploadfile').attr('disabled', 'disabled');
                $('#btnuploadfile span').text('Uploading..');
            }
        }).done(function (data) {
            var filelist = $.parseJSON(data);
            $('#attachedFileList').empty();
            $("#attachingFileList").empty();
            var ctrdisable = '';
            if (coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) {
                ctrdisable = 'disabled';
            }
            var flcnt = 0;
            filelist.forEach(function (item) {
                if (item.status == 'saved' || item.fileid != -1) {
                    var filestr = '<tr><td><a href="?r=cwf/fwShell/dmfile/downloadfile&file_id=' + item.fileid
                            + '&core-sessionid=' + coreWebApp.coreSessionID
                            + '" style="text-decoration:none" fileid="' + item.fileid + '">' + item.fileName + '</a>';
                    filestr += '<button ' + ctrdisable + ' type="button" filename="' + item.fileName + '" fileid="' + item.fileid + '" class="btn btn-default" ' +
                            'style="border:none; padding:0 5px; float:right;" onclick="coreWebApp.detachDMFile(this)">' +
                            ((coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) ? '' : '<i class="glyphicon glyphicon-trash"></i>') +
                            '</button>';
                    filestr += '</td></tr>';
                    $('#attachedFileList').append(filestr);
                    flcnt++;
                } else if (item.fileName == '' && item.status != 'saved' && item.fileid == -1) {
                    var filestr = "<tr><td>" +
                            "<span style=\"font-style:italic;float:right;color:maroon;\"> " + item.status + " Upload failed. </span></td></tr>";
                    $("#attachingFileList").append(filestr);
                } else {
                    var filestr = "<tr><td><span>" + item.fileName + "</span>" +
                            "<span style=\"font-style:italic;float:right;color:maroon;\"> File is infected ( " + item.status + " ). Upload failed. </span></td></tr>";
                    $("#attachingFileList").append(filestr);
                }
            });
            $('#btnuploadfile').hide();
            $('#btnuploadfile').removeAttr('disabled');
            $('#btnuploadfile span').text('Upload');
            $('#attcnt').html(flcnt);
            if (flcnt > 0) {
                $('#attcnt').show();
            } else {
                $('#attcnt').hide();
            }
        });
        return false;
    }
    coreWebApp.uploadFile = UploadFile;

    function GetUploadedFiles() {
        if (typeof coreWebApp === typeof undefined) {
            return;
        }
        if (typeof coreWebApp.ModelBo === typeof undefined) {
            return;
        }
        if (typeof coreWebApp.ModelBo.__doc_id() === typeof undefined) {
            return;
        }
        if (coreWebApp.ModelBo.__doc_id() == '-1') {
            return;
        }

        var bo = coreWebApp.ModelBo.__bo();
        var doc_id = coreWebApp.ModelBo.__doc_id();

        $.ajax({
            url: '?r=cwf/fwShell/dmfile/doclist',
            type: 'GET',
            data: {'bo': bo, 'doc_id': doc_id, 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            }
        }).done(function (data) {
            var filelist = $.parseJSON(data);
            $('#attachedFileList').empty();
            $("#attachingFileList").empty();
            var ctrdisable = '';
            if (coreWebApp.ModelBo.status() == 5) {
                ctrdisable = 'disabled';
            }
            filelist.forEach(function (item) {
                var filestr = '<tr><td><a href="?r=cwf/fwShell/dmfile/downloadfile&file_id=' + item.fileid
                        + '&core-sessionid=' + coreWebApp.coreSessionID
                        + '" style="text-decoration:none" fileid="' + item.fileid + '">' + item.fileName + '</a>';
                filestr += '<button ' + ctrdisable + ' type="button" filename="' + item.fileName + '" fileid="' + item.fileid + '" class="btn btn-default" ' +
                        'style="border:none; padding:0 5px; float:right;" onclick="coreWebApp.detachDMFile(this)">' +
                        ((coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) ? '' : '<i class="glyphicon glyphicon-trash"></i>') +
                        '</button>';
                filestr += '</td></tr>';
                $('#attachedFileList').append(filestr);
            });
        });
        return false;
    }
    coreWebApp.getUploadedFiles = GetUploadedFiles;

    function SetFileList(filelist) {
        if (filelist.length == 0) {
            return;
        }
        var ctrdisable = '';
        if (coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) {
            ctrdisable = 'disabled';
        }
        $('#attachedFileList').empty();
        $("#attachingFileList").empty();
        coreWebApp.dmfiles = [];
        coreWebApp.dmfiles = filelist;
        filelist.forEach(function (item) {
            var filestr = '<tr><td><a href="?r=cwf/fwShell/dmfile/downloadfile&file_id=' + item.fileid
                    + '&core-sessionid=' + coreWebApp.coreSessionID
                    + '" style="text-decoration:none" fileid="' + item.fileid + '">' + item.fileName + '</a>';
            filestr += '<button ' + ctrdisable + ' type="button" filename="' + item.fileName + '" fileid="' + item.fileid + '" class="btn btn-default" ' +
                    'style="border:none; padding:0 5px; float:right;" onclick="coreWebApp.detachDMFile(this)">' +
                    ((coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) ? '' : '<i class="glyphicon glyphicon-trash"></i>') +
                    '</button>';
            filestr += '</td></tr>';
            $('#attachedFileList').append(filestr);
        });
    }
    coreWebApp.setFileList = SetFileList;

    function DetachDMFile(ctrl) {
        if (coreWebApp.ModelBo.hasOwnProperty('status') && coreWebApp.ModelBo.status() == 5) {
            toastmsg('warning', '', 'Could not delete attachment from posted document.');
            return;
        }
        bs_prompt('error', 'Are you sure you want to remove ' + $(ctrl).attr('filename') + '?', function () {
            if (typeof coreWebApp === typeof undefined) {
                return;
            }
            if (typeof coreWebApp.ModelBo === typeof undefined) {
                return;
            }
            if (typeof coreWebApp.ModelBo.__doc_id() === typeof undefined) {
                return;
            }
            if (coreWebApp.ModelBo.__doc_id() == '-1') {
                return;
            }

            var bo = coreWebApp.ModelBo.__bo();
            var doc_id = coreWebApp.ModelBo.__doc_id();
            var file_id = $(ctrl).attr('fileid');
            $.ajax({
                url: '?r=cwf/fwShell/dmfile/detachdoc',
                type: 'GET',
                data: {'bo': bo, 'doc_id': doc_id, 'file_id': file_id, 'reqtime': new Date().getTime()},
                beforeSend: function () {
                    startloading();
                },
                complete: function () {
                    stoploading();
                }
            }).done(function (data) {
                var filelist = $.parseJSON(data);
                $('#attachedFileList').empty();
                filelist.forEach(function (item) {
                    var filestr = '<tr><td><a href="?r=cwf/fwShell/dmfile/downloadfile&file_id=' + item.fileid
                            + '&core-sessionid=' + coreWebApp.coreSessionID
                            + '" style="text-decoration:none" fileid="' + item.fileid + '">' + item.fileName + '</a>';
                    filestr += '<button type="button" filename="' + item.fileName + '" fileid="' + item.fileid + '" class="btn btn-default" ' +
                            'style="border:none; padding:0 5px; float:right;" onclick="coreWebApp.detachDMFile(this)">' +
                            '<i class="glyphicon glyphicon-trash"></i>' +
                            '</button>';
                    filestr += '</td></tr>';
                    $('#attachedFileList').append(filestr);
                });
                $('#attcnt').html(filelist.length);
                if (filelist.length > 0) {
                    $('#attcnt').show();
                } else {
                    $('#attcnt').hide();
                }
            });
        });
        return false;
    }
    coreWebApp.detachDMFile = DetachDMFile;

    function DocReady() {
        coreWebApp.dateFormat = $('#dateformat').val();
        coreWebApp.userTimeZone = $('#usertimezone').val();
        coreWebApp.ccySystem = $('#ccysystem').val();
        coreWebApp.updateKOhandlers();
        $('.container-fluid').css('padding-left', '5px');
        $('.navbar-header').width(parseInt($('.container-fluid').width() * 0.16));
//            $('.headerlogo').width(parseInt($('.container-fluid').width()*0.125));
//            $('.headerlogo').height((parseInt($('.container-fluid').width()*0.125))*(64/204));
//            $('#sfver').css('left' ,$('.headerlogo').width());
//            var rt = ($('.container-fluid').width() - ($('#sfver').offset().left + $('#sfver').outerWidth()));
//            $('#cname').css('left',(rt+20));
        if ($('#content-root').length !== 0 && (($('#content-root').html()).trim()).length === 0) {
            rendercontents('?r=cwf/fwShell/main/dashboard&dbd=home', 'content-root');
        }
        $(document).unbind('keydown').bind('keydown', function (event) {
            var doPrevent = false, elem;
            if (event.keyCode === 8) {
                elem = event.srcElement || event.target;
                if ($(elem).is("input, textarea")) {
                    doPrevent = elem.readOnly || elem.disabled;
                } else {
                    doPrevent = true;
                }
            }
            if (event.keyCode == 13) {
                elem = event.srcElement || event.target;
                if ($(elem).is("input, textarea")) {
                    doPrevent = true;
                    if (/^login.*password$/.test($(elem).attr('id'))
                            || /^login.*username$/.test($(elem).attr('id'))) {
                        doPrevent = false;
                    }
                }
                if ($(elem).is("textarea")) {
                    var temp = $(elem).val();
                    posn = $(elem).prop("selectionStart");
                    var op = temp.substr(0, posn) + '\n' + temp.substr(posn);
                    $(elem).val(op);
                    $(elem).prop("selectionStart", posn + 1);
                    $(elem).prop("selectionEnd", posn + 1);
                }
            }
            if (doPrevent) {
                event.preventDefault();
                return false;
            }
        });
        $('#sidemenu').children().find('.table').first().css('background-color', '#2c383b');
        $('.kv-toggle').click(function () {
            var clrr = $(this).css('background-color');//'#2c383b';
            $('.opened').hide();
            $('.menu-cnt-badge').each(function () {
                if ($(this).html() != '') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            $('.closed').show();
            $('.kv-toggle').css('background-color', '#2c383b');
            $('.kv-toggle').css('color', '#9d9d9d');
            $('.root-item').each(function () {
                $(this).removeClass('active');
                $(this).children('ul').hide();
            });
            $(this).addClass("active");
            $(this).children('.opened').show();
            $(this).children('.closed').hide();
            $(this).parent().children('ul').css('padding-left', '5px');
            $(this).parents().children('ul').show();
            $(this).parents().children('.kv-toggle').css('color', 'white');
            $(this).parents().children('.kv-toggle').children('.opened').show();
            $(this).parents().children('.kv-toggle').children('.menu-cnt-badge').hide();
            $(this).parents().children('.kv-toggle').children('.closed').hide();
            $(this).parents('.abs').children('.kv-toggle').css('background-color', modcolor(0.2, clrr));
        });
        $('.nonroot-item').click(function () {
            $('.nonroot-item').removeClass('active');
            $(this).addClass('active');
        });
        $(window).bind('resize', function (e) {
            if (window.RT)
                clearTimeout(window.RT);
//            coreWebApp.resetwindow();
            $('.mycontainer').hide().show(0);
        });

        // Set branch gst info
        coreWebApp.branch_gst_info = $.parseJSON($('#branch_gst_info').val());
    }
    coreWebApp.docReady = DocReady;

    function resetwindow() {
        var wrapht = parseInt($(window).height());
        var navht = parseInt($('#headernav').height());
        $('.mycontainer').height(wrapht - navht - 1);
        $('#workspace').height(wrapht - navht - 1);
        $('#mysidemenu').height(wrapht - navht - 1);
        $('#content-root').height(wrapht - navht - 1);
        $('#contentholder').height(wrapht - navht - 11);
        $('#details').height(wrapht - navht - 11);
        if ($('#cboformbody').length > 0) {
            $('#cboformbody').height(wrapht - navht - 66);
        }
//        $('.dataTables_scrollBody').width($('#thelist').children('tbody').width());
        $('#content-root').width($('#workspace').width() - $('#mysidemenu').width() - 15);
    }
    coreWebApp.resetwindow = resetwindow;

    function setform() {
        ctr = $('#cboformbodyin').find('input').filter(':visible:first');
        $(ctr).focus();
        if ($('#content-root').length > 0) {
            if ($('#content-root').height() > 550) {
                $('.tran-body').css('max-height', '355px');
            } else {
                $('.tran-body').css('max-height', '158px');
            }
        }
    }
    coreWebApp.setform = setform;

    function setOrder() {
        $('#cboformbodyin').find('input,select, textarea, button').filter(":not([readonly]):enabled").each(function () {
            if (this.type != "hidden") {
                var $input = $(this);
                $input.attr("tabindex", 0);
            }
        });
        $('#cboformbodyin').find('input,select, textarea, button').filter(":disabled").each(function () {
            var $input = $(this);
            $input.attr("tabindex", -1);
        });
    }
    coreWebApp.setorder = setOrder;

    function updateKOhandlers() {

        ko.bindingHandlers.select2 = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                coreWebApp.applySmartCombo(element);
            },
            update: function (element, valueAccessor, allBindingsAccessor, viewModel) {
                var val = ko.unwrap(valueAccessor());
                $(element).select2("val", val);
            }
        };

        ko.bindingHandlers.select2m = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                coreWebApp.applySmartMultiCombo(element);
            },
            update: function (element, valueAccessor, allBindingsAccessor, viewModel) {
            }
        };

        ko.bindingHandlers.numericValue = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                var scale = parseInt($(element).attr('scale'));
                var maxval = parseFloat($(element).attr('maxVal'));
                if (typeof maxval == 'undefined' || maxval == 0 || maxval == '') {
                    maxval = 999999999999;
                }
                if (typeof scale == 'undefined' || isNaN(scale)) {
                    scale = 2;
                }
                coreWebApp.applyNumber(element);
                var underlyingObservable = valueAccessor();
                var interceptor = ko.computed({
                    read: function () {
                        if (typeof underlyingObservable == 'object'
                                || typeof underlyingObservable == "undefined") {
                            return 0;
                        }
                        return coreWebApp.formatNumber(underlyingObservable(), scale);
                    },
                    write: function (newValue) {
                        var current = underlyingObservable();
                        var valueToWrite = parseFloat(newValue.replace(/,/g, "")).toFixed(scale);
                        if (!isNaN(valueToWrite)) {
                            if (valueToWrite > maxval) {
                                valueToWrite = maxval;
                                underlyingObservable(maxval);
                                underlyingObservable.valueHasMutated();
                            } else if (valueToWrite !== current) {
                                underlyingObservable(valueToWrite);
                            }
                            if (valueToWrite != current) {
                                underlyingObservable.valueHasMutated();
                            }
                        } else {
                            $(element).val(current);
                        }
                    }
                });
                ko.applyBindingsToNode(element, {value: interceptor, text: interceptor});
            }
        };

        ko.bindingHandlers.dateValue = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                coreWebApp.applyDatepicker(element);
                var underlyingObservable = valueAccessor();
                var interceptor = ko.computed({
                    read: function () {
                        if (ko.isObservable(underlyingObservable)) {
                            return coreWebApp.formatDate(underlyingObservable());
                        } else {
                            return '';
                        }
                    },
                    write: function (newValue) {
                        // try to update when complete date is entered
                        if (newValue.length == 10) {
                            var current = underlyingObservable(),
                                    valueToWrite = coreWebApp.validDate(newValue, current);
                            if (valueToWrite !== current) {
                                underlyingObservable(valueToWrite);
                                underlyingObservable.valueHasMutated();
                            }
                        }
                    }
                });
                if (ko.isObservable(underlyingObservable)) {
                    $(element).val(coreWebApp.formatDate(underlyingObservable())).datepicker('update');
                }
                ko.applyBindingsToNode(element, {value: interceptor, text: interceptor});
            }
        };

        ko.bindingHandlers.toggle = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                $(element).change(function () {
                    var val = valueAccessor();
                    val($(element).prop('checked'));
                });
            },
            update: function (element, valueAccessor) {
                var value = valueAccessor();
                var result = ko.unwrap(value);
                $(element).prop('checked', result).change();
            }
        };

        ko.bindingHandlers.datetimetext = {
            init: function (element, valueAccessor, allBindingsAccessor) {
                var interceptor = ko.computed({
                    read: function () {
                        if (ko.isObservable(valueAccessor())) {
                            var val = valueAccessor();
                            var fxdt = ((val()).replace(/-/g, '/'));
                            var dtm = moment(new Date(fxdt));
                            return dtm.tz(coreWebApp.userTimeZone).format((coreWebApp.dateFormat).toUpperCase() + ' HH:mm:ss z');
                        }
                    }
                });
                ko.applyBindingsToNode(element, {value: interceptor, text: interceptor});
            }
        };


    }
    coreWebApp.updateKOhandlers = updateKOhandlers;

    function formatDate(dateval) {
        if (dateval == '1970-01-01') {
            return '';
        }
        var dtm = moment(new Date(dateval));
        return dtm.format((coreWebApp.dateFormat).toUpperCase());
    }
    coreWebApp.formatDate = formatDate;

    function formatDateTime(dateval) {
        if (dateval == '1970-01-01 00:00:00 UTC') {
            return '';
        }
        var fxdt = (dateval).replace(/-/g, '/');
        if (fxdt.indexOf('UTC') == -1) {
            fxdt += ' UTC';
        }
        var dtm = moment(new Date(fxdt));
        return dtm.tz(coreWebApp.userTimeZone).format((coreWebApp.dateFormat).toUpperCase() + ' HH:mm:ss z');
    }
    coreWebApp.formatDateTime = formatDateTime;

    function pad2(val) {
        if (val.toString().length == 1) {
            return '0' + val.toString();
        } else {
            return val.toString();
        }
    }
    coreWebApp.pad2 = pad2;

    function validDate(enteredDate, currentValue) {
        if (enteredDate == '') {
            return currentValue;
        }
        if (enteredDate.toString().indexOf('-') == 5
                && !isNaN(enteredDate.toString().substring(4))) {
            // date is enetered in long format, so return as is if valid
            var d = Date.parse(enteredDate);
            if (!isNaN(d)) {
                return enteredDate;
            }
        }
        return coreWebApp.unformatDate(enteredDate);
    }
    coreWebApp.validDate = validDate;

    function unformatDate(enteredDate) {
        if (coreWebApp.dateFormat == 'dd/mm/yyyy') {
            var parts = enteredDate.split('/');
            return parts[2].toString() + '-' + pad2(parts[1]) + '-' + pad2(parts[0]);
        } else if (coreWebApp.dateFormat == 'dd-mm-yyyy') {
            var parts = enteredDate.split('-');
            return parts[2].toString() + '-' + pad2(parts[1]) + '-' + pad2(parts[0]);
        } else if (coreWebApp.dateFormat == 'mm/dd/yyyy') {
            var parts = enteredDate.split('/');
            return parts[2].toString() + '-' + pad2(parts[0]) + '-' + pad2(parts[1]);
        } else if (coreWebApp.dateFormat == 'mm-dd-yyyy') {
            var parts = enteredDate.split('-');
            return parts[2].toString() + '-' + pad2(parts[0]) + '-' + pad2(parts[1]);
        }
    }
    coreWebApp.unformatDate = unformatDate;

    function formatNumber(numval, scale) {
        if (typeof numval === 'undefined') {
            return '0';
        }
        var parsedval = Number.parseFloat(numval);
        if (coreWebApp.ccySystem === 'l') {
            return parsedval.toLocaleString('en-IN', {minimumFractionDigits: scale, maximumFractionDigits: scale});
        } else {
            return parsedval.toLocaleString('en-US', {minimumFractionDigits: scale, maximumFractionDigits: scale});
        }
        // Old code commented
//        var temp = parseFloat(numval).toFixed(scale);
//        numval = temp.toString();
//        var intpart = parseInt(numval).toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
//        var decpos = numval.toString().indexOf('.');
//        if (decpos !== -1) {
//            var decpart = numval.toString().substring(decpos);
//            return numsign + intpart + decpart;
//        } else {
//            if (scale == 0) {
//                return intpart;
//            } else {
//                return numsign + intpart + '.00';
//            }
//        }
        // Old Code comment
    }
    coreWebApp.formatNumber = formatNumber;

    function applySmartCombo(element) {
        $(element).attr('type', 'SmartCombo');
        var lnk = '?r=cwf/fwShell/main/lookup2&namedlookup=' + $(element).attr('data-NamedLookup') +
                '&displaymember=' + $(element).attr('data-DisplayMember') +
                '&valuemember=' + $(element).attr('data-ValueMember');
        var cfilter = $(element).attr('data-filter');
        var selctr = $(element).select2({
            placeholder: 'Enter name',
            minimumInputLength: 0,
            dropdownAutoWidth: true,
            ajax: {
                url: lnk,
                dataType: 'json',
                cache: "true",
                delay: 250,
                data: function (term, page, arg1) {
                    var filter = '';
                    var filterevent = $(element).attr('filterevent');
                    var datacontext = ko.dataFor(element);
                    if (filterevent !== undefined && filterevent !== '') {
                        var tempFunction = new Function("fltr", "datacontext", "return " + filterevent + "(fltr, datacontext)");
                        filter = tempFunction(cfilter, datacontext);
                    } else {
                        filter = $(element).attr('data-filter');
                    }
                    var query = {
                        filter: filter,
                        term: term,
                        page: page,
                        page_limit: 50
                    }
                    return query;
                },
                results: function (data, page) {
                    return {
                        results: data.results,
                        more: data.results.length >= 50
                    };
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            },
            initSelection: function (element, callback) {
                var lookupid = $(element).attr('data-NamedLookup') + '|' + $(element).attr('data-DisplayMember') + '|'
                        + $(element).attr('data-ValueMember') + '|' + $(element).attr('id');
                var valueid = $(element).val();
                var valfound = false;
                if (valueid == -1) {
                    var data = {
                        id: valueid,
                        text: 'Select an option'
                    };
                    callback(data);
                    return false;
                }
                // try lookup cache
                var lcitm = coreWebApp.lookupCache.get($(element).attr('id'), $(element).val());
                if (lcitm !== undefined) {
                    callback(lcitm);
                    return false;
                }
                if (typeof coreWebApp.ModelBo !== 'undefined' && typeof coreWebApp.ModelBo.preLookupData !== 'undefined') {
                    $.each(coreWebApp.ModelBo.preLookupData, function (index, pld) {
                        if (pld.lookupid == lookupid && pld.valueid == valueid) {
                            var data = {
                                id: valueid,
                                text: pld.dispText
                            };
                            callback(data);
                            valfound = true;
                            return false;
                        }
                    });
                }
                if (!valfound) {
                    $.getJSON(lnk + '&filter=' + cfilter + "&id=" + (element.val()), function (data) {
                        return callback(data);
                    });
                }
            },
            escapeMarkup: function (markup) {
                // This shows the selected item in the combo
                if (typeof markup != 'undefined' && markup.indexOf("<span") == 0) {
                    return $(markup).text();
                }
                return markup;
            },
            formatResult: function (object, container, query) {
                // This displays the items for selection when the dropdown is open
                return object.text;
            }
        });
        selctr.on('change', function (e) {
            newval = $(this).val();
            var onvalchange = $(this).attr('on-change-event');
            if (typeof onvalchange != 'undefined' && onvalchange != '') {
                var func = new Function("newval", '{return ' + onvalchange + '(newval);}');
                var onvalchangeResult = func(newval);
                if (typeof (onvalchangeResult) != 'undefined' && onvalchangeResult == false) {
                    return false;
                }
            }
        });
    }
    coreWebApp.applySmartCombo = applySmartCombo;

    function applySmartMultiCombo(element) {
        $(element).attr('type', 'SmartCombo');
        var multiples = $(element).attr('multiple');
        var ismulti = false;
        if (typeof multiples != 'undefined' && multiples == 'multiple') {
            ismulti = true;
        }
        var lnk = '?r=cwf/fwShell/main/lookup2&namedlookup=' + $(element).attr('data-NamedLookup') +
                '&displaymember=' + $(element).attr('data-DisplayMember') +
                '&valuemember=' + $(element).attr('data-ValueMember') + '&nodefault=true';
        var cfilter = $(element).attr('data-filter');
        $(element).select2({
            placeholder: 'Enter name',
            minimumInputLength: 0,
            dropdownAutoWidth: true,
            multiple: ismulti,
            ajax: {
                url: lnk,
                dataType: 'json',
                cache: "true",
                delay: 250,
                data: function (term, page, arg1) {
                    var filter = '';
                    var filterevent = $(element).attr('filterevent');
                    var datacontext = ko.dataFor(element);
                    if (filterevent !== undefined && filterevent !== '') {
                        var tempFunction = new Function("fltr", "datacontext", "return " + filterevent + "(fltr, datacontext)");
                        filter = tempFunction(cfilter, datacontext);
                    } else {
                        filter = $(element).attr('data-filter');
                    }
                    var query = {
                        filter: filter,
                        term: term,
                        page: page,
                        page_limit: 50
                    }
                    return query;
                },
                results: function (data, page) {
                    return {
                        results: data.results,
                        more: data.results.length >= 50
                    };
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            },
            initSelection: function (element, callback) {
                var lookupid = $(element).attr('data-NamedLookup') + '|' + $(element).attr('data-DisplayMember') + '|'
                        + $(element).attr('data-ValueMember') + '|' + $(element).attr('id');
                var valueid = $(element).val();
                var valfound = false;
                if (valueid == -1) {
                    var data = {
                        id: valueid,
                        text: 'Select an option'
                    };
                    callback(data);
                    return false;
                }
                if (typeof coreWebApp.ModelBo !== 'undefined' && typeof coreWebApp.ModelBo.preLookupData !== 'undefined') {
                    $.each(coreWebApp.ModelBo.preLookupData, function (index, pld) {
                        if (pld.lookupid == lookupid && pld.valueid == valueid) {
                            var data = {
                                id: valueid,
                                text: pld.dispText
                            };
                            callback(data);
                            valfound = true;
                            return false;
                        }
                    });
                }
                if (!valfound) {
                    $.getJSON(lnk + '&filter=' + cfilter + "&id=" + (element.val()), function (data) {
                        return callback(data);
                    });
                }
            },
            escapeMarkup: function (markup) {
                // This shows the selected item in the combo
                if (typeof markup != 'undefined' && markup.indexOf("<span") == 0) {
                    return $(markup).text();
                }
                return markup;
            },
            formatResult: function (object, container, query) {
                // This displays the items for selection when the dropdown is open
                return object.text;
            }
        });
    }
    coreWebApp.applySmartMultiCombo = applySmartMultiCombo;

    function applyCheckList(element) {
        var lnk = '?r=cwf/fwShell/main/lookup&namedlookup=' + $(element).attr('data-NamedLookup') +
                '&displaymember=' + $(element).attr('data-DisplayMember') +
                '&valuemember=' + $(element).attr('data-ValueMember');
        $.ajax({
            url: lnk,
            type: 'GET',
            success: function (data) {
                results = jQuery.parseJSON(data);
                $.each(results, function (key, value) {
                    cbox = $('<input type="checkbox" value="' + key + '" data-bind="checkedValue: '
                            + key + ', checked: coreWebApp.ModelBo.' + $(element).attr('id')
                            + '"></input>' + value + '<br/>');
                    $(element).append(cbox).trigger('create');
                });
                ko.cleanNode($(element)[0]);
                ko.applyBindings(coreWebApp.ModelBo.atypes, $(element)[0]);
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.applyCheckList = applyCheckList;

    function applyNumber(element) {
        var ifneg = false;
        if (typeof $(element).attr('allownegative') != 'undefined') {
            var neg = $(element).attr('allownegative');
            neg = neg.toLowerCase();
            if (neg == 'true') {
                ifneg = true;
            }
        }
        $(element).numericInput({allowFloat: true, allowNegative: ifneg});
        $(element).css('text-align', 'right');
    }
    coreWebApp.applyNumber = applyNumber;

    function applyDatepicker(element) {
        var dateformat = coreWebApp.dateFormat;
        var end_date = $(element).attr('end_date');
        var start_date = $(element).attr('start_date');
//        if (!$(element).is(":visible")) {
//            return;
//        }
        if (typeof end_date !== typeof undefined && typeof start_date !== typeof undefined) {
            $(element).datepicker({
                format: dateformat,
                autoclose: true,
                endDate: end_date,
                startDate: start_date,
                enableOnReadonly: false,
                todayHighlight: true,
                zIndexOffset: 1040
            })
                    .on('show', function () {
                        previousDate = $(element).val();
                    })
                    .on('hide', function () {
                        if ($(element).val() === '' || $(element).val() === null) {
                            $(element).val(previousDate).datepicker('update');
                        }
                    });
        } else {
            $(element).datepicker({
                format: dateformat,
                autoclose: true,
                enableOnReadonly: false,
                todayHighlight: true,
                zIndexOffset: 1040
            })
                    .on('show', function () {
                        previousDate = $(element).val();
                    })
                    .on('hide', function () {
                        if ($(element).val() === '' || $(element).val() === null) {
                            $(element).val(previousDate).datepicker('update');
                        }
                    });
        }
    }
    coreWebApp.applyDatepicker = applyDatepicker;

    function getTranCal(tranItem, mainpropph, depprop, trueval) {
        var mainpropphlower = mainpropph;
        var truevallower = trueval;

        if (typeof (mainpropph) == 'string') {
            mainpropphlower = mainpropph.toLowerCase();
            truevallower = trueval.toLowerCase()
        }

        if (mainpropphlower != truevallower) {
            if (depprop.indexOf('amt') > -1) {
                tranItem[depprop](0);
            } else if (depprop.indexOf('_id') > -1) {
                tranItem[depprop](-1);
            } else {
                tranItem[depprop]('');
            }
            return false;
        } else {
            return true;
        }
    }
    coreWebApp.getTranCal = getTranCal;

    function getvisTranCal(tranItem, mainpropph, depprop, trueval) {
        var mainpropphlower = mainpropph;
        var truevallower = trueval;

        if (typeof (mainpropph) == 'string') {
            mainpropphlower = mainpropph.toLowerCase();
            truevallower = trueval.toLowerCase()
        }

        if (mainpropphlower != truevallower) {
            if (depprop.indexOf('amt') > -1) {
                tranItem[depprop](0);
            } else if (depprop.indexOf('_id') > -1) {
                tranItem[depprop](-1);
            }
            return false;
        } else {
            return true;
        }
    }
    coreWebApp.getvisTranCal = getvisTranCal;

    function selectid(id) {
        $('#selectedid').val(id);
        document.forms[0].submit(id);
    }
    coreWebApp.selectid = selectid;

    function onNewClick(mydata, mydiv, nodiv, before, after, afterload) {
        if (typeof before != 'undefined' && before != '' && before != null) {
            var func = new Function('{' + before + '();}');
            func();
        }
        $.ajax({
            url: mydata,
            type: 'GET',
            data: {'reqtime': new Date().getTime()},
            success: function (resultdata) {
                if (typeof mydiv === 'undefined') {
                    $('#content-root').html(resultdata);
                } else {
                    $('#' + mydiv).html(resultdata);
                    if (typeof nodiv !== 'undefined') {
                        $('#' + nodiv).hide();
                    }
                    $('#' + mydiv).show();
                }
                if ($('#bo-form').length !== 0) {
                    var htcontents = $('#content-root').height();
                    $('#cboformbody').height(htcontents * 0.9);
                    var aftld = (typeof afterload != 'undefined' && afterload != '' && afterload != null) ? afterload : '';
                    var ld = (typeof after != 'undefined' && after != '' && after != null) ? after : '';
                    coreWebApp.GetModel('#bo-form', aftld, ld);
//                    if (typeof after != 'undefined' && after != '' && after != null) {
//                        if (typeof afterload != 'undefined' && afterload != '' && afterload != null) {
//                            coreWebApp.GetNewModel('#bo-form', after, afterload);
//                        } else {
//                            coreWebApp.GetNewModel('#bo-form', after);
//                        }
//                    } else {
//                        if (typeof afterload != 'undefined' && afterload != '' && afterload != null) {
//                            coreWebApp.GetModel('#bo-form', afterload);
//                        } else {
//                            coreWebApp.GetModel('#bo-form');
//                        }
//                    }
                } else if ($('#wiz-form').length !== 0) {
                    applysmartcontrols($('#wiz-form'));
                    $('#details').height($('#wiz-form').height() + 55);
                } else if ($('#custom-form').length !== 0) {
                    applysmartcontrols($('#custom-form'));
                    $('#details').height($('#custom-form').height() + 55);
                } else if ($('#rptOptions').length !== 0) {
                    applysmartcontrols($('#rptOptions'));
                } else if ($('.tree').length !== 0) {
                    applysmartcontrols($('.tree'));
                }
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                stoploading();
            }
        });
    }
    coreWebApp.onNewClick = onNewClick;

    var modchart;
    var modcharts = new Object();
    var modchartid = '';
    function makechart(mydiv) {
        $('#cDashboard').height($('#' + mydiv).height() - 30);
        plotdata = $.parseJSON(plotData);
        for (i = 0; i < plotdata.plots.length; i++) {
            $('#' + plotdata.plots[i].placeholder).height($('#content-root').height() * 0.3);
            if (plotdata.plots[i].isCustom || plotdata.plots[i].isTwig) {
                $('#' + plotdata.plots[i].placeholder).parents('.dbwidgetmain').first().css('width', '99%');
                $('#' + plotdata.plots[i].placeholder).addClass('dbcwidget');
                $('#' + plotdata.plots[i].placeholder).css('margin', '0');
                $('#' + plotdata.plots[i].placeholder).css('overflow', 'auto');
                $('#' + plotdata.plots[i].placeholder).find('#contentholder').css('margin', '0');
                $('#' + plotdata.plots[i].placeholder).find('#contentholder').css('min-width', '99%');
                $('#' + plotdata.plots[i].placeholder).find('#contentholder').css('width', '99%');
                continue;
            } else {
                $('#' + plotdata.plots[i].placeholder).addClass('dbwidget');
            }
            var chartdata2 = [];
            plotdiv = '#' + plotdata.plots[i].placeholder;
            $(plotdiv).height($(plotdiv).height() - 20);
            var plottype = null;
            if (plotdata.plots[i].plotType == 'pie') {
                plottype = 'pie';
                var abc = {};
                for (var x in plotdata.plots[i]) {
                    if (x != 'data') {
                        abc[x] = plotdata.plots[i][x];
                    }
                }
                var chartdata = [];
                abc['data'] = plotdata.plots[i].data.data;
                chartdata2 = abc;
            } else if (plotdata.plots[i].plotType == 'stack') {
                plottype = 'stack';
                for (j = 0; j < plotdata.plots[i].data.length; j++) {
                    var abc = {};
                    var chartdata = [];
                    for (var x in plotdata.plots[i].data[j].data) {
                        chartdata.push([x, plotdata.plots[i].data[j].data[x]]);
                    }

                    abc['data'] = chartdata;
                    chartdata2[j] = chartdata;
                }
            } else if (plotdata.plots[i].plotType == 'grid') {
                plottype = 'grid';
                chartdata2 = plotdata.plots[i].data;
                modchartid = plotdata.plots[i].plotid;
                $('#' + plotdata.plots[i].placeholder).css('margin', '0');
                $(plotdiv).height($(plotdiv).height() + 20);
            } else if (plotdata.plots[i].isCustom || plotdata.plots[i].isTwig) {
                plottype = 'custom';
                $('#' + plotdata.plots[i].placeholder).html($('#' + plotdata.plots[i].dbdrender));
            } else {
                for (j = 0; j < plotdata.plots[i].data.length; j++) {
                    var abc = {};
                    for (var x in plotdata.plots[i].data[j]) {
                        if (x != 'data') {
                            abc[x] = plotdata.plots[i].data[j][x];
                        }
                    }
                    var chartdata = [];
                    for (var x in plotdata.plots[i].data[j].data) {
                        chartdata.push([x, plotdata.plots[i].data[j].data[x]]);
                    }

                    abc['data'] = chartdata;
                    chartdata2[j] = abc;
                }
            }

            modchart = chartdata2;
            modcharts[plotdata.plots[i].placeholder] = chartdata2;
            var plotz;
            if (plottype !== null && plottype === 'pie') {
                plotz = $.plot(plotdiv, chartdata2.data,
                        {
                            series: {pie: {show: true}},
                            grid: {"borderWidth": 0, "hoverable": true}
                        });
            } else if (plottype !== null && plottype === 'stack') {
                plotz = $.plot(plotdiv, chartdata2,
                        {
                            series: {stack: true, bars: {show: true, barWidth: 0.15, align: 'center'}},
                            xaxis: {mode: "categories", tickLength: 0},
                            grid: {borderWidth: 0, hoverable: true}
                        });
            } else if (plottype !== null && plottype === 'grid') {
                $(plotdiv).html(chartdata2);
                if (modchartid != '') {
                    table = $('#' + modchartid).DataTable({
                        "scrollY": (parseInt(($('#content-root').height() * 0.3)) - 52) + 'px',
                        "scrollCollapse": true,
                        "paging": false,
                        "searching": false, "info": false
                    });
                    modchartid = '';
                }
                $('.dataTables_empty').text('No data to display');

                $('.dataTables_scroll').css('overflow', 'auto');
                $('.dataTables_scrollHead').css('overflow', '');
                $('.dataTables_scrollBody').css('overflow', '');
                $('.dbwidgetmain').css('overflow', 'hidden');
                $('.dataTables_scrollHeadInner').css('padding-right', '0');
            } else {
                plotz = $.plot(plotdiv, chartdata2,
                        {
                            //canvas:true,
                            xaxis: {"mode": "categories", "tickLength": 0,
                                tickFormatter: function (val, axis) {
                                    alert(val);
                                    return coreWebApp.formatNumber(val);
                                }},
                            grid: {"borderWidth": 0, "hoverable": true, clickable: true}
                        });
            }

            $(plotdiv).bind('plothover', function (event, pos, item) {
                if (item) {
                    var x = item.datapoint[0].toFixed(2);
                    var y = Array.isArray(item.datapoint[1]) ?
                            item.datapoint[1][0][1]
                            : item.datapoint[1];
                    var msg = '';
                    if (item.series !== undefined && item.series.label !== undefined) {
                        msg = item.series.label + ": " + y;
                    } else {
                        msg = y;
                    }
                    var posx, posy;
                    if (item.pageX == undefined) {
                        posx = $(this).offset().left;
                    } else {
                        posx = item.pageX - $('#sidemenu').width() + 5;
                    }
                    if (item.pageY == undefined) {
                        posy = $(this).offset().top;
                    } else {
                        posy = item.pageY - $('.navbar-inverse').height() - 5;
                    }


                    $("#tooltip").html(msg)
                            .css({top: posy, left: posx})
                            .fadeIn(200);

                } else {
                    $("#tooltip").hide();
                }
            });

            $(plotdiv).bind("plotclick", function (event, pos, item) {
                if (item) {
                    var dbdinfo = new Object();
                    dbdinfo.plot_id = $(this).attr("placeholder");
                    dbdinfo.x_value = item.series.data[item.dataIndex][0];
                    dbdinfo.y_value = item.series.data[item.dataIndex][1];
                    dbdinfo.seriesIndex = item.seriesIndex + 1;
                    if ($(this).attr("widgetmethod") != undefined
                            && $(this).attr("widgetmethod") != '') {
                        var tempFunction = new Function("dbdinfo", "return "
                                + $(this).attr("widgetmethod") + "(dbdinfo)");
                        var dbdo = tempFunction(dbdinfo);
                    }
                }
            });

        }
        $("<div id='tooltip'></div>").css({
            position: "absolute",
            display: "none",
            border: "1px solid #fdd",
            padding: "2px",
            "background-color": "#fee",
            opacity: 0.80
        }).appendTo("#cDashboard");
    }
    coreWebApp.makechart = makechart;

    function maxWidget(ctr, hdr, plottype, plotid) {
        if (plottype !== null && plottype === 'grid') {
            maxWidget2(ctr, hdr, true, plotid);
            return false;
        }
        var dialog_html;
        var ht = parseInt($('#cDashboard').height());
        var wd = parseInt($('#cDashboard').width());// style="height:'+(ht-50)+'px;width:'+(wd-30)+'px;"//style="height:'+(ht-150)+'px;width:'+(wd-80)+'px;"
        dialog_html = '<div id="widget-dialog1"><div id="widget-dialog" style="height:' + (ht - 110) + 'px;" ></div></div>';
        var dlg = $(dialog_html).dialog({
            title: hdr,
            modal: true,
            width: wd - 50, height: ht - 50,
            create: function (event, ui) {},
            close: function (event, ui) {
                $(this).dialog("destroy").remove();
            },
            open: function () {
                $(this).closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon-closethick' style='font-weight:bold;'>X</span>")
                        .css('float', 'right').height('25px');
            }
        });

        $(".ui-dialog").css('z-index', '999');
        var plotz;
        var plotdiv = '#widget-dialog';
        chartdata2 = modcharts[ctr];
        if (plottype !== null && plottype === 'pie') {
            plotz = $.plot(plotdiv, chartdata2.data,
                    {
                        series: {pie: {show: true}},
                        grid: {"borderWidth": 0, "hoverable": true}
                    });
        } else if (plottype !== null && plottype === 'stack') {
            plotz = $.plot(plotdiv, chartdata2,
                    {
                        series: {stack: true, bars: {show: true, barWidth: 0.15, align: 'center'}},
                        xaxis: {mode: "categories", tickLength: 0},
                        grid: {borderWidth: 0, hoverable: true}
                    });
        } else {
            plotz = $.plot(plotdiv, chartdata2,
                    {
                        //canvas:true,
                        xaxis: {"mode": "categories", "tickLength": 0,
                            tickFormatter: function (val, axis) {
                                alert(val);
                                return coreWebApp.formatNumber(val);
                            }},
                        grid: {"borderWidth": 0, "hoverable": true, clickable: true}
                    });
        }

        $(plotdiv).bind('plothover', function (event, pos, item) {
            if (item) {
                var x = item.datapoint[0].toFixed(2);
                var y = Array.isArray(item.datapoint[1]) ?
                        item.datapoint[1][0][1]
                        : item.datapoint[1];
                var msg = '';
                if (item.series !== undefined && item.series.label !== undefined) {
                    msg = item.series.label + ": " + y;
                } else {
                    msg = y;
                }
                var posx, posy;
                if (item.pageX == undefined) {
                    posx = $(this).offset().left;
                } else {
                    posx = item.pageX - $('#sidemenu').width() + 5;
                }
                if (item.pageY == undefined) {
                    posy = $(this).offset().top;
                } else {
                    posy = item.pageY - 100;
                }


                $("#tooltip2").html(msg)
                        .css({top: posy, left: posx})
                        .fadeIn(200);

            } else {
                $("#tooltip").hide();
            }
        });

        $(plotdiv).bind("plotclick", function (event, pos, item) {
            if (item) {
                var dbdinfo = new Object();
                dbdinfo.plot_id = $(this).attr("placeholder");
                dbdinfo.x_value = item.series.data[item.dataIndex][0];
                dbdinfo.y_value = item.series.data[item.dataIndex][1];
                dbdinfo.seriesIndex = item.seriesIndex + 1;
                if ($(this).attr("widgetmethod") != undefined
                        && $(this).attr("widgetmethod") != '') {
                    var tempFunction = new Function("dbdinfo", "return "
                            + $(this).attr("widgetmethod") + "(dbdinfo)");
                    var dbdo = tempFunction(dbdinfo);
                }
            }
        });

        $("<div id='tooltip2'></div>").css({
            position: "absolute",
            display: "none",
            border: "1px solid #fdd",
            padding: "2px",
            "background-color": "#fee",
            opacity: 0.80
        }).appendTo("#widget-dialog");

        return false;
    }
    coreWebApp.maxWidget = maxWidget;

    function maxWidget2(ctr, hdr, isgrid, plotid) {
        var dialog_html;
        var ht = parseInt($('#cDashboard').height());
        var wd = parseInt($('#cDashboard').width());
        dialog_html = '<div id="widget-dialog1" style="height:' + (ht - 50) + 'px;width:' + (wd - 50) + 'px;">' +
                '<div id="widget-dialog" style="padding:0 1px;" >' + $('#' + ctr).html() + '</div></div>';
        var dlg = $(dialog_html).dialog({
            title: hdr,
            modal: true,
            width: wd - 50, height: ht - 50,
            create: function (event, ui) {},
            close: function (event, ui) {
                $(this).dialog("destroy").remove();
            },
            open: function () {
                $(this).closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                        .html("<span class='ui-button-icon-primary ui-icon-closethick' style='font-weight:bold;'>X</span>")
                        .css('float', 'right').height('25px');
            }
        });
        if (isgrid == true) {
            $('#widget-dialog').html(modcharts[ctr]);
            if (plotid != '') {
                $($('#widget-dialog').find('#' + plotid)).attr('id', plotid + '_w');
                table = $('#' + plotid + '_w').DataTable({
                    "scrollY": (ht - 150) + 'px', "scollX": true,
                    "scrollCollapse": true,
                    "paging": false,
                    "searching": false, "info": false
                });
            }
            $('.dataTables_empty').text('No data to display');
            $('.dataTables_scroll').css('overflow', 'hidden');
            $('.dataTables_scrollHead').css('overflow', '');
            $('.dataTables_scrollBody').css('overflow', 'auto');
            $('.dbwidgetmain').css('overflow', 'hidden');
            $('.dataTables_scrollHeadInner').css('padding-right', '0');
        }
        return false;
    }
    coreWebApp.maxWidget2 = maxWidget2;

    function rendercontents(mydata, mydiv, nodiv, afterloadevent) {
        $.ajax({
            url: mydata,
            type: 'GET',
            data: {'reqtime': new Date().getTime()},
            success: function (resultdata) {
                if (typeof mydiv === 'undefined' || mydiv === '') {
                    $('#content-root').html(resultdata);
                } else {
                    $('#' + mydiv).html(resultdata);
                    if (typeof nodiv !== 'undefined') {
                        $('#' + nodiv).hide();
                    }
                    $('#' + mydiv).show();
                }

                if ($('#bo-form').length !== 0) {
                    coreWebApp.GetModel('#bo-form', afterloadevent);
                    var htcontents = $('#content-root').height();
                    $('#cboformbody').height(htcontents * 0.9);
                    stght = 0;
                    if ($('#doc_stage_info').length > 0) {
                        stght = $('#doc_stage_info').height();
                    }
                    $('#cboformbody').height($('#cboformbody').height() - stght);
                } else if ($('#wiz-form').length !== 0) {
                    $('#wiz-form').find('input').each(function () {
                        if ($(this).hasClass('smartcombo')) {
                            //coreWebApp.applySmartCombo(this);
                        } else if ($(this).hasClass('datetime')) {
//                            coreWebApp.applyDatepicker(this);
//                            var dtval = $(this).attr('value');
//                            if(dtval != $(this).val()) {
//                                if(typeof dtval != 'undefined' && Date.parse(dtval)) {
//                                    $(this).val(dtval);
//                                }
//                            }
                        } else if ($(this).attr('type') == 'decimal') {
                            coreWebApp.applyNumber(this);
                        }
                    });
                    var htcontents = $('#content-root').height();
                    $('#details').height(htcontents * 0.98);
//                    $('#details').height($('#wiz-form').height() + 55);
                } else if ($('#custom-form').length !== 0) {
                    $('#custom-form').height($('#content-root').height() - 60);
                    $('#details').height($('#custom-form').height());
                    if ($('#dataTables_scrollBody').length !== 0) {
                        $('#dataTables_scrollBody').height($('#custom-form').height() - 65);
                        $('#dataTables_scrollBody').width($('#custom-form').width());
                    }
                } else if ($('#rptOptions').length !== 0) {
                    applysmartcontrols($('#rptOptions'));
                } else if ($('.tree').length !== 0) {
                    applysmartcontrols($('#thelist'));
                    applySmartCombo(($('#divsearch').find('.smartcombo'))[0]);
                    $('#thelistdiv').height($('#content-root').height() - 110);
                }

                if ($('#detailsat').find('#custom-form').length != 0 && $('#detailsat').is(':visible')) {
//                    $('#detailsat').css('margin-top','5px');
                    var htcroot = parseInt($('#content-root').height());
                    $('#custom-form').height(htcroot - 5);
                    $('#detailsat').height($('#custom-form').height());
                    if ($('#dataTables_scrollBody').length !== 0) {
                        $('#dataTables_scrollBody').height(htcroot - 65);
                        $('#dataTables_scrollBody').width($('#content-root').width() - 30);
                        if ($('#thelist2').length != 0) {
                            $('#thelist2').height($('#custom-form').height() - 15);
                            $('#thelist2').width($('#content-root').width() - 30);
                        }
                    }
                    $('#thelist2').find('tbody').height($('#dataTables_scrollBody').height() - $('#thelist2').find('thead').height());
                    $('#thelist2').find('thead').css('display', 'block');
                    $('#thelist2').find('tbody').css('display', 'block');
                    $('#content-root').css('overflow', 'hidden');
                    $('#dataTables_scrollBody').css('overflow', 'auto');
                    if (typeof afterloadevent != 'undefined' && afterloadevent != '') {
                        var func = new Function('{' + afterloadevent + '();}');
                        func();
                    }
                }

                if ($('[logonselect="true"]').length == 1) {
                    $('#content-root').find(".mycontainer").first().css('padding-top', '0px');
                    rejust('companyinfo');
                }

                if ($('#cDashboard').length !== 0 && $('#cDashboard').is(':visible')) {
                    if (typeof mydiv == 'undefined') {
                        makechart('content-root');
                    } else {
                        makechart(mydiv);
                    }
                }
            },
            error: function (err) {
                var errMsg = err.responseText === undefined ? err.message : err.responseText;
                toastmsg('error', 'Failed to Fetch Data', errMsg, false);
                stoploading();
                // Disable Save/Action buttons when Get Model caused errors
                $('#cmdsave').hide();
                $('#btn-action').hide();
            }
        });
    }
    coreWebApp.rendercontents = rendercontents;

    function initCollection(tblid) {
        $('#contents').height($('#content-root').height() - 50);
        vtable = $('#' + tblid).DataTable({
            "scrollY": getscrollheight() + 'px',
            "scrollCollapse": true,
            "paging": false,
            "searching": true, "info": false,
        });
        $('.dataTables_empty').text('No data to display');
        $('.dataTables_scrollBody').height(400);
        $('.dataTables_scrollBody').css("min-height", ($('.dataTables_scrollBody').height()).toString() + 'px');
    }
    coreWebApp.initCollection = initCollection;

    function startloading() {
        $("#overlay").css("display", "block");
        $(function () {
            var docHeight = $(document).height();
            $("body").append("<div id='overlay2' class='Centerer'><div class='loading'></div>" +
                    "</div>");
            $("#overlay2").height(docHeight);
        });
    }
    coreWebApp.startloading = startloading;

    function stoploading() {
        $("#overlay2").remove();
    }
    coreWebApp.stoploading = stoploading;

    function wizNext(event) {
        event.preventDefault;
        var fdata = this;
        $.ajax({
            url: $('#wizlink').val(),
            type: 'POST',
            data: {'formName': $('#formName').val(), 'step': $('#step').val(),
                'formdata': ko.mapping.toJSON(this), 'oldStepData': oldStepData,
                'operation': 'next', 'reqtime': new Date().getTime()},
            success: function (resultdata) {
                //ko.cleanNode($('#wiz-form')[0]);
                $('#details').html(resultdata);
                setwiz();
                if (brule != 'undefined') {
                    if (brule.length > 0) {
                        //                            ko.cleanNode($('#wiz-form')[0]);
                        //                            ko.applyBindings(fdata);
                        //                            applysmartcontrols();
                    }
                }
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
        return false;
    }
    coreWebApp.wizNext = wizNext;

    function setwiz() {
        if ($('#wiz-form').length !== 0) {
            $('#details').height($('#wiz-form').height() + 55);
            applysmartcontrols($('#wiz-form'));
        } else if ($('#bo-form').length !== 0) {
            $('#bo-form').show();
            var wizafterload = '';
            if (typeof formafterload != 'undefined') {
                wizafterload = formafterload;
            }
            coreWebApp.GetModel('#bo-form', wizafterload);
            var htcontents = $('#content-root').height();
            $('#details').height(htcontents - 10);
            $('#cboformbody').height(htcontents - 60);
            stght = 0;
            if ($('#doc_stage_info').length > 0) {
                stght = $('#doc_stage_info').height();
            }
            $('#cboformbody').height($('#cboformbody').height() - stght);
        }
    }
    coreWebApp.setwiz = setwiz;

    function wizPrev(event) {
        event.preventDefault;
        $.ajax({
            url: $('#wizlink').val(),
            type: 'GET',
            data: {'formName': $('#formName').val(), 'step': $('#step').val(),
                'oldStepData': oldStepData,
                'operation': 'prev', 'reqtime': new Date().getTime()},
            success: function (resultdata) {
                ko.cleanNode($('#wiz-form')[0]);
                $('#details').html(resultdata);
                $('#details').height($('#wiz-form').height() + 60);
                applysmartcontrols($('#wiz-form'));
            },
            error: function (data) {
                toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
        return false;
    }
    coreWebApp.wizPrev = wizPrev;

    function getscrollheight() {
        r1 = parseInt($('#collheader').height());
        r2 = parseInt($('#collfilter').height());
        cntht = parseInt($('#contents').height());
        var calht = cntht - r1 - r2 - 100;
        return calht;
    }
    coreWebApp.getscrollheight = getscrollheight;

    function toggleUpdate() {
        $('#cmdupdatebankreco').hide();
        if ($("#view_type_id option:selected").text() !== 'All') {
            if (coreWebApp.ModelBo.dt().length > 0) {
                $('#cmdupdatebankreco').show();
            }
        }
    }
    coreWebApp.toggleUpdate = toggleUpdate;
    
    coreWebApp.lookupCache = {
        cacheMap: new Map(),
        add: function(field, id, text) {
            var lid = field + '|' + id;
            if (coreWebApp.lookupCache.cacheMap.has(lid)) {
                itm = coreWebApp.lookupCache.cacheMap.get(lid);
                itm.id = id;
                itm.text = text;
            } else {
                var itm = {
                    id: id,
                    text: text
                };
                coreWebApp.lookupCache.cacheMap.set(lid, itm);
            }
        },
        get: function(field, id) {
            var lid = field + '|' + id;
            return coreWebApp.lookupCache.cacheMap.get(lid);
        },
        reset: function() {
            coreWebApp.lookupCache.cacheMap = new Map();
        }
    }
    
    function addLookupCache(field, id, text) {
        
    }
    coreWebApp.addLookupCache = addLookupCache;

    function trigger_change(elid, valueid, dispText) {
        var el = $('#' + elid);
        var lookupid = $(el).attr('data-NamedLookup') + '|' + $(el).attr('data-DisplayMember') + '|'
                + $(el).attr('data-ValueMember') + '|' + $(el).attr('id');
        var pld = {
            lookupid: lookupid,
            valueid: valueid,
            dispText: dispText
        };
        if (typeof coreWebApp.ModelBo !== 'undefined') {
            // This avoids a round trip to the server
            coreWebApp.ModelBo.preLookupData.push(pld);
        }
        var items = $('[id="' + elid + '"]');
        $.each(items, function () {
            $(this).trigger('change');
        });
    }
    coreWebApp.trigger_change = trigger_change;

    function trigger_change_el(el, valueid, dispText) {
        if ($(el).attr('data-NamedLookup') == undefined) {
            return;
        }
        var lookupid = $(el).attr('data-NamedLookup') + '|' + $(el).attr('data-DisplayMember') + '|'
                + $(el).attr('data-ValueMember') + '|' + $(el).attr('id');
        var pld = {
            lookupid: lookupid,
            valueid: valueid,
            dispText: dispText
        };
        if (typeof coreWebApp.ModelBo !== 'undefined') {
            // This avoids a round trip to the server
            coreWebApp.ModelBo.preLookupData.push(pld);
        }
        $(el).trigger('change');
    }
    coreWebApp.trigger_change_el = trigger_change_el;

    function detectIE() {
        var ua = window.navigator.userAgent;

        var msie = ua.indexOf('MSIE ');
        if (msie > 0) {
            // IE 10 or older
            return parseInt(ua.substring(msie + 5, ua.indexOf('.', msie)), 10);
        }

        var trident = ua.indexOf('Trident/');
        if (trident > 0) {
            // IE 11
            var rv = ua.indexOf('rv:');
            return parseInt(ua.substring(rv + 3, ua.indexOf('.', rv)), 10);
        }

        var edge = ua.indexOf('Edge/');
        if (edge > 0) {
            // IE 12
            return parseInt(ua.substring(edge + 5, ua.indexOf('.', edge)), 10);
        }

        // other browser
        return false;
    }
    coreWebApp.detectIE = detectIE;

    function modcolor(p, from, to) {
        if (typeof (p) != "number" || p < -1 || p > 1 || typeof (from) != "string" || (from[0] != 'r' && from[0] != '#') || (typeof (to) != "string" && typeof (to) != "undefined"))
            return null; //ErrorCheck
        if (!this.sbcRip)
            this.sbcRip = function (d) {
                var l = d.length, RGB = new Object();
                if (l > 9) {
                    d = d.split(",");
                    if (d.length < 3 || d.length > 4)
                        return null;//ErrorCheck
                    RGB[0] = i(d[0].slice(4)), RGB[1] = i(d[1]), RGB[2] = i(d[2]), RGB[3] = d[3] ? parseFloat(d[3]) : -1;
                } else {
                    if (l == 8 || l == 6 || l < 4)
                        return null; //ErrorCheck
                    if (l < 6)
                        d = "#" + d[1] + d[1] + d[2] + d[2] + d[3] + d[3] + (l > 4 ? d[4] + "" + d[4] : ""); //3 digit
                    d = i(d.slice(1), 16), RGB[0] = d >> 16 & 255, RGB[1] = d >> 8 & 255, RGB[2] = d & 255, RGB[3] = l == 9 || l == 5 ? r(((d >> 24 & 255) / 255) * 10000) / 10000 : -1;
                }
                return RGB;
            }
        var i = parseInt, r = Math.round, h = from.length > 9, h = typeof (to) == "string" ? to.length > 9 ? true : to == "c" ? !h : false : h, b = p < 0, p = b ? p * -1 : p, to = to && to != "c" ? to : b ? "#000000" : "#FFFFFF", f = sbcRip(from), t = sbcRip(to);
        if (!f || !t)
            return null; //ErrorCheck
        if (h)
            return "rgb(" + r((t[0] - f[0]) * p + f[0]) + "," + r((t[1] - f[1]) * p + f[1]) + "," + r((t[2] - f[2]) * p + f[2]) + (f[3] < 0 && t[3] < 0 ? ")" : "," + (f[3] > -1 && t[3] > -1 ? r(((t[3] - f[3]) * p + f[3]) * 10000) / 10000 : t[3] < 0 ? f[3] : t[3]) + ")");
        else
            return "#" + (0x100000000 + (f[3] > -1 && t[3] > -1 ? r(((t[3] - f[3]) * p + f[3]) * 255) : t[3] > -1 ? r(t[3] * 255) : f[3] > -1 ? r(f[3] * 255) : 255) * 0x1000000 + r((t[0] - f[0]) * p + f[0]) * 0x10000 + r((t[1] - f[1]) * p + f[1]) * 0x100 + r((t[2] - f[2]) * p + f[2])).toString(16).slice(f[3] > -1 || t[3] > -1 ? 1 : 3);
    }
    coreWebApp.modcolor = modcolor;

    function toastmsg(ttype, ttitle, tmsg, tforcestop) {
        ttimeout = 0;
        showclose = false;
        switch (ttype) {
            case 'success':
                iziToast.success({title: ttitle, message: tmsg, position: 'topCenter', progressBar: false, close: true,
                    transitionIn: 'fadeIn', transitionOut: 'fadeOut', animateInside: false, layout: 2, timeout: 2000});
                break;
            case 'error':
                iziToast.error({title: ttitle, message: tmsg, position: 'topCenter', progressBar: false, close: true,
                    transitionIn: 'fadeIn', transitionOut: 'fadeOut', animateInside: false, layout: 2, timeout: false});
                break;
            case 'warning':
                iziToast.warning({title: ttitle, message: tmsg, position: 'topCenter', progressBar: false, close: true,
                    transitionIn: 'fadeIn', transitionOut: 'fadeOut', animateInside: false, layout: 2, timeout: 2000});
                break;
            default:
                iziToast.info({title: ttitle, message: tmsg, position: 'topCenter', progressBar: false, close: true,
                    transitionIn: 'fadeIn', transitionOut: 'fadeOut', animateInside: false, layout: 2, timeout: 3000});
                break;
        }
    }
    coreWebApp.toastmsg = toastmsg;

    function bs_prompt(ntype, nmsg, truefunction, falsefunction) {
        var mtype = BootstrapDialog.TYPE_DEFAULT;
        switch (ntype) {
            case 'error':
                mtype = BootstrapDialog.TYPE_DANGER;
                break;
            case 'warning':
                mtype = BootstrapDialog.TYPE_WARNING;
                break;
            default:
                mtype = BootstrapDialog.TYPE_INFO;
                break;
        }
        var bsres = false;
        BootstrapDialog.confirm({
            type: mtype,
            title: '',
            message: nmsg,
            animate: false,
            callback: function (result) {
                if (result) {
                    if (typeof (truefunction) != 'undefined' && truefunction != '') {
                        truefunction();
                    } else {
                        bsres = true;
                    }
                } else {
                    if (typeof (falsefunction) != 'undefined' && falsefunction != '') {
                        falsefunction();
                    } else {
                        bsres = false;
                    }
                }
            }
        });
        return bsres;
    }
    coreWebApp.bs_prompt = bs_prompt;

    function findInTree(searchBox) {
        if (typeof searchBox == 'undefined' ||
                $(searchBox).val() == '' || $(searchBox).val() == null) {
            return;
        }
        var searchText = $(searchBox).select2('data').text;
        $('#thelist').treegrid('collapseAll');
        $('#thelist tr').each(function () {
            $(this).css('background-color', 'white');
            if ($(this).find('td').eq(0).text() == searchText) {
                traverseTree($(this));
                $(this).css('background-color', 'lightgrey');
                $('#thelistdiv').scrollTop($(this).position().top + 100);
            }
        });
    }
    coreWebApp.findInTree = findInTree;

    function traverseTree(node) {
        var pnode = $(node).treegrid('getParentNode');
        if (pnode != null) {
            traverseTree(pnode);
        }
        $(node).treegrid('expand');
    }

    function minimiseside() {
        var sdw = $('#mysidemenu').width();
        var crw = $('#content-root').width();
        var wsp = $('#workspace').width();
        $('#content-root').css('max-width', '100%');
        $('#content-root').width(wsp - 75);
        $('#mysidemenu').width(40);
        $('#sidemenu').hide();
        $('#smallmenu').show();
        $('.vsplitter').hide();
    }
    coreWebApp.minimiseside = minimiseside;

    function maximiseside() {
        var sdw = $('#mysidemenu').width();
        var crw = $('#workspace').width();
        var wsp = $('#workspace').width();
        $('#content-root').css('max-width', '84%');
        $('#mysidemenu').css('width', '16%');
        $('#sidemenu').show();
        $('#smallmenu').hide();
        $('.vsplitter').show();
    }
    coreWebApp.maximiseside = maximiseside;

    function applogout() {
        var res = bs_prompt('warning', 'Are you sure you want to logout?', function () {
            $.ajax({
                url: '?r=site/logout',
                type: 'POST',
                beforeSend: function () {
                    startloading();
                },
                complete: function () {
                    stoploading();
                },
                success: function (result) {
                }
            });
        }, function () {
            return false;
        });
        return false;
    }
    coreWebApp.applogout = applogout;

    function searchVoucher() {
        vchId = $('#srchVchId').val();
        $.ajax({
            url: '?r=cwf/sys/main/search-doc',
            type: 'GET',
            dataType: 'json',
            data: {'docid': vchId, 'reqtime': new Date().getTime()},
            beforeSend: function () {
                startloading();
            },
            complete: function () {
                stoploading();
            },
            success: function (resultdata) {
                var jsonResult = resultdata;
                if (jsonResult['status'] !== 'OK') {
                    toastmsg('warning', 'Search', jsonResult['status'], false);
                    return;
                } else {
                    var res = bs_prompt('success', 'Document found.<br/> Do you want to replace current screen with ' + vchId + ' ?', function () {
                        $('#srchVchId').val('');
                        lnk = '?r=/' + jsonResult['qpRoute'] + 'form&formName=' + jsonResult['qpForm'] + '&formParams={"' + jsonResult['qpKey'] + '":"' + jsonResult['qpid'] + '"}';
                        if ($('#details').length == 0) {
                            $('#content-root').append('<div id="details" class="view-min-width view-window2" style="display: none;"></div>');
                        }
                        var xcontainer = 'contentholder';
                        if ($('#contentholder').length == 0) {
                            xcontainer = 'contents';
                        }
                        rendercontents(lnk, 'details', xcontainer, jsonResult['afterLoadEvent']);
                    }, function () {
                        return false;
                    });
                }
            }
        });

    }
    coreWebApp.searchVoucher = searchVoucher;

}(window.coreWebApp));

$.formUtils.addValidator({
    name: 'smart-combo',
    validatorFunction: function (value, $el, config, language, $form) {
        if ($el.attr('data-validation-optional') != "true") {
            return parseInt(value) !== -1;
        } else {
            return true;
        }
    },
    errorMessage: 'Please select an option',
    errorMessageKey: 'badSelection'
});

function applysmartcontrols(ctrl) {
    if (typeof (ctrl) == 'undefined' || ctrl == null || ctrl == '') {
        ctrl = $('form')[0];
    }

    if ($(ctrl).hasClass('smarttextbox')) {
        applySmartTextBox(ctrl);
    } else if ($(ctrl).hasClass('fc-x-rate')) {
        applyFC(ctrl);
    } else {
        $(ctrl).find('.smarttextbox').each(function () {
            applySmartTextBox(this);
        });
        $('form').find('.fc-x-rate').each(function () {
            applyFC(this);
        });
        $(ctrl).find('.checklist').each(function () {
            coreWebApp.applyCheckList(this);
        });
    }

    $('[type=text],[type=TextBox],[type=checkbox],[type=DateTime],[type=SmartTextBox]').keydown(function (event) {
        if (event.keyCode === 13) {
            event.preventDefault();
        }
    });
    $('[smarttext]').keydown(function (event) {
        if (event.ctrlKey) {
            if (event.which === 65 || event.which === 97) {
                coreWebApp.ModelBo[$(this).attr('id')](eval(($(this).attr('smarttext'))));
            }
        }
    });
    if ($('#thelist').is(':visible')) {
        $('.tree').treegrid({initialState: 'collapsed'});
    }
//    if($('#bo-form').length!==0){
//        $('#details').height($('#bo-form').height()+40);
//    }
    $('[type=checkbox][readonly]').attr('disabled', '');
    //$('[type=SmartCombo][readonly]').attr('disabled','');
    $('input[type=checkbox][data-toggle^=toggle]').bootstrapToggle();
    $('select[readonly]').attr('disabled', '');
    $('#btn-action').click(function () {
        $('.dropdown-menu').css('left', -63);
    });//('left','-63px');
    $(window).unbind('keydown').bind('keydown',
            (function (event) {
                if (event.ctrlKey || event.metaKey) {
                    if (String.fromCharCode(event.which).toLowerCase() == 's') {
                        event.preventDefault();
                        if (typeof coreWebApp === typeof undefined) {
                            return;
                        }
                        if (typeof coreWebApp.ModelBo === typeof undefined) {
                            return;
                        }
                        var stt = coreWebApp.ModelBo.status;
                        if (typeof stt != 'undefined') {
                            if ($('#cmdsave:visible').length > 0 && stt() != 5) {
                                $('#cmdsave').click();
                            }
                        } else {
                            if ($('#cmdsave:visible').length > 0) {
                                $('#cmdsave').click();
                            }
                        }
                        if (typeof stt != 'undefined') {
                            if ($('#cmdproceed:visible').length > 0 && stt() != 5) {
                                $('#cmdproceed').click();
                            }
                        } else {
                            if ($('#cmdproceed:visible').length > 0) {
                                $('#cmdproceed').click();
                            }
                        }
                        return false;
                    }
                }
            }));
    if ($('#bo-form').length > 0) {
        if ($('#cmdclose:enabled:visible').length > 0) {
            var element1 = $('#cboformbodyin').find(":input:not([readonly='true']):not([disabled='disabled']):visible").first();
            focusElement(element1);
            $('#cmdclose').focus();
        } else {
            var element1 = $('#bo-form').find('select:enabled:visible,:input[type="text"]:enabled:visible').first();
            focusElement(element1);
        }
    } else if ($('form').length > 0) {
        if ($('#cmd_addnew:enabled:visible').length > 0) {
            $('#cmd_addnew').focus();
        } else if ($('#btnrefresh:enabled:visible').length > 0) {
            $('#btnrefresh').focus();
        } else {
            var element1 = $('form').find('select:enabled:visible,:input[type="text"]:enabled:visible').first();
            focusElement(element1);
        }
    }
}

function focusElement(element1) {
    if (element1.hasClass('smartcombo')) {
        $(element1).parent().focus();
    } else if (element1.hasClass('datetime')) {
        var ele = element1.parent().nextAll().find('select:enabled:visible,:input[type="text"]:enabled:visible').first();
        focusElement(ele);
    } else {
        element1.focus();
    }
}

function applySmartTextBox(ctrl) {
    var notyetsmart = $(ctrl).attr('notyetsmart');
    if (typeof notyetsmart !== typeof undefined || notyetsmart == true) {
        $(ctrl).attr('type', 'SmartTextBox');
        var lnk = '?r=cwf/fwShell/main/lookup3&namedlookup=' + $(ctrl).attr('data-NamedLookup') +
                '&displaymember=' + $(ctrl).attr('data-DisplayMember')
                + '&filter=' + $(ctrl).attr('data-filter') + '&q=%QUERY';
        var cfilter = $(ctrl).attr('data-filter');
        var textloader = new Bloodhound({
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace,
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            local: textloader,
            remote: {
                url: lnk,
                wildcard: '%QUERY'
            }
        });

        $(ctrl).typeahead({
            hint: false, highlight: false,
        }, {
            name: 'textloader', source: textloader, limit: 10
        }).on('typeahead:selected', function (e, sel) {
            $(ctrl).change();
        });
        $(ctrl).removeAttr('notyetsmart');
        $(ctrl).parent().addClass('form-control');
        $(ctrl).position($(ctrl).parent().position());
        $('.twitter-typeahead').attr('style', 'border:0');
        $(ctrl).attr('style', 'margin-left: -5px;margin-top: -2px;');
    }
}

function applyFC(ctrl) {
    $(ctrl).each(function () {
        var issmart = $(this).attr('smartapplied');
        //if(typeof issmart === typeof undefined || issmart === false){
        var fcfield = $(this).attr('data-fc-field');
        if (typeof coreWebApp.ModelBo == 'undefined')
            return;
        if (coreWebApp.ModelBo.fc_type_id() && coreWebApp.ModelBo.fc_type_id() > 0) {
            changeFC(false);
        } else {
            changeFC(true);
        }
        $('#' + fcfield).change(function () {
            if ($('#' + fcfield).select2('data').text === 'Local') {
                coreWebApp.ModelBo.exch_rate('1.0000');
                //$('.fc-x-rate').hide();
                changeFC(true);
            } else {
                //$('.fc-x-rate').show();
                changeFC(false);
            }
        });
        $(this).attr('smartapplied', 'true');
        //}
    });
    $('.fc-x-rate').change(function () {
        var fcfield = $(this).attr('data-fc-field');
        if ($('#' + fcfield).select2('data').text === 'Local') {
            changeFC(true);
        } else {
            changeFC(false);
        }
    });
}

// next => use knockout computed dependency
function changeFC(islocal) {
    $('*[data-fc-dependent]').each(function (i, item) {
        var localvalctr = $(item).attr('data-fc-dependent');
        if (islocal) {
            $(item).attr('readonly', 'readonly');
            $('[name="' + localvalctr + '"]').each(function (i, itemc) {
                $(itemc).removeAttr('readonly');
            });
        } else {
            $(item).removeAttr('readonly');
            $('[name="' + localvalctr + '"]').each(function (i, itemc) {
                $(itemc).attr('readonly', 'readonly');
            });
        }
    });
}

$(document).ajaxStart(function () {
});

$(document).ajaxComplete(function () {
    $("#overlay").hide();
});
