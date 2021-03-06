<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Fac%2Fbank-reco%2Fgetdata';
$purl = '?r=core%2Fac%2Fbank-reco%2Fsetdata';
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
$from_date =  FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));

$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Bank Reconciliation</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="bankreco" name ="bankreco" 
                  target="recodata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-2 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="view_type_id">Status</label>
                    <?= Html::dropDownList('view_type_id', 'unReconciled', $view_type_option, ['class' => 'form-control', 'id' => 'view_type_id'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="account_id">Bank</label>
                    <?=
                    Html::input('SmartCombo', 'account_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'account_id', 'name' => 'account_id',
                        'data-validation' => 'required',
                        'data-filter' => 'account_type_id = 1',
                        'data-valuemember' => 'account_id',
                        'data-displaymember' => 'account_head',
                        'data-namedlookup' => '../core/ac/lookups/Account.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Bank'])
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
                        'id' => 'from_date', 'name' => 'as_on',
                        'start_date' => $year_begin,
                        'end_date' => $year_end]
                    )
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="as_on">To Date/As On</label>
                    <?=
                    Html::input('Date', 'as_on', $as_on_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'To/As on Date is required.',
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
                        onclick="coreWebApp.bankReco.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatebankreco" style="display: none;"
                        onclick="coreWebApp.bankReco.SetJsonData('<?= $purl ?>', 'POST', 'recodata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="recodata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran" cellspacing="0">
            </table>
            <div id="reco-footer" class="col-md-12 row" data-bind="visible: coreWebApp.bankReco.showFooter()">
                <div class="col-md-2"></div>
                <div class=" col-md-3 form-group">
                    <label class="control-label" for="book-bal">Book balance:</label>
                    <span id="book-bal" data-bind="text: coreWebApp.bankReco.FormatBal(bookBalance())"></span>
                </div>
                <div class=" col-md-3 form-group">
                    <label class="control-label" for="unreco-sum">Unreconciled:</label>
                    <span id="unreo-sum" data-bind="text: coreWebApp.bankReco.FormatBal(unrecoSum())"></span>
                </div>
                <div class=" col-md-3 form-group">
                    <label class="control-label" for="bank-bal">Bank balance:</label>
                    <span id="bank-bal" data-bind="text: coreWebApp.bankReco.FormatBal(bankBalance())"></span>
                </div>
            </div>
        </div>

    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
<script type="text/javascript">
    $('#bankreco').find('input').each(function () {
        if ($(this).hasClass('smartcombo')) {
            coreWebApp.applySmartCombo(this);
        } else if ($(this).hasClass('datetime')) {
            coreWebApp.applyDatepicker(this);
        } else if ($(this).attr('type') == 'decimal') {
            coreWebApp.applyNumber(this);
        }
    });

    //create and bind bankReco namespace
    window.coreWebApp.bankReco = {};
    (function (bankReco) {

        function getData() {
            $('#brules').html('');
            var res = $('#bankreco').serialize();
            $('#bankreco input[type=checkbox]:not(:checked)').each(
                    function () {
                        res += '&' + this.name + '=0';
                    });
            if ($('#as_on').val() === '' || $('#account_id').val() < 0) {
                alert('Bank and As On date must be selected.');
                return;
            }
            // get actual data

            form_method = $('#bankreco').attr('method');
            form_action = $('#bankreco').attr('action');
            form_target = $('#bankreco').attr('target');
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
                    $('#vch_tran').width($('#contents').width() - 30);
                    $('#recodata').show();
                    ko.cleanNode($('#recodata')[0]);
                    bankReco.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                    bankReco.BuildCustomProperties();
                    var tbl = $('#vch_tran').DataTable({
                        columns: [
                            {data: {_: "doc_date", display: "doc_date.display", sort: "doc_date.sort"}, title: "Doc Date", type: "num"},
                            {data: "voucher_id", title: "Voucher id"},
                            {data: "vch_caption", title: "Caption"},
                            {data: "cheque_number", title: "Cheque#", className: "dt-right"},
                            {data: {_: "cheque_date", display: "cheque_date.display", sort: "cheque_date.sort"}, title: "Cheque Date", type: "num"},
                            {data: "debit_amt", title: "Debit", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData(), 2);
                                }
                            },
                            {data: "credit_amt", title: "Credit", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData(), 2);
                                }
                            },
                            {data: "collected", title: "Collected",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: collected, click: coreWebApp.bankReco.CheckChanged($data)">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
                            {data: "collection_date", title: "Collection Date",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input class="datetime" data-bind="enable: collected, dateValue: collection_date" type="DateTime">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                },
                            }
                        ],
                        data: bankReco.ModelBo.dt(),
                        deferRender: true,
                        scrollY: bankReco.getscrollheight() + 'px',
                        scrollCollapse: true,
                        scroller: true,
                        //scrollX: 'auto'
                    });
                    $('.dataTables_scrollBody').height(bankReco.getscrollheight());
                    $('.dataTables_scrollBody').css('background', 'white');
                    var l = $('#vch_tran_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                    $('.dataTables_empty').text('No data to display');
                    ko.applyBindings(bankReco.ModelBo, $('#reco-footer')[0]);
                    bankReco.ToggleUpdate();
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        bankReco.GetData = getData;

        function setJsonData(formaction, formmethod, contentid) {
            form_method = formmethod;
            form_action = formaction;
            form_target = contentid;
            var data = ko.mapping.toJSON(bankReco.ModelBo);
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
                        bankReco.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        bankReco.BuildCustomProperties();
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#vch_tran').width($('#contents').width() - 30);
                        $('#vch_tran').show();
                        ko.cleanNode($('#recodata')[0]);
                        var tbl = $('#vch_tran').DataTable({
                            columns: [
                                {data: {_: "doc_date", display: "doc_date.display", sort: "doc_date.sort"}, title: "Doc Date", type: "num"},
                                {data: "voucher_id", title: "Voucher id"},
                                {data: "vch_caption", title: "Caption"},
                                {data: "cheque_number", title: "Cheque#", className: "dt-right"},
                                {data: {_: "cheque_date", display: "cheque_date.display", sort: "cheque_date.sort"}, title: "Cheque Date", type: "num"},
                                {data: "debit_amt", title: "Debit", className: "dt-right",
                                    render: function (cellData) {
                                        return coreWebApp.formatNumber(cellData(), 2);
                                    }
                                },
                                {data: "credit_amt", title: "Credit", className: "dt-right",
                                    render: function (cellData) {
                                        return coreWebApp.formatNumber(cellData(), 2);
                                    }
                                },
                                {data: "collected", title: "Collected",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<input type="checkbox" data-bind="checked: collected, click: coreWebApp.bankReco.CheckChanged($data)">');
                                        ko.applyBindings(rowData, $(td)[0]);
                                        $(td).css('text-align', 'center');
                                    }
                                },
                                {data: "collection_date", title: "Collection Date",
                                    createdCell: function (td, cellData, rowData, row, col) {
                                        $(td).html('<input class="datetime" data-bind="enable: collected, dateValue: collection_date" type="DateTime">');
                                        ko.applyBindings(rowData, $(td)[0]);
                                    },
                                }
                            ],
                            data: bankReco.ModelBo.dt(),
                            deferRender: true,
                            scrollY: bankReco.getscrollheight() + 'px',
                            scrollCollapse: true,
                            scroller: true,
                            //scrollX: 'auto'
                        });
                        $('.dataTables_scrollBody').height(bankReco.getscrollheight());
                        $('.dataTables_scrollBody').css('background', 'white');
                        var l = $('#vch_tran_length');
                        if (l !== 'undefined') {
                            l.hide();
                        }
                        $('.dataTables_empty').text('No data to display');
                        ko.applyBindings(bankReco.ModelBo, $('#reco-footer')[0]);
                        bankReco.ToggleUpdate();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
            return false;
        }
        bankReco.SetJsonData = setJsonData;

        function getTimestamp(ctr) {
            var dateval = $(ctr).val();
            var unfdate = coreWebApp.unformatDate(dateval);
            var ts = new Date(unfdate).getTime();
            return ts;
        }
        bankReco.GetTimestamp = getTimestamp;

        function calTS() {
            $('[data-bind="dateValue: doc_date"]').each(function () {
                var temp = bankReco.GetTimestamp(this);
                $(this).attr('data-order', temp);
            });
        }
        bankReco.CalTS = calTS;

        function toggleUpdate() {
            $('#cmdupdatebankreco').hide();
            if ($("#view_type_id option:selected").text() !== 'All') {
                if (bankReco.ModelBo.dt().length > 0) {
                    $('#cmdupdatebankreco').show();
                }
            }
        }
        bankReco.ToggleUpdate = toggleUpdate;

        function buildCustomProperties() {
            bankReco.ModelBo.unrecoSum = ko.computed(function () {
                var total = 0;
                var dateval = $('#as_on').val();
                var as_on = coreWebApp.unformatDate(dateval);
                ko.utils.arrayForEach(bankReco.ModelBo.dt(), function (row) {
                    if (!row.collected() || (row.collected() && row.collection_date() > as_on)) {
                        total += parseFloat(row.debit_amt()) - parseFloat(row.credit_amt());
                    }
                });
                return total;
            });
            bankReco.ModelBo.bankBalance = ko.computed(function () {
                // Bank balance is always represented from Banker's point of view. 
                // Hence it is the exact opposite of book balance
                return (bankReco.ModelBo.bookBalance() - bankReco.ModelBo.unrecoSum()) * -1;
            });
        }
        bankReco.BuildCustomProperties = buildCustomProperties;

        // Sets the As On date as the reco date by default
        function checkChanged(item) {
            if (item.collected() && item.collection_date() == '1970-01-01') {
                var dateval = $('#as_on').val();
                var as_on = coreWebApp.unformatDate(dateval);
                item.collection_date(as_on);
            } else if (!item.collected() && item.collection_date() != '1970-01-01') {
                item.collection_date('1970-01-01'); // setting the default date
            }
        }
        bankReco.CheckChanged = checkChanged;

        function formatBal(balVal) {
            if (parseFloat(balVal).toFixed(2) > 0) {
                return coreWebApp.formatNumber(balVal, 2) + ' Dr';
            } else if (parseFloat(balVal).toFixed(2) < 0) {
                return coreWebApp.formatNumber(parseFloat(balVal * -1).toFixed(2), 2) + ' Cr';
            } else {
                return '0.00';
            }
        }
        bankReco.FormatBal = formatBal;

        function showFooter() {
            if ($('#view_type_id').val() == 0) {
                return true;
            }
            return false;
        }
        bankReco.showFooter = showFooter;

        function getScrollHeight() {
            r1 = parseInt($('#collheader').height());
            r2 = parseInt($('#collfilter').height());
            cntht = parseInt($('#content-root').height());
            var calht = cntht - r1 - r2 - 170;
            return calht;
        }
        bankReco.getscrollheight = getScrollHeight;

    }(window.coreWebApp.bankReco));
</script>
