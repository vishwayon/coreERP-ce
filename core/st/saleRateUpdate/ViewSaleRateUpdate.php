<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$viewerurl = '?r=core%2Fst%2Fsale-rate-update%2Fgetdata';
$purl = '?r=core%2Fst%2Fsale-rate-update%2Fsetdata';
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Sale Rate Update</h3>
        </div>
        <div id="collfilter" class="row">

            <form class="form-horizontal required" id="salerateupdate" name ="salerateupdate" 
                  target="salerateupdatedata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="material_type_id">Stock Type</label>
                    <?=
                    Html::input('SmartCombo', 'material_type_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'material_type_id', 'name' => 'material_type_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'material_type_id',
                        'data-displaymember' => 'material_type',
                        'data-namedlookup' => '@app/core/st/lookups/MaterialTypeWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select stock type'])
                    ?>
                </div>
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="material_id">Stock Item</label>
                    <?=
                    Html::input('SmartCombo', 'material_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'material_id', 'name' => 'material_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'material_id',
                        'data-displaymember' => 'material_name',
                        'data-namedlookup' => '@app/core/st/lookups/Material.xml',
                        'filterevent' => 'core_st.saleRateUpdate.material_filter',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select stock item'])
                    ?>
                </div>
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="rate_type">Price Type</label>
                    <select id="rate_type" name="rate_type" class="form-control" style="width: 80%;">
                        <option value="FP">Fixed Price</option>
                        <option value="WAC">Weighted Avg. Cost + Markup</option>
                        <option value="LP">Latest Purchase Cost + Markup</option>
                    </select>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="core_st.saleRateUpdate.getData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdateinv" style="display: none;"
                        onclick="core_st.saleRateUpdate.setJsonData('<?= $purl ?>', 'POST', 'salerateupdatedata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="salerateupdatedata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">        
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Stock Code</th>
                        <th>Stock Item</th>
                        <th>Stock Type</th>
                        <th><span id="price1">Markup/unit</span></th>
                        <th><span id="price2">Markup %</span></th>
                        <th>Modify</th>
                        <th><span id="mprice1">New Markup/unit</span></th>
                        <th><span id="mprice2">New Markup %</span></th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'salerateupdatedata-template', foreach: dt }">
                </tbody>
            </table>
        </div>
        <script id="salerateupdatedata-template" type="text/html">
            <tr> 
                <td data-bind="text: material_code"/>
                <td data-bind="text: material_name"/>
                <td data-bind="text: material_type"/>
                <td data-bind="numericValue: sr_pu"/>
                <td data-bind="numericValue: disc_pcnt"/>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: select, click: core_st.saleRateUpdate.checkChanged($data)">
                </td>               
                <td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="numericValue: msr_pu, enable: select">
                </td><td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="numericValue: mdisc_pcnt, enable: select">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        $('#salerateupdate').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        typeof window.core_st == 'undefined' ? window.core_st = {} : null; 
        window.core_st.saleRateUpdate = {};
        (function (saleRateUpdate) {
            function getData() {
                $('#brules').html('');
                var res = $('#salerateupdate').serialize();
                res = res.replace(/=on/g, '=1');
                res = res.replace(/=True/g, '=1');

                form_method = $('#salerateupdate').attr('method');
                form_action = $('#salerateupdate').attr('action');
                form_target = $('#salerateupdate').attr('target');
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
                        $('#brules').html('');
                        if (jsonResult.jsondata.brokenrules.length > 0) {
                            var brules = jsonResult.jsondata.brokenrules;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();
                            $('#salerateupdatedata').show();
                            $('#vch_tran').hide();
                        } else {
                            saleRateUpdate.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            saleRateUpdate.ptype_changed();
                            $('#salerateupdatedata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#salerateupdatedata')[0]);
                            ko.applyBindings(saleRateUpdate.ModelBo, $('#salerateupdatedata')[0]);
                            saleRateUpdate.toggleUpdate();
                            coreWebApp.initCollection('vch_tran');
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            saleRateUpdate.getData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(saleRateUpdate.ModelBo);
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
                        if (jsonResult.brule.length > 0) {
                            coreWebApp.toastmsg('warning', 'Save Failed', '', false);
                            var brules = jsonResult.brule;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();
                            $('#vch_tran').show();
                        } else if (jsonResult.status == 'OK') {
                            coreWebApp.toastmsg('msg', 'Update Status', 'Successfully updated sale rate(s)', true);
                            saleRateUpdate.getData();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            saleRateUpdate.setJsonData = setJsonData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            saleRateUpdate.getTimestamp = getTimestamp;

            function toggleUpdate() {
                $('#cmdupdateinv').hide();
                //                if ($("#view_type_id option:is_dispatched").text() !== 'Released') {
                if (saleRateUpdate.ModelBo.dt().length > 0) {
                    $('#cmdupdateinv').show();
                }
                //                }
            }
            saleRateUpdate.toggleUpdate = toggleUpdate;

            function checkChanged(item) {
            }
            saleRateUpdate.checkChanged = checkChanged;

            function material_filter(fltr) {
                if (typeof ($('#material_type_id').val()) != 'undefined' && $('#material_type_id').val() != ''
                        && parseInt($('#material_type_id').val()) !== -1 && parseInt($('#material_type_id').val()) !== 0) {
                    fltr = ' material_type_id = ' + $('#material_type_id').val();
                }
                return fltr;
            }
            saleRateUpdate.material_filter = material_filter;

            function ptype_changed() {
                if (typeof ($('#rate_type').val()) != 'undefined' && $('#rate_type').val() != '') {
                    if ($('#rate_type').val() == 'FP') {
                        $('#price1').html('Price/unit');
                        $('#price2').html('Std Disc %');
                        $('#mprice1').html('New Price/unit');
                        $('#mprice2').html('New Disc %');
                    } else {
                        $('#price1').html('Markup/unit');
                        $('#price2').html('Markup %');
                        $('#mprice1').html('New Markup/unit');
                        $('#mprice2').html('New Markup %');
                    }
                }
            }
            saleRateUpdate.ptype_changed = ptype_changed;

        }(window.core_st.saleRateUpdate));
        
    </script>
