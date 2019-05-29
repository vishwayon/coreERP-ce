<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Fap%2Fbill-no-edit%2Fgetdata';
$purl = '?r=core%2Fap%2Fbill-no-edit%2Fsetdata';
$view_type_option = array();
$view_type_option[0] = 'With BNR';
$view_type_option[1] = 'Without BNR';
$from_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$to_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
if (strtotime(date("Y-m-d", time())) > strtotime(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
    $as_on_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
    $to_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
    $from_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
}

$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());

$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
?>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Bill No Update</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="bill" name ="bill" 
                  target="billdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-2 form-group" style="margin-top: 0px; width: 140px;">
                    <label class="control-label" for="view_type_id">Status</label>
                    <?= Html::dropDownList('view_type_id', 'With BNR', $view_type_option, ['class' => 'form-control', 'id' => 'view_type_id'])
                    ?>
                </div>

                <div class=" col-md-3 form-group required" style="margin-top: 0px; width: 250px;">
                    <label class="control-label" for="account_id">Supplier</label>
                    <?=
                    Html::input('SmartCombo', 'account_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'account_id', 'name' => 'account_id',
                        'data-validation' => 'required',
                        'data-valuemember' => 'supplier_id',
                        'data-displaymember' => 'supplier',
                        'data-filter' => '',
                        'data-namedlookup' => '../core/ap/lookups/SupplierWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Supplier'])
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px; width: 130px;">
                    <label class="control-label" for="from_date">From</label>
                    <?=
                    Html::input('Date', 'from_date', $from_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'From Date is required.',
                        'id' => 'from_date', 'name' => 'from_date',
                        'start_date' => $year_begin,
                        'end_date' => $year_end]
                    )
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px; width: 130px;">
                    <label class="control-label" for="to_date">To</label>
                    <?=
                    Html::input('Date', 'to_date', $as_on_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'To Date is required.',
                        'id' => 'to_date', 'name' => 'to_date',
                        'start_date' => $year_begin,
                        'end_date' => $year_end]
                    )
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px; width: 170px;">
                    <label class="control-label" for="to_user_id">Voucher ID</label>
                    <?=
                    Html::input('text', 'bill_id', NULL, ['class' => 'form-control',
                        'id' => 'bill_id', 'name' => 'bill_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-validations' => 'string',
                        'style' => '',
                        'data-validation-error-msg' => 'Please enter voucher id'])
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.bill.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatebill" style="display: none;"
                        onclick="coreWebApp.bill.SetJsonData('<?= $purl ?>', 'POST', 'billdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="billdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Doc Date</th>
                        <th>Voucher id</th>
                        <th>Supplier</th>
                        <th>Bill Amount</th>
                        <th>select</th>
                        <th>Bill No</th>
                        <th>Bill Date</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'billdata-template', foreach: dt, afterRender: coreWebApp.bill.CalTS() }">
                </tbody>
            </table>
        </div>
        <script id="billdata-template" type="text/html">
            <tr> 
                <td data-bind="dateValue: doc_date">
                </td>
                <td data-bind="text: voucher_id">
                </td>
                <td data-bind="text: supplier">
                </td>
                <td data-bind="numericValue: bill_amt" style="text-align: right">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: selected, click: coreWebApp.bill.CheckChanged($data)">
                </td>
                <td class="">
                    <input id="bill_no" type="Text" class="col-md-10" style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: bill_no, enable: selected">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10 datetime" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="dateValue: bill_date, enable: selected, attr: {'id': 'cd'+$index()} ">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        $('#bill').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });


        //create and bind bill namespace
        window.coreWebApp.bill = {};
        (function (bill) {
            function getData() {
                $('#brules').html('');
                var res = $('#bill').serialize();
                res = res.replace(/=on/g, '=1');
                res = res.replace(/=True/g, '=1');
                $('#bill input[type=checkbox]:not(:checked)').each(
                        function () {
                            res += '&' + this.name + '=0';
                            res += '&as_on=' + coreWebApp.formatDate(new Date());
                        });

                form_method = $('#bill').attr('method');
                form_action = $('#bill').attr('action');
                form_target = $('#bill').attr('target');
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
                            $('#billdata').show();
                            $('#vch_tran').hide();
                        } else
                        {
                            bill.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            $('#billdata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#billdata')[0]);
                            ko.applyBindings(bill.ModelBo, $('#billdata')[0]);
                            bill.ToggleUpdate();
                            coreWebApp.initCollection('vch_tran');
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            bill.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(bill.ModelBo);
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
                            ko.cleanNode($('#billdata')[0]);
                            bill.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            ko.applyBindings(bill.ModelBo, $('#billdata')[0]);
                            coreWebApp.applyDatepicker('');
                            bill.ToggleUpdate();
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
            bill.SetJsonData = setJsonData;


            function calTS() {
                $('[data-bind="dateValue: ro_date"]').each(function () {
                    var temp = bill.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            bill.CalTS = calTS;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            bill.GetTimestamp = getTimestamp;

            function toggleUpdate() {
                $('#cmdupdatebill').hide();
//                if ($("#view_type_id option:selected").text() !== 'Released') {
                if (bill.ModelBo.dt().length > 0) {
                    $('#cmdupdatebill').show();
                }
//                }
            }
            bill.ToggleUpdate = toggleUpdate;

            // Sets the As On date as the reco date by default
            function checkChanged(item) {
                if (!item.selected()) {
                    item.bill_no('BNR'); // setting the default bill no
                }
            }
            bill.CheckChanged = checkChanged;

        }(window.coreWebApp.bill));
    </script>
