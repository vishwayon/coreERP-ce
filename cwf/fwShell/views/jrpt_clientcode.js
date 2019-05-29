window.cwf_jrpt = {};

(function (cwf_jrpt) {
    cwf_jrpt.max_pages = 150; // Sets the maximum number of pages to be loaded in html view
    cwf_jrpt.firsttime = true;
    cwf_jrpt.rptInfo;
    cwf_jrpt.afterRefreshEvent = new Function();

    function refreshClick() {
        $('#print-limit-msg').hide();
        var url = $('#rptOptions').attr('action') + '/renderhtml';
        var data = $('#rptOptions').serialize();
        data = data.replace(/=on/g, '=1');
        data = data.replace(/=True/g, '=1');
        $('#rptOptions input[type=checkbox]:not(:checked)').each(
                function () {
                    data += '&' + this.name + '=0';
                });
        data += '&max_pages=' + cwf_jrpt.max_pages;
        $('#rptRoot').show();
        $('#rptRoot').html('');
        var contentHeight = $('#content-root').height();

        var afterEventHandler = $('#afterRefreshEventHandler').attr('value');
        if (afterEventHandler != '' && afterEventHandler != null) {
            cwf_jrpt.afterRefreshEvent = new Function("page", '{ window.' + afterEventHandler + '(page); }');
        } else {
            cwf_jrpt.afterRefreshEvent = new Function();
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata, status, jqXHR) {
                if (jqXHR.getResponseHeader("Output-Type") == "text/html") {
                    $('#rptRoot').html(resultdata);
                } else if (jqXHR.getResponseHeader("Output-Type") == "application/json") {
                    cwf_jrpt.rptInfo = $.parseJSON(resultdata);
                    var data = cwf_jrpt.rptInfo.Data;
                    var rptParent = $('<div class="print-preview-wrapper" id="rptParent" name="rptParent"></div>');
                    rptParent.append(cwf_jrpt.rptInfo.PageStyle);
                    var pg_count = 0;
                    if (cwf_jrpt.rptInfo.PageCount > cwf_jrpt.max_pages) {
                        coreWebApp.toastmsg('warning', 'Report Generation', 'Report too large to view online. First '+cwf_jrpt.max_pages+' pages rendered.</br>Please export to PDF/xls and view', false);
                        $('#print-limit-msg').show();
                        pg_count = cwf_jrpt.max_pages;
                    } else {
                        pg_count = cwf_jrpt.rptInfo.PageCount;
                    }
                    for (i = 0; i < pg_count; i++) {
                        var rptPage = $('<div class="print-format" id="rptPage' + i + '"></div>');
                        rptParent.append(rptPage);
                    }
                    $('#rptRoot').append(rptParent);
                    $('#btnprint').removeAttr('disabled');
                    $('#rptParent').height(contentHeight - $('#rptrow1').height() - 65);
                    for (i = 0; i < pg_count; i++) {
                        var prop = 'Page' + i;
                        var rptPageid = '#rptPage' + i;
                        var htmllink = data[prop];
                        cwf_jrpt.getpage(rptPageid, htmllink, i);
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Status', 'Failed to fetch data', false);
                $('#rptRoot').html(data.responseText);
            }
        });
        adjustHeight();
        return false;
    }
    cwf_jrpt.refreshClick = refreshClick;

    function getpage(pageid, pagelink, pageindex) {
        $.ajax({
            url: pagelink,
            type: 'GET',
            success: function (pagedata) {
                var phtml = $(pagedata);
                phtml.find('img').each(function () {
                    $(this).attr('src', cwf_jrpt.rptInfo.ReportRenderedPath.substring(1, cwf_jrpt.rptInfo.ReportRenderedPath.length) + '/' + $(this).attr('src'));
                });
                var t = phtml.find('table[class=jrPage]');
                t.attr('id', 'jrPage-' + pageindex);
                $(pageid).append(t);
                cwf_jrpt.afterRefreshEvent($(pageid));
            }
        })
    }
    cwf_jrpt.getpage = getpage;

    function printDialogSubmit() {
        var print_choice = $('[name=printact]:checked').val();
        if (print_choice == 'print') {
            printClick();
            $('#divprintdata').dialog('destroy');
        } else if (print_choice == 'export') {
            exportClick($('#btn-export-option').val(), $('#cwf_data_only').is(":checked"));
            $('#divprintdata').dialog('destroy');
        } else if (print_choice == 'email') {
            //docPrintMail();
            emailClick();
            $('#divprintdata').dialog('destroy');
        }
    }
    cwf_jrpt.printDialogSubmit = printDialogSubmit;


    function docPrintMail() {
        var pdata = $('#rptOptions').serialize();
        pdata = pdata.replace(/=on/g, '=1');
        $('#rptOptions input[type=checkbox]:not(:checked):visible').each(
                function () {
                    pdata += '&' + this.name + '=0';
                });
        var emdata = new Object();
        $('#divmaildata :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^mail_')) {
                var fldid = ($(this).attr('id')).replace('mail_', '');
                emdata[fldid] = $(this).val();
            }
        });
        $.ajax({
            url: '?r=cwf/fwShell/jreport/mailreport',
            type: 'POST',
            data: pdata + '&' + jQuery.param(emdata),
            dataType: 'json',
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (result) {
                if(result.status == 'OK') {
                    $('#divmaildata').dialog('destroy');
                    coreWebApp.toastmsg('success', 'Email Report', 'Email is sent for this document.');
                } else {
                    coreWebApp.toastmsg('error', 'Email Report', result.msg);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
    }
    cwf_jrpt.docPrintMail = docPrintMail;

    function printClick() {
        if (coreWebApp.detectIE()) {
            cwf_jrpt.printForIE();
            return;
        }
        var pwin = window.open('');
        var htmldoc = $('<html></html>');
        var head = $('<head>' + cwf_jrpt.rptInfo.PageStyle + '</head>');
        htmldoc.append(head);
        var data = cwf_jrpt.rptInfo.Data;
        // This should be a simple parent div to ensure that it does not take printer page space
        var rptParent = $('<div id="rptParent" name="rptParent"></div>');
        var pg_count = 0;
        if (cwf_jrpt.rptInfo.PageCount > cwf_jrpt.max_pages) {
            pg_count = cwf_jrpt.max_pages;
        } else {
            pg_count = cwf_jrpt.rptInfo.PageCount;
        }
        for (i = 0; i < pg_count; i++) {
            var rptPage = $('<div id="rptPage' + i + '" class="print-format"></div>');
            var rptContainer = $('<div id="t' + i + '"></div>');
            var prop = 'Page' + i;
            var table = $('#jrPage-' + i);
            rptContainer.append(table.clone());
            rptPage.append(rptContainer);
            rptParent.append(rptPage);
            // set the last page margin to Zero.
            // This would suppress the blank page being printed
            if (i == cwf_jrpt.rptInfo.PageCount - 1) {
                rptPage.attr('style', "margin-bottom: 0px;");
            }
        }
        var body = $('<body></body>');
        body.attr('onload', 'pageLoaded()');
        body.append(rptParent);
        htmldoc.append(body);
        var script = pwin.document.createElement('script');
        script.type = 'text/javascript';
        script.text = 'function pageLoaded() { window.print(); window.close(); }';
        htmldoc.append(script);
        pwin.document.write(htmldoc.html());
        pwin.document.close();
        //pwin.close();
    }
    cwf_jrpt.printClick = printClick;

    function printForIE() {
        // Alternate long code for IE 
        // --Begin--
        //var url = '?r=/cwf/fwShell/jreport/print&core-sessionid='+$('#sessionid').val()+'&reqtime='+$('#reqtime').val();
        //window.open(url);
        // --End--
        var pwin = window.open('');
        var htmldoc = $('<html></html>');
        var head = $('<head>' + cwf_jrpt.rptInfo.PageStyle + '</head>');
        htmldoc.append(head);
        var data = cwf_jrpt.rptInfo.Data;
        // This should be a simple parent div to ensure that it does not take printer page space
        var rptParent = $('<div id="rptParent" name="rptParent"></div>');
        for (i = 0; i < cwf_jrpt.rptInfo.PageCount; i++) {
            var rptPage = $('<div id="rptPage' + i + '" class="print-format"></div>');
            var rptContainer = $('<div id="t' + i + '"></div>');
            var prop = 'Page' + i;
            var table = $('#jrPage-' + i);
            rptContainer.append(table.clone());
            rptPage.append(rptContainer);
            rptParent.append(rptPage);
            // set the last page margin to Zero.
            // This would suppress the blank page being printed
            if (i == cwf_jrpt.rptInfo.PageCount - 1) {
                rptPage.attr('style', "margin-bottom: 0px;");
            }
        }
        var body = $('<body></body>');
        // Following code is not supported in IE 
        // --Begin--
        //body.attr('onload', 'pageLoaded()'); 
        // --End--
        body.append(rptParent);
        htmldoc.append(body);
        // Following code is not supported in IE 
        // --Begin--
        //var script=pwin.document.createElement('script');
        //script.type = 'text/javascript';
        //script.text = 'function pageLoaded() { window.print(); window.close(); }';
        //htmldoc.append(script); 
        //--End--
        pwin.document.write(htmldoc.html());
        pwin.document.close();
    }
    cwf_jrpt.printForIE = printForIE;

    function exportClick(exptype, cwf_data_only) {
        var pdfInfo;
        var url = $('#rptOptions').attr('action');
        if (exptype == 'pdf') {
            url += '/renderpdf';
        } else if (exptype == 'ms-doc') {
            url += '/render-ms-doc';
        } else if (exptype == 'ms-xls') {
            url += '/render-ms-xls';
        } else if (exptype == 'open-doc') {
            url += '/render-open-doc';
        } else if (exptype == 'open-calc') {
            url += '/render-open-calc';
        }
        var data = $('#rptOptions').serialize();
        data += '&pcwf_data_only=' + cwf_data_only;
        data = data.replace(/=on/g, '=1');
        $('#rptOptions input[type=checkbox]:not(:checked)').each(
                function () {
                    data += '&' + this.name + '=0';
                });
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            beforeSend: function () {
                coreWebApp.toastmsg('info', 'Export Status', 'Submitting report generation request');
            },
            complete: function () {
                coreWebApp.toastmsg('info', 'Export Status', 'Report export process completed', false);
            },
            success: function (resultdata, status, jqXHR) {
                if (jqXHR.getResponseHeader("Output-Type") == "text/html") {
                    coreWebApp.toastmsg('error', 'Export Status', resultdata, false);
                } else if (jqXHR.getResponseHeader("Output-Type") == "application/json") {
                    pdfInfo = $.parseJSON(resultdata);
                    var pwin = window.open(pdfInfo.ReportRenderedPath);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Status', 'Failed to fetch data', false);
            }
        });
        return false;
    }
    cwf_jrpt.exportClick = exportClick;

    function emailClick() {
        var pdata = $('#rptOptions').serialize();
        pdata = pdata.replace(/=on/g, '=1');
        $('#rptOptions input[type=checkbox]:not(:checked):visible').each(
                function () {
                    pdata += '&' + this.name + '=0';
                });
        var emdata = new Object();
        $('#mailopts :input').each(function () {
            var attrid = $(this).attr('id');
            if (typeof attrid != 'undefined' && attrid.match('^mail_')) {
                var fldid = ($(this).attr('id')).replace('mail_', '');
                emdata[fldid] = $(this).val();
            }
        });
        $.ajax({
            url: '?r=cwf/fwShell/jreport/maildata',
            type: 'POST',
            data: pdata + '&' + jQuery.param(emdata),
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
    cwf_jrpt.emailClick = emailClick;

    function showemaildialog() {
        var pdialog = $("#divmaildata").dialog({
            autoOpen: false,
            modal: true,
            title: 'Email',
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
        $("#divmaildata").parents().children().find(".ui-dialog").css('z-index', '999');
        $("#divmaildata").parents().children().find(".ui-dialog").css('top', '120px');
        $("#divmaildata").parents().children().find(".ui-dialog").css('left', '32%');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
        $("#divmaildata").parents().children().find(".ui-dialog").css('z-index', '999');
        $("#divmaildata").parents().children().find(".ui-widget-header").css('border', 'none');
        $("#divmaildata").parents().children().find(".ui-widget-header").css('border-bottom', '1px solid teal');
        $("#divmaildata").parents().children().find(".ui-widget-header").css('border-radius', '0');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('line-height', '30px');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('font-size', '15px');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('color', 'teal');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('padding-left', '20px');
        $("#divmaildata").parents().children().find(".ui-dialog .ui-dialog-title").css('width', '150px');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").addClass('btn btn-default');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").css('background-color', 'lightgray');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").css('margin-right', '1em');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").css('margin-top', '3px');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").css('padding', '3px 12px');
        $("#divmaildata").parents().children().find(".ui-dialog-titlebar button").focus();
        $("#divmaildata").parents().children().find('.ui-dialog').find('#cboformbodyin').css('border-bottom', '0');
        $("#divmaildata").parents().children().find('.ui-dialog .ui-dialog-buttonpane').css('border', '0');
    }
    cwf_jrpt.showemaildialog = showemaildialog;

    var isExpanded = true;
    function adjustHeight() {
        r1 = parseInt($('#rptrow1').height());
        r2 = 0;
        cntht = parseInt($('#content-root').height());
        $('#rptParent').height(cntht - r1 - r2 - 25);
        $('#rptrow2').hide('slow');
        $('#rptCaption').hide('slow');
        isExpanded = false;
        if ($('#rptParent').not(':visible')) {
            $('#rptParent').show('slow');
        }
    }
    cwf_jrpt.adjustHeight = adjustHeight;

    function expandOptions() {
        if (isExpanded) {
            $('#rptrow2').hide('slow');
            isExpanded = false;
        } else {
            $('#rptrow2').show('slow');
//            if(coreWebApp.detectIE()){
//                $('#rptParent').hide('slow');}
            isExpanded = true;
        }
    }
    cwf_jrpt.expandOptions = expandOptions;


    function bindModel() {
        var rawdata = $('#modelData').val();
        if (rawdata != '' && rawdata != null) {
            var data = $.parseJSON(rawdata);
            cwf_jrpt.Model = ko.mapping.fromJS(data);
            ko.applyBindings(cwf_jrpt.Model, $('#rptOptions')[0]);
            refreshClick();
        } else {
            cwf_jrpt.applySmartControls();
        }
    }
    cwf_jrpt.bindModel = bindModel;

    function applySmartControls() {
        $('#rptOptions').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('multiselect')) {
                coreWebApp.applySmartMultiCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });
    }
    cwf_jrpt.applySmartControls = applySmartControls;

    function enableSubscr() {
        $('#cboformwrapper').toggle();
        $('#schOptions').toggle();
        $('#subscrOptions').toggle();
        $('#rptRoot').hide();
        $('#rptrow2').show();
        $('#rptname').val($('#rptCaption').text());
    }
    cwf_jrpt.enableSubscr = enableSubscr;

    function showSchday() {
        if ($('#sch_monthly_on').val() == 'specific') {
            $('#day').show();
            $('#labelday').show();
        } else {
            $('#day').hide();
            $('#labelday').hide();
        }
    }
    cwf_jrpt.showSchday = showSchday;

    function subscrClick() {
        var data = $('#rptOptions').find('select, input:not([name="sch_wday"])').serialize();
        var wdays = [];
        var rptname = '';
        $('#rptOptions').find('input[name="sch_wday"]:checked').each(function () {
            wdays.push(this.value);
        });
        $('#rptOptions').find('#rptname').each(function () {
            rptname = this.value;
        });
        data = data.replace(/=on/g, '=1');
        data = data.replace(/=True/g, '=1');
        data += '&sch_wday=' + wdays.join(',');
        data += '&rpt_name=' + rptname;
        $.ajax({
            url: '?r=cwf/sys/subscription/add',
            type: 'POST',
            data: data,
            beforeSend: function () {
                coreWebApp.toastmsg('info', 'Subscription Status', 'Processing subscription request');
            },
            complete: function () {
                coreWebApp.toastmsg('info', 'Subscription Status', 'Subscription request process completed', false);
            },
            success: function (resultdata) {
                coreWebApp.toastmsg('info', 'Subscription Status', 'Report subscription successfull.');
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Status', 'Failed to subscribe report.', false);
            }
        });
        return false;
    }
    cwf_jrpt.subscrClick = subscrClick;
}(window.cwf_jrpt));


