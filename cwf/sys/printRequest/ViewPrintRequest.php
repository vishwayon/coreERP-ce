<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=cwf%2Fsys%2Fprint-request%2Fgetdata';
$purl = '?r=cwf%2Fsys%2Fprint-request%2Fsetdata';
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Print Requests</h3>                  
            </div>
            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.printRequest.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdateprintreq" style="display: none;"
                        onclick="coreWebApp.printRequest.SetJsonData('<?= $purl ?>', 'POST', 'preqdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="preqdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Voucher id</th>
                        <th>User Name</th>
                        <th>Request Date</th>
                        <th>Approve</th>
                        <th>Reject</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'preqdata-template', foreach: dt_printRequest, afterRender: coreWebApp.printRequest.CalTS() }">
                </tbody>
            </table>            
        </div>

        <script id="preqdata-template" type="text/html">
            <tr> 
                <td data-bind="text: doc_id">
                </td>
                <td data-bind="text: user_name">
                </td>
                <td data-bind="dateValue: requested_on, attr:{'data-sort': doc_date_sort}">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: approve, click: coreWebApp.printRequest.checkApprove($data)">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: reject, click: coreWebApp.printRequest.checkReject($data)">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        //create and bind printRequest namespace
        window.coreWebApp.printRequest = {};
        (function (printRequest) {

            function getData() {
                // get actual data
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fprint-request%2Fgetdata',
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
                        printRequest.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#preqdata').show();
                        ko.cleanNode($('#preqdata')[0]);
                        ko.applyBindings(printRequest.ModelBo, $('#preqdata')[0]);
                        printRequest.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            printRequest.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(printRequest.ModelBo);
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
                        $('#brules').html('');

                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#vch_tran').show();
                        ko.cleanNode($('#preqdata')[0]);
                        printRequest.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        ko.applyBindings(printRequest.ModelBo, $('#preqdata')[0]);
                        coreWebApp.applyDatepicker('');
                        printRequest.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            printRequest.SetJsonData = setJsonData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            printRequest.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: doc_date"]').each(function () {
                    var temp = printRequest.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            printRequest.CalTS = calTS;

            function toggleUpdate() {
                $('#cmdupdateprintreq').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (printRequest.ModelBo.dt_printRequest().length > 0) {
                        $('#cmdupdateprintreq').show();
                    }
                }
            }
            printRequest.ToggleUpdate = toggleUpdate;

            function checkApprove(item) {
                if (item.reject()) {
                    item.reject(false);
                }
            }
            printRequest.checkApprove = checkApprove;
            
            function checkReject(item) {
                if(item.approve()){
                    item.approve(false);
                }
            }
            printRequest.checkReject = checkReject;

        }(window.coreWebApp.printRequest));
    </script>