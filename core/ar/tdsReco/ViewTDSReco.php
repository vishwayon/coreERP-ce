<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Far%2Ftds-reco%2Fgetdata';
$purl = '?r=core%2Far%2Ftds-reco%2Fsetdata';
$view_type_option = array();
$view_type_option[0] = 'unReconciled';
$view_type_option[1] = 'Reconciled';
$view_type_option[2] = 'All';
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
if (strtotime(date("Y-m-d", time())) > strtotime(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
    $as_on_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
}


$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());

$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>TDS Reconciliation</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="tdsreco" name ="tdsreco" 
                  target="recodata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-2 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="view_type_id">Status</label>
                    <?= Html::dropDownList('view_type_id', 'unReconciled', $view_type_option, ['class' => 'form-control', 'id' => 'view_type_id'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="customer_id">Customer</label>
                    <?=
                    Html::input('SmartCombo', 'customer_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'customer_id', 'name' => 'customer_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'customer_id',
                        'data-displaymember' => 'customer',
                        'data-namedlookup' => '@app/core/ar/lookups/CustomerWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Customer'])
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="as_on">As On</label>
                    <?=
                    Html::input('Date', 'as_on', $as_on_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'As on Date is required.',
                        'id' => 'as_on', 'name' => 'as_on',
                        'start_date' => $year_begin,
                        'end_date' => $year_end]
                    )
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.tdsReco.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatetdsreco" style="display: none;"
                        onclick="coreWebApp.tdsReco.SetJsonData('<?= $purl ?>', 'POST', 'recodata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="recodata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Doc Date</th>
                        <th>Voucher id</th>
                        <th>Customer</th>
                        <th>PAN</th>
                        <th>TAN</th>
                        <th style="text-align: center">TDS Amt</th>
                        <th>Reconciled</th>
                        <th>Reco Date</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'recodata-template', foreach: dt, afterRender: coreWebApp.tdsReco.CalTS() }">
                </tbody>
            </table>
            <div id="reco-footer" class="col-md-12 row" data-bind="visible: coreWebApp.tdsReco.showFooter()">
                <div class="col-md-2"></div>
                <!--            <div class=" col-md-3 form-group">
                                <label class="control-label" for="book-bal">Book balance:</label>
                                <span id="book-bal" data-bind="text: coreWebApp.tdsReco.FormatBal(bookBalance())"></span>
                            </div>-->
                <div class=" col-md-3 form-group">
                    <label class="control-label" for="unreco-sum">Unreconciled:</label>
                    <span id="unreo-sum" data-bind="text: coreWebApp.tdsReco.FormatBal(unrecoSum())"></span>
                </div>
                <div class=" col-md-3 form-group">
                    <label class="control-label" for="tds-bal">TDS balance:</label>
                    <span id="tds-bal" data-bind="text: coreWebApp.tdsReco.FormatBal(tdsBalance())"></span>
                </div>
            </div>
        </div>

        <script id="recodata-template" type="text/html">
            <tr> 
                <td data-bind="dateValue: doc_date, attr:{'data-sort': doc_date_sort}">
                </td>
                <td data-bind="text: voucher_id">
                </td>
                <td data-bind="text: customer">
                </td>
                <td data-bind="text: pan">
                </td>
                <td data-bind="text: tan">
                </td>
                <td data-bind="numericValue: tds_amt" style="text-align: right">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: reconciled, click: coreWebApp.tdsReco.CheckChanged($data)">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10 datetime" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="dateValue: reco_date, enable: reconciled, attr: {'id': 'cd'+$index()} ">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">
        $('#tdsreco').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        //create and bind tdsReco namespace
        window.coreWebApp.tdsReco = {};
        (function (tdsReco) {

            function getData() {
                $('#brules').html('');
                var res = $('#tdsreco').serialize();
                $('#tdsreco input[type=checkbox]:not(:checked)').each(
                        function () {
                            res += '&' + this.name + '=0';
                        });

                if ($('#as_on').val() === '' || $('#customer_id').val() < 0) {
                    alert('TDS and As On date must be selected.');
                    return;
                }
                // get actual data

                form_method = $('#tdsreco').attr('method');
                form_action = $('#tdsreco').attr('action');
                form_target = $('#tdsreco').attr('target');
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
                        tdsReco.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        tdsReco.BuildCustomProperties();
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#recodata').show();
                        ko.cleanNode($('#recodata')[0]);
                        ko.applyBindings(tdsReco.ModelBo, $('#recodata')[0]);
                        tdsReco.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            tdsReco.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(tdsReco.ModelBo);
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
                            ko.cleanNode($('#recodata')[0]);
                            tdsReco.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            tdsReco.BuildCustomProperties();
                            ko.applyBindings(tdsReco.ModelBo, $('#recodata')[0]);
                            coreWebApp.applyDatepicker('');
                            tdsReco.ToggleUpdate();
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
            tdsReco.SetJsonData = setJsonData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            tdsReco.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: doc_date"]').each(function () {
                    var temp = tdsReco.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            tdsReco.CalTS = calTS;

            function toggleUpdate() {
                $('#cmdupdatetdsreco').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (tdsReco.ModelBo.dt().length > 0) {
                        $('#cmdupdatetdsreco').show();
                    }
                }
            }
            tdsReco.ToggleUpdate = toggleUpdate;

            function buildCustomProperties() {
                tdsReco.ModelBo.unrecoSum = ko.computed(function () {
                    var total = 0;
                    var dateval = $('#as_on').val();
                    var as_on = coreWebApp.unformatDate(dateval);
                    ko.utils.arrayForEach(tdsReco.ModelBo.dt(), function (row) {
                        if (!row.reconciled() || (row.reconciled() && row.reco_date() > as_on)) {
                            total = total + parseFloat(row.tds_amt());
                        }
                    });
                    return total;
                });
                tdsReco.ModelBo.tdsBalance = ko.computed(function () {
                    // TDS balance is always represented from TDSer's point of view. 
                    // Hence it is the exact opposite of book balance
                    return (tdsReco.ModelBo.bookBalance() - tdsReco.ModelBo.unrecoSum()) * -1;
                });
            }
            tdsReco.BuildCustomProperties = buildCustomProperties;

            // Sets the As On date as the reco date by default
            function checkChanged(item) {
                if (item.reconciled() && item.reco_date() == '1970-01-01') {
                    var dateval = $('#as_on').val();
                    var as_on = coreWebApp.unformatDate(dateval);
                    item.reco_date(as_on);
                } else if (!item.reconciled() && item.reco_date() != '1970-01-01') {
                    item.reco_date('1970-01-01'); // setting the default date
                }
            }
            tdsReco.CheckChanged = checkChanged;

            function formatBal(balVal) {
                return coreWebApp.formatNumber(balVal, 2);
            }
            tdsReco.FormatBal = formatBal;

            function showFooter() {
                if ($('#view_type_id').val() == 0) {
                    return true;
                }
                return false;
            }
            tdsReco.showFooter = showFooter;


        }(window.coreWebApp.tdsReco));
    </script>
