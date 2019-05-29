<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Fap%2Fsupp-gstin-update%2Fgetdata';
$purl = '?r=core%2Fap%2Fsupp-gstin-update%2Fsetdata';

$view_type_option = array();
$view_type_option[0] = 'Without GST';
$view_type_option[1] = 'With GST';
$view_type_option[2] = 'All';
?>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Update GST Info for Supplier</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="supp" name ="supp" 
                  target="suppdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">

                <div class=" col-md-2 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="view_type_id">GST Status</label>
                    <?= Html::dropDownList('view_type_id', 'All', $view_type_option, ['class' => 'form-control', 'id' => 'view_type_id'])
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.supp.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatesupp" style="display: none;"
                        onclick="coreWebApp.supp.SetJsonData('<?= $purl ?>', 'POST', 'suppdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="suppdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran" style="display: block;" cellspacing="0">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th style="width: 200px" >Address</th>
                        <th>GST State</th>
                        <th>GSTIN</th>
                        <th>select</th>
                        <th>New GSTIN</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'suppdata-template', foreach: dt}">
                </tbody>
            </table>
        </div>
        <script id="suppdata-template" type="text/html">
            <tr> 
                <td data-bind="text: supplier">
                </td>
                <td data-bind="html: address" style="max-width: 250px;">
                </td>
                <td data-bind="text: gst_state_with_code">
                </td>
                <td data-bind="text: gstin">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: selected, click: coreWebApp.supp.CheckChanged($data)">
                </td> 
                <td class="">
                    <input id="new_gstin" type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: new_gstin, enable: selected">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">
        //create and bind supp namespace
        window.coreWebApp.supp = {};
        (function (supp) {
            function getData() {
                $('#brules').html('');
                var res = $('#supp').serialize();
                res = res.replace(/=on/g, '=1');
                res = res.replace(/=True/g, '=1');
                $('#supp input[type=checkbox]:not(:checked)').each(
                        function () {
                            res += '&' + this.name + '=0';
                        });

                form_method = $('#supp').attr('method');
                form_action = $('#supp').attr('action');
                form_target = $('#supp').attr('target');
                $.ajax({
                    url: form_action,
                    type: form_method,
                    data: {'params': res, 'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        supp.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#suppdata').show();
                        ko.cleanNode($('#suppdata')[0]);
                        ko.applyBindings(supp.ModelBo, $('#suppdata')[0]);
                        supp.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            supp.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(supp.ModelBo);
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
                        if (jsonResult.jsondata.brokenrules.length > 0) {
                            coreWebApp.toastmsg('warning', 'Save Failed', '', false);
                            var brules = jsonResult.jsondata.brokenrules;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();
                            $('#vch_tran').show();
                        } else {
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            $('#vch_tran').show();
                            ko.cleanNode($('#suppdata')[0]);
                            supp.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            ko.applyBindings(supp.ModelBo, $('#suppdata')[0]);
                            coreWebApp.initCollection('vch_tran');
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            supp.SetJsonData = setJsonData;


            function calTS() {
            }
            supp.CalTS = calTS;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            supp.GetTimestamp = getTimestamp;

            function toggleUpdate() {
                $('#cmdupdatesupp').hide();
//                if ($("#view_type_id option:selected").text() !== 'Released') {
                if (supp.ModelBo.dt().length > 0) {
                    $('#cmdupdatesupp').show();
                }
//                }
            }
            supp.ToggleUpdate = toggleUpdate;

            // Sets the As On date as the reco date by default
            function checkChanged(item) {
                if (!item.selected()) {
                    item.new_gst_state_id(-1); // setting the default gst state
                    item.new_gstin('');
                }
            }
            supp.CheckChanged = checkChanged;

        }(window.coreWebApp.supp));
    </script>
