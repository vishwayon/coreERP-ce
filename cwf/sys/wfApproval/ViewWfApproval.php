<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Pending approval requests</h3>                  
            </div>
            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.wf_userdocs.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>                
            </div>
        </div>
        <div id="pwfdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Doc Type</th>
                        <th>Voucher id</th>
                        <th>Doc Date</th>
                        <th>Customer</th>
                        <th>From User</th>
                        <th>Request Date</th>
                        <th></th>  
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'pwfdata-template', foreach: dt_request, afterRender: coreWebApp.wf_userdocs.calTS() }">
                </tbody>
            </table>            
        </div>

        <script id="pwfdata-template" type="text/html">
            <tr> 
                <td style="text-align: center;" data-bind="text: apr_type">
                </td>
                <td data-bind="text: branch_name">
                </td>
                <td data-bind="text: bo_id">
                </td>
                <td data-bind="text: doc_id">
                </td>
                <td data-bind="dateValue: doc_date, attr:{'data-sort': doc_date_sort}">
                </td> 
                <td data-bind="text: customer">
                </td>                  
                <td data-bind="text: from_user">
                </td>
                <td data-bind="dateValue: added_on, attr:{'data-sort': req_date_sort}">
                </td> 
                <td>
                    <button type="button" id="cedit" title="View" 
                            data-bind ="click:coreWebApp.wf_userdocs.openSummary"
                            style="border:none;padding-left:5px;padding-right:5px;background-color:white;">
                        <i class="glyphicon glyphicon-info-sign"></i>
                    </button>
                </td>                
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        $('#pendingreq').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        //create and bind wf_userdocs namespace
        window.coreWebApp.wf_userdocs = {};
        (function (wf_userdocs) {

            function getData() {
                $('#brules').html('');
                var res = $('#pendingreq').serialize();
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fwf-approval%2Fget-data',
                    type: 'GET',
                    data: {'params': res, 'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        $('#brules').html('');
                        if (jsonResult.jsondata.brokenrules.length > 0) {
                            var brules = jsonResult.jsondata.brokenrules;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();
                            $('#pwfdata').show();
                            $('#vch_tran').hide();
                        } else {
                            wf_userdocs.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            $('#pwfdata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#pwfdata')[0]);
                            ko.applyBindings(wf_userdocs.ModelBo, $('#pwfdata')[0]);
                            coreWebApp.initCollection('vch_tran');
                            var dtht = $('#contentholder').height() - $('#collheader').height() - 77;
                            $('.dataTables_scrollBody').css('min-height', dtht);
                            $('.dataTables_scrollBody').height(dtht);
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            wf_userdocs.GetData = getData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            wf_userdocs.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: doc_date"]').each(function () {
                    var temp = wf_userdocs.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            wf_userdocs.calTS = calTS;

            function openSummary(row) {
                wf_userdocs.docdata = row;
                var qpRoute = row.route();
                var qpForm = row.formname();
                var qpKey = row.formparams();
                lnk = '?r=/' + qpRoute + '/form/summary&formName=' + qpForm + '&formParams=' + qpKey;
                coreWebApp.rendercontents(lnk, 'details', 'contentholder', 'coreWebApp.wf_userdocs.afterFormLoad');
            }
            wf_userdocs.openSummary = openSummary;

            function afterFormLoad() {
                var afterloadevent = $('#hkAfterLoadEvent').val();
                if (typeof afterloadevent != 'undefined' && afterloadevent != '') {
                    var func = new Function('{' + afterloadevent + '();}');
                    func();
                }
            }
            wf_userdocs.afterFormLoad = afterFormLoad;

            function setData(ifapproved) {
                $('#brules').html('');
                var resarr = Object();
                resarr.wf_approved = ifapproved;
                resarr.wf_comment = $('#docwf_userto_comments').val();
                resarr.doc_id = $('#doc_id_text').text();
                resarr.wf_ar_id = $('#wf_ar_id').val();
                var res = JSON.stringify(resarr);
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fwf-approval%2Fset-data',
                    type: 'GET',
                    data: {'params': res, 'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        $('#brules').html('');
                        if (jsonResult.brokenrules.length > 0) {                            
                            coreWebApp.toastmsg('error', 'Status', 'Document status not changed. Check the broken rules. ', false);
                            var brules = jsonResult.brokenrules;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();                      
                        }else{
                            debugger;
                            msag = 'Document ';
                            if(ifapproved){
                                msag += ' approved sucessfully';
                            }else{
                                msag += ' rejected';
                            }
                            coreWebApp.toastmsg('success', 'Status', msag, false);
                            coreWebApp.closeDetail();
                            wf_userdocs.GetData();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            wf_userdocs.setData = setData;

        }(window.coreWebApp.wf_userdocs));
        coreWebApp.wf_userdocs.GetData();
    </script>
