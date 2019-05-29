<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=cwf%2Fsys%2Fwidget%2Fgetwidget';
$purl = '?r=cwf%2Fsys%2Fwidget%2Fsetwidget';
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Request Widget</h3>
            </div>
            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.requestWidget.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatereqwidget" style="display: none;"
                        onclick="coreWebApp.requestWidget.SetJsonData('<?= $purl ?>', 'POST', 'preqdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Request         
                </button>
            </div>
        </div>
        <div id="preqdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Widget</th>
                        <th>Subscribe</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'preqdata-template', foreach: dt_requestWidget }">
                </tbody>
            </table>            
        </div>

        <script id="preqdata-template" type="text/html">
            <tr> 
                <td data-bind="text: widget_name">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: subscribe">
                </td>
                <td style="text-align: center" data-bind="html: coreWebApp.requestWidget.req_detail($data)">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        //create and bind requestWidget namespace
        window.coreWebApp.requestWidget = {};
        (function (requestWidget) {

            function getData() {
                // get actual data
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fwidget%2Fgetwidget',
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
                        requestWidget.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#preqdata').show();
                        ko.cleanNode($('#preqdata')[0]);
                        ko.applyBindings(requestWidget.ModelBo, $('#preqdata')[0]);
                        requestWidget.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            requestWidget.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(requestWidget.ModelBo);
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
                        requestWidget.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        ko.applyBindings(requestWidget.ModelBo, $('#preqdata')[0]);
                        coreWebApp.applyDatepicker('');
                        requestWidget.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            requestWidget.SetJsonData = setJsonData;

            function toggleUpdate() {
                $('#cmdupdatereqwidget').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (requestWidget.ModelBo.dt_requestWidget().length > 0) {
                        $('#cmdupdatereqwidget').show();
                    }
                }
            }
            requestWidget.ToggleUpdate = toggleUpdate;
            
            function req_detail(item){
                if(item.pending()){
                    return '<span class="glyphicon glyphicon-ok"></span>';
                }
            }
            requestWidget.req_detail = req_detail;

        }(window.coreWebApp.requestWidget));
    </script>