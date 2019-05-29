<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=cwf%2Fsys%2Fwidget%2Fgetrequest';
$purl = '?r=cwf%2Fsys%2Fwidget%2Fsetrequest';
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Widget Requests</h3>                  
            </div>
            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.widgetRequest.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatewidgetreq" style="display: none;"
                        onclick="coreWebApp.widgetRequest.SetJsonData('<?= $purl ?>', 'POST', 'preqdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="preqdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Widget</th>
                        <th>User</th>
                        <th>Request Date</th>
                        <th>Subscribe</th>
                        <th>Approve</th>
                        <th>Reject</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'preqdata-template', foreach: dt_widgetRequest, afterRender: coreWebApp.widgetRequest.CalTS() }">
                </tbody>
            </table>            
        </div>

        <script id="preqdata-template" type="text/html">
            <tr> 
                <td data-bind="text: widget_name">
                </td>
                <td data-bind="text: user_name">
                </td>
                <td data-bind="dateValue: request_date, attr:{'data-sort': doc_date_sort}">
                </td>
                <td data-bind="html: coreWebApp.widgetRequest.req_detail($data)"  style="text-align: center">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: approve, click: coreWebApp.widgetRequest.checkApprove($data)">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: reject, click: coreWebApp.widgetRequest.checkReject($data)">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        //create and bind widgetRequest namespace
        window.coreWebApp.widgetRequest = {};
        (function (widgetRequest) {

            function getData() {
                // get actual data
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fwidget%2Fgetrequest',
                    type: 'GET',
                    data: {'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        widgetRequest.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#preqdata').show();
                        ko.cleanNode($('#preqdata')[0]);
                        ko.applyBindings(widgetRequest.ModelBo, $('#preqdata')[0]);
                        widgetRequest.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            widgetRequest.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(widgetRequest.ModelBo);
                $('#vch_tran').hide();
                $.ajax({
                    url: form_action,
                    type: form_method,
                    data: data,
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#vch_tran').show();
                        ko.cleanNode($('#preqdata')[0]);
                        widgetRequest.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        ko.applyBindings(widgetRequest.ModelBo, $('#preqdata')[0]);
                        coreWebApp.applyDatepicker('');
                        widgetRequest.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            widgetRequest.SetJsonData = setJsonData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            widgetRequest.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: request_date"]').each(function () {
                    var temp = widgetRequest.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            widgetRequest.CalTS = calTS;

            function toggleUpdate() {
                $('#cmdupdatewidgetreq').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (widgetRequest.ModelBo.dt_widgetRequest().length > 0) {
                        $('#cmdupdatewidgetreq').show();
                    }
                }
            }
            widgetRequest.ToggleUpdate = toggleUpdate;

            function checkApprove(item) {
                if (item.reject()) {
                    item.reject(false);
                }
            }
            widgetRequest.checkApprove = checkApprove;
            
            function checkReject(item) {
                if(item.approve()){
                    item.approve(false);
                }
            }
            widgetRequest.checkReject = checkReject;
            
            function req_detail(item){
                if(item.subscribe()){
                    return '<span class="glyphicon glyphicon-ok"></span>';
                }else{
                    return '<span class="glyphicon glyphicon-remove"></span>';
                }
            }
            widgetRequest.req_detail = req_detail;

        }(window.coreWebApp.widgetRequest));
    </script>