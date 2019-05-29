<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$viewerurl = '?r=core%2Far%2Fcust-info-update%2Fgetdata';
$purl = '?r=core%2Far%2Fcust-info-update%2Fsetdata';
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Update Customer Info</h3>
        </div>
        <div id="collfilter" class="row">

            <form class="form-horizontal required" id="custinfoupdate" name ="custinfoupdate" 
                  target="custinfoupdatedata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="customer_id">Customer</label>
                    <?=
                    Html::input('SmartCombo', 'customer_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'customer_id', 'name' => 'customer_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'customer_id',
                        'data-displaymember' => 'customer',
                        'data-namedlookup' => '../core/ar/lookups/CustomerWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select customer'])
                    ?>
                </div>
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="credit_limit_type">Credit Limit Type</label>
                    <select id="rate_type" name="credit_limit_type" class="form-control" style="width: 80%;">
                        <option value="-1">All</option>
                        <option value="0">No Credit</option>
                        <option value="1">Unlimited Credit</option>
                        <option value="2">Apply Credit Limit</option>
                    </select>
                </div>
                <div class=" col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="pay_term_id">Pay Term</label>
                    <?=
                    Html::input('SmartCombo', 'pay_term_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'material_id', 'name' => 'pay_term_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'pay_term_id',
                        'data-displaymember' => 'pay_term',
                        'data-namedlookup' => '../core/ar/lookups/CustPayTermWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Pay Term'])
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="core_ar.custInfoUpdate.getData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatecust" style="display: none;"
                        onclick="core_ar.custInfoUpdate.setJsonData('<?= $purl ?>', 'POST', 'custinfoupdatedata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="custinfoupdatedata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">        
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th><span id="cl_type">Credit Limit Type</span></th>
                        <th><span id="cl_val">Credit Limit</span></th>
                        <th><span id="pt">Pay Term</span></th>
                        <th>Modify</th>
                        <th><span id="mcl_type">Credit Limit Type</span></th>
                        <th><span id="mcl_val">Credit Limit</span></th>
                        <th><span id="mpt">Pay Term</span></th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'custinfoupdatedata-template', foreach: dt }">
                </tbody>
            </table>
        </div>
        <script id="custinfoupdatedata-template" type="text/html">
            <tr> 
                <td data-bind="text: customer"/>
                <td data-bind="text: credit_limit_type_desc"/>
                <td data-bind="numericValue: credit_limit"/>
                <td data-bind="text: pay_term"/>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: select, click: core_ar.custInfoUpdate.checkChanged($data, $element)">
                </td>               
                <td class="">
                    <select id="mcl_type" class="form-control" name="mcl_type" data-bind="value: mcl_type, enable: select" disabled="true">
                        <option value="-1">Select</option>
                        <option value="0">No Credit</option>
                        <option value="1">Unlimited Credit</option>
                        <option value="2">Apply Credit Limit</option>
                    </select>
                </td>
                <td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="numericValue: mcl, enable: select">
                </td>
                <td style="" class="td-mpt_id" colspan="1">
                    <select id="mpt_id" class="form-control" name="mpt_id" data-bind="value: mpt_id, enable: select" disabled="true">
                        <option value="-1">Select</option>
                    </select>
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        $('#custinfoupdate').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        typeof window.core_ar == 'undefined' ? window.core_ar = {} : null;
        window.core_ar.custInfoUpdate = {};
        (function (custInfoUpdate) {
            function getData() {
                $('#brules').html('');
                var res = $('#custinfoupdate').serialize();
                res = res.replace(/=on/g, '=1');
                res = res.replace(/=True/g, '=1');

                form_method = $('#custinfoupdate').attr('method');
                form_action = $('#custinfoupdate').attr('action');
                form_target = $('#custinfoupdate').attr('target');
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
                            $('#custinfoupdatedata').show();
                            $('#vch_tran').hide();
                        } else {
                            custInfoUpdate.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            custInfoUpdate.ptype_changed();
                            $('#custinfoupdatedata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#custinfoupdatedata')[0]);
                            ko.applyBindings(custInfoUpdate.ModelBo, $('#custinfoupdatedata')[0]);
                            custInfoUpdate.toggleUpdate();
                            coreWebApp.initCollection('vch_tran');
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            custInfoUpdate.getData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(custInfoUpdate.ModelBo);
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
                            custInfoUpdate.getData();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            custInfoUpdate.setJsonData = setJsonData;

            function payTermData(ctrl) {
                $.ajax({
                    url: '?r=core/ar/form/fetch-pay-term',
                    type: 'GET',
                    data: {},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var raw = $.parseJSON(resultdata);
                        var udata = ko.mapping.fromJS(raw);
                        $.each(udata.dt_pay_term(), function (index, val) {
                            $(ctrl).append($('<option value="' + val.pay_term_id() + '">' + val.pay_term() + '</option>'));
                        });
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    }
                });
            }
            custInfoUpdate.payTermData = payTermData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            custInfoUpdate.getTimestamp = getTimestamp;

            function toggleUpdate() {
                $('#cmdupdatecust').hide();
                //                if ($("#view_type_id option:is_dispatched").text() !== 'Released') {
                if (custInfoUpdate.ModelBo.dt().length > 0) {
                    $('#cmdupdatecust').show();
                }
                //                }
            }
            custInfoUpdate.toggleUpdate = toggleUpdate;

            function checkChanged(item, ctrl) {
                if (item.select()) {
                    var pay_term_combo = $(ctrl).parent().parent().children().find('#mpt_id');
                    custInfoUpdate.payTermData(pay_term_combo);
                }
            }
            custInfoUpdate.checkChanged = checkChanged;

            function ptype_changed() {
//                if (typeof ($('#rate_type').val()) != 'undefined' && $('#rate_type').val() != '') {
//                    if ($('#rate_type').val() == 'FP') {
//                        $('#price1').html('Price/unit');
//                        $('#price2').html('Std Disc %');
//                        $('#mprice1').html('New Price/unit');
//                        $('#mprice2').html('New Disc %');
//                    } else {
//                        $('#price1').html('Markup/unit');
//                        $('#price2').html('Markup %');
//                        $('#mprice1').html('New Markup/unit');
//                        $('#mprice2').html('New Markup %');
//                    }
//                }
            }
            custInfoUpdate.ptype_changed = ptype_changed;

        }(window.core_ar.custInfoUpdate));

    </script>
