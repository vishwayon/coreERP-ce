<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=cwf%2Fsys%2Ffy-access%2Fgetdata';
$purl = '?r=cwf%2Fsys%2Ffy-access%2Fsetdata';
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Closed Financial Year Access</h3>                  
            </div>
            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.clfyAccess.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdateclfyusr" style="display: none;"
                        onclick="coreWebApp.clfyAccess.SetJsonData('<?= $purl ?>', 'POST', 'usrdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="usrdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="usr_lst" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Allow</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'usrdata-template', foreach: dt_usr }">
                </tbody>
            </table>            
        </div>

        <script id="usrdata-template" type="text/html">
            <tr> 
                <td data-bind="text: full_user_name">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: clfy_access">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        //create and bind clfyAccess namespace
        window.coreWebApp.clfyAccess = {};
        (function (clfyAccess) {

            function getData() {
                // get actual data
                $.ajax({
                    url: '?r=cwf%2Fsys%2Ffy-access%2Fgetdata',
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
                        clfyAccess.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#usr_lst')) {
                            var t = $('#usr_lst').DataTable();
                            t.destroy();
                        }
                        $('#usrdata').show();
                        ko.cleanNode($('#usrdata')[0]);
                        ko.applyBindings(clfyAccess.ModelBo, $('#usrdata')[0]);
                        clfyAccess.ToggleUpdate();
                        coreWebApp.initCollection('usr_lst');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            clfyAccess.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(clfyAccess.ModelBo);
                $('#usr_lst').hide();
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

                        if ($.fn.dataTable.isDataTable('#usr_lst')) {
                            var t = $('#usr_lst').DataTable();
                            t.destroy();
                        }
                        $('#usr_lst').show();
                        ko.cleanNode($('#usrdata')[0]);
                        clfyAccess.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        ko.applyBindings(clfyAccess.ModelBo, $('#usrdata')[0]);
                        clfyAccess.ToggleUpdate();
                        coreWebApp.initCollection('usr_lst');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            clfyAccess.SetJsonData = setJsonData;

            function toggleUpdate() {
                $('#cmdupdateclfyusr').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (clfyAccess.ModelBo.dt_usr().length > 0) {
                        $('#cmdupdateclfyusr').show();
                    }
                }
            }
            clfyAccess.ToggleUpdate = toggleUpdate;

        }(window.coreWebApp.clfyAccess));
    </script>