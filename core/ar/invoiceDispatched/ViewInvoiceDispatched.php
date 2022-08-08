<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Far%2Finvoice-dispatched%2Fgetdata';
$purl = '?r=core%2Far%2Finvoice-dispatched%2Fsetdata';
$from_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$to_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
if (strtotime(date("Y-m-d", time())) > strtotime(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
    $as_on_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
    $to_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
    $from_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
}

$dispatch_method_option = array();
$dispatch_method_option[0] = 'None';
$dispatch_method_option[1] = 'Courier';
$dispatch_method_option[2] = 'e-mail';
$dispatch_method_option[3] = 'Hand Delivery';

$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());

$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
?>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Invoice Dispatched</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="inv" name ="inv" 
                  target="invdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">

                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="customer_id">Customer</label>
                    <?=
                    Html::input('SmartCombo', 'customer_id', 0, ['class' => 'smartcombo form-control required',
                        'id' => 'customer_id', 'name' => 'customer_id',
                        'data-validation' => 'required',
                        'data-valuemember' => 'customer_id',
                        'data-displaymember' => 'customer',
                        'data-filter' => '',
                        'data-namedlookup' => '@app/core/ar/lookups/CustomerWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Customer'])
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
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
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
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
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.inv.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdateinv" style="display: none;"
                        onclick="coreWebApp.inv.SetJsonData('<?= $purl ?>', 'POST', 'invdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="invdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
            </table>
        </div>
<!--        <script id="invdata-template" type="text/html">
            <tr> 
                <td data-bind="dateValue: doc_date">
                </td>
                <td data-bind="text: voucher_id">
                </td>
                <td data-bind="text: customer">
                </td>
                <td data-bind="text: salesman_name">
                </td>
                <td data-bind="numericValue: invoice_amt" style="text-align: right">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: is_dispatched, click: coreWebApp.inv.CheckChanged($data)">
                </td> 
                <td class="">
                    <input type="Text" class="col-md-10 datetime" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="dateValue: dispatched_date, enable: is_dispatched, attr: {'id': 'cd'+$index()} ">
                </td>
                <td class="">
        <?= Html::dropDownList('dispatch_method', 'None', $dispatch_method_option, ['class' => 'form-control', 'id' => 'dispatch_method', 'data-bind' => 'value: dispatch_method, enable: is_dispatched'])
        ?>
                </td>
                <td class="">
                    <input id="dispatch_remark" type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: dispatch_remark, enable: is_dispatched">
                </td>
            </tr>
            </script>-->
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
<script type="text/javascript">

    $('#inv').find('input').each(function () {
        if ($(this).hasClass('smartcombo')) {
            coreWebApp.applySmartCombo(this);
        } else if ($(this).hasClass('datetime')) {
            coreWebApp.applyDatepicker(this);
        } else if ($(this).attr('type') == 'decimal') {
            coreWebApp.applyNumber(this);
        }
    });


    //create and bind inv namespace
    window.coreWebApp.inv = {};
    (function (inv) {
        function getData() {
            $('#brules').html('');
            var res = $('#inv').serialize();
            $('#bankreco input[type=checkbox]:not(:checked)').each(
                    function () {
                        res += '&' + this.name + '=0';
                    });

            form_method = $('#inv').attr('method');
            form_action = $('#inv').attr('action');
            form_target = $('#inv').attr('target');
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
                    if ($.fn.dataTable.isDataTable('#vch_tran')) {
                        var t = $('#vch_tran').DataTable();
                        t.destroy();
                    }
                    $('#invdata').show();
                    ko.cleanNode($('#invdata')[0]);
                    inv.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                    var tbl = $('#vch_tran').DataTable({
                        columns: [
                            {data: {_: "doc_date", display: "doc_date.display", sort: "doc_date.sort"}, title: "Doc Date", type: "num"},
                            {data: "voucher_id", title: "Voucher id"},
                            {data: "customer", title: "Customer"},
                            {data: "salesman_name", title: "Salesman"}
                            ,
                            {data: "invoice_amt", title: "Invoice Amt", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData(), 2);
                                }
                            },
                            {data: "is_dispatched", title: "Dispatched",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: is_dispatched, click: coreWebApp.inv.CheckChanged($data)">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
                            {data: "dispatched_date", title: "Dispatched Date",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input class="datetime" data-bind="enable: is_dispatched, dateValue: dispatched_date" type="DateTime">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                },
                            },
                            {data: "dispatch_method", title: "Dispatch Method",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<select id="dispatch_method" class="form-control" name="dispatch_method" data-bind="value: dispatch_method, enable:is_dispatched"> \n\
                                                      <option value="0">None</option> \n\
\n\                                                   <option value="1">Courier</option> \n\
\n\                                                   <option value="2">e-mail</option> \n\
\n\                                                   <option value="3">Hand Delivery</option> \n\
\n\                                             </select>');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }
                            },
                            {data: "dispatch_remark", title: "Remarks",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="string" data-bind="value: dispatch_remark, enable:is_dispatched" max-length="500" autocomplete="off">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }
                            }
                        ],
                        data: inv.ModelBo.dt(),
                        deferRender: true,
                        scrollY: inv.getscrollheight() + 'px',
                        scrollCollapse: true,
                        scroller: true,
                        //scrollX: 'auto'
                    });
                    $('.dataTables_scrollBody').height(inv.getscrollheight());
                    $('.dataTables_scrollBody').css('background', 'white');
                    var l = $('#vch_tran_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                    $('.dataTables_empty').text('No data to display');
                    inv.ToggleUpdate();
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        inv.GetData = getData;

        function setJsonData(formaction, formmethod, contentid) {
            form_method = formmethod;
            form_action = formaction;
            form_target = contentid;
            var data = ko.mapping.toJSON(inv.ModelBo);
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
                        inv.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#vch_tran').show();
                        ko.cleanNode($('#invdata')[0]);
                        var tbl = $('#vch_tran').DataTable({
                            columns: [
                                {data: {_: "doc_date", display: "doc_date.display", sort: "doc_date.sort"}, title: "Doc Date", type: "num"},
                                {data: "voucher_id", title: "Voucher id"},
                                {data: "customer", title: "Customer"},
                                {data: "salesman_name", title: "Salesman"}
                                ,
                                {data: "invoice_amt", title: "Invoice Amt", className: "dt-right",
                                    render: function (cellData) {
                                        return coreWebApp.formatNumber(cellData(), 2);
                                    }
                                },
                                {data: "is_dispatched", title: "Dispatched",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<input type="checkbox" data-bind="checked: is_dispatched, click: coreWebApp.inv.CheckChanged($data)">');
                                        ko.applyBindings(rowData, $(td)[0]);
                                        $(td).css('text-align', 'center');
                                    }
                                },
                                {data: "dispatched_date", title: "Dispatched Date",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<input class="datetime" data-bind="enable: is_dispatched, dateValue: dispatched_date" type="DateTime">');
                                        ko.applyBindings(rowData, $(td)[0]);
                                    },
                                },
                                {data: "dispatch_method", title: "Dispatch Method",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<select id="dispatch_method" class="form-control" name="dispatch_method" data-bind="value: dispatch_method, enable:is_dispatched"> \n\
                                                      <option value="0">None</option> \n\
\n\                                                   <option value="1">Courier</option> \n\
\n\                                                   <option value="2">e-mail</option> \n\
\n\                                                   <option value="3">Hand Delivery</option> \n\
\n\                                             </select>');
                                        ko.applyBindings(rowData, $(td)[0]);
                                    }
                                },
                                {data: "dispatch_remark", title: "Remarks",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<input type="string" data-bind="value: dispatch_remark, enable:is_dispatched" max-length="500" autocomplete="off">');
                                        ko.applyBindings(rowData, $(td)[0]);
                                    }
                                }
                            ],
                            data: inv.ModelBo.dt(),
                            deferRender: true,
                            scrollY: inv.getscrollheight() + 'px',
                            scrollCollapse: true,
                            scroller: true,
                            //scrollX: 'auto'
                        });
                        $('.dataTables_scrollBody').height(inv.getscrollheight());
                        $('.dataTables_scrollBody').css('background', 'white');
                        var l = $('#vch_tran_length');
                        if (l !== 'undefined') {
                            l.hide();
                        }
                        $('.dataTables_empty').text('No data to display');
                        inv.ToggleUpdate();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
            return false;
        }
        inv.SetJsonData = setJsonData;


        function calTS() {
            $('[data-bind="dateValue: ro_date"]').each(function () {
                var temp = inv.GetTimestamp(this);
                $(this).attr('data-order', temp);
            });
        }
        inv.CalTS = calTS;

        function getTimestamp(ctr) {
            var dateval = $(ctr).val();
            var unfdate = coreWebApp.unformatDate(dateval);
            var ts = new Date(unfdate).getTime();
            return ts;
        }
        inv.GetTimestamp = getTimestamp;

        function toggleUpdate() {
            $('#cmdupdateinv').hide();
//                if ($("#view_type_id option:is_dispatched").text() !== 'Released') {
            if (inv.ModelBo.dt().length > 0) {
                $('#cmdupdateinv').show();
            }
//                }
        }
        inv.ToggleUpdate = toggleUpdate;

        // Sets the As On date as the reco date by default
        function checkChanged(item) {
            if (item.is_dispatched() && item.dispatched_date() == '1970-01-01') {
                var dateval = $('#to_date').val();
                var as_on = coreWebApp.unformatDate(dateval);
                item.dispatched_date(as_on);
                coreWebApp.applyDatepicker($('#dispatched_date'));
            } else if (!item.is_dispatched() && item.dispatched_date() != '1970-01-01') {
                item.dispatched_date('1970-01-01'); // setting the default date
            }
        }
        inv.CheckChanged = checkChanged;

        function getScrollHeight() {
            r1 = parseInt($('#collheader').height());
            r2 = parseInt($('#collfilter').height());
            cntht = parseInt($('#content-root').height());
            var calht = cntht - r1 - r2 - 170;
            return calht;
        }
        inv.getscrollheight = getScrollHeight;

    }(window.coreWebApp.inv));
</script>
