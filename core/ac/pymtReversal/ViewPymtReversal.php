<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$viewerurl = '?r=core%2Fac%2Fpymt-reversal%2Fgetdata';
$purl = '?r=core%2Fac%2Fpymt-reversal%2Fsetdata';

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
if (strtotime(date("Y-m-d", time())) > strtotime(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
    $as_on_date = FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
}
$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
?>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3 style="color: teal;">Payment Reversal</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="inv" name ="inv" 
                  target="invdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-3 form-group required" style="margin: 0 0 0 20px;">
                    <label class="control-label" for="bank_acc_id">Bank Account</label>
                    <?=
                    Html::input('SmartCombo', 'bank_acc_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'bank_acc_id', 'name' => 'bank_acc_id',
                        'data-validation' => 'required',
                        'data-valuemember' => 'account_id',
                        'data-displaymember' => 'account_head',
                        'data-filter' => 'account_type_id = 1',
                        'data-namedlookup' => '../core/ac/lookups/Account.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Bank'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="to_user_id">Voucher ID</label>
                    <?=
                    Html::input('text', 'find_vch_id', NULL, ['class' => 'form-control',
                        'id' => 'find_vch_id', 'name' => 'find_vch_id',
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
                        onclick="coreWebApp.pymtreversal.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
            </div>
        </div>
        <div id="invdata" class="row" style="display: none;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;margin-top: 15px;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <div style="margin-left: 1px;margin-bottom:10px;" class="row">
                <style type="text/css">
                    #vchdata td{padding: 10px;}
                </style>
                <table id="vchdata" style="margin: 15px 0 15px 0;width:90%; min-width:500px;border-top: 1px solid slategray;">          
                    <caption style="color: slategray;font-weight: bold;margin: 5px; padding:0;">Voucher Info</caption>
                    <tr>
                        <td style="color: gray;">Voucher ID</td><td data-bind="text: doc_id"></td>
                        <td style="color: gray;">Date</td><td data-bind="text: doc_date"></td>
                    </tr>
                    <tr>
                        <td style="color: gray;">Bank Account</td><td data-bind="text: bank_acc"></td>
                        <td style="color: gray;">Paid To</td><td data-bind="text: supp_name"></td>
                    </tr>
                    <tr>
                        <td style="color: gray;">Settled Amount</td><td data-bind="text: settled_amt"></td>
                        <td></td><td></td>
                    </tr>                    
                    <tr>
                        <td style="color: gray;">Reversal Date</td><td>                            
                            <?=
                            Html::input('Date', 'reversal_date', $as_on_date, ['class' => ' datetime form-control required',
                                'type' => 'Text',
                                'data-validation-format' => $form_date_format,
                                'data-validation' => 'date',
                                'data-validation-error-msg' => 'As on Date is required.',
                                'id' => 'reversal_date', 'name' => 'reversal_date',
                                'style' => 'width:100px',
                                'data-bind' => 'dateValue: reversal_date',
                                'start_date' => $year_begin,
                                'end_date' => $year_end]
                            )
                            ?>
                        </td>
                        <td style="color: gray;">Remarks</td><td>
                            <textarea id="rev_remark" rows="2"
                                      style="padding-left: 5px; padding-right: 5px;"
                                      data-bind="value: rev_remark"/>
                        </td>
                    </tr>
                    <tr>
                        <td>                        
                            <button class="btn btn-sm btn-default" id="cmdupdateinv" style="display: none; padding: 5px;"
                                    onclick="coreWebApp.pymtreversal.SetJsonData('<?= $purl ?>', 'POST', 'invdata');">
                                <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                            </button>
                        </td>
                        <td></td><td></td><td></td>
                    </tr>
                </table>

                <!--div id="pymt_tran-cont">
                    <table class="table table-hover table-condensed" id="pymt_tran" style="border-top: 1px solid slategray;">
                        <caption style="color: slategray;font-weight: bold;margin-left: 5px;">Share with</caption>
                        <thead>
                            <tr>
                                <th>Voucher #</th>
                                <th>Bill #</th>
                                <th>Bill Date</th>
                                <th>Settled Amt</th>
                            </tr>
                        </thead>
                        <tbody data-bind="template: { name: 'pymt_tran_template', foreach: dtCust  }"></tbody>
                    </table>
                </div>                    
                <div style="display: none;" id="pymt_tran-errors"></div>
                <script type="text/html" id="pymt_tran_template">
                    <td data-bind="text: vch_id">
                    </td
                    <td data-bind="text: bill_id">
                    </td>
                    <td data-bind="dateValue: vch_date, attr:{'data-sort': doc_date_sort}">
                    </td>
                    <td data-bind="text: sett_amt">
                    </td>
                    </tr>
                    </script>
                </div-->
            </div>
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
        window.coreWebApp.pymtreversal = {};
        (function (pymtreversal) {
            vch_tran = [];
            frminfo = null;
            function getData() {
                $('#brules').html('');
                var res = $('#inv').serialize();
                frminfo = res;
                form_method = $('#inv').attr('method');
                form_action = $('#inv').attr('action');
                form_target = $('#inv').attr('target');
                $.ajax({
                    url: form_action,
                    type: form_method,
                    dataType: 'json',
                    data: {'params': res, 'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        $('#brules').html('');
                        if (resultdata.jsondata.brokenrules.length > 0) {
                            var brules = resultdata.jsondata.brokenrules;
                            var litems = '<strong>Broken Rules</strong>';
                            for (var i = 0; i < brules.length; i++) {
                                litems += "<li>" + brules[i] + "</li>";
                            }
                            $('#brules').append(litems);
                            $('#divbrules').show();
                            $('#invdata').show();
                            $('#vchdata').hide();
                        } else if (resultdata.jsondata.dtVch.length > 0) {
                            pymtreversal.ModelBo = ko.mapping.fromJS(resultdata.jsondata);
                            $('#invdata').show();
                            $('#vchdata').show();
                            ko.cleanNode($('#invdata')[0]);
                            $('#reversal_date').datepicker('remove');
                            $('#reversal_date').attr('start_date',
                                    $.datepicker.formatDate(coreWebApp.dateFormat.substr(0, 8), new Date(resultdata.jsondata.min_date)));
                            coreWebApp.applyDatepicker($('#reversal_date'));
                            ko.applyBindings(pymtreversal.ModelBo, $('#invdata')[0]);
                            if (resultdata.jsondata.rev_remark == '') {
                                $('#cmdupdateinv').show();
                            }
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            pymtreversal.GetData = getData;

            function setJsonData() {
                if (coreWebApp.formatDate(coreWebApp.pymtreversal.ModelBo.reversal_date()) > coreWebApp.formatDate(Date())) {
                    var res = coreWebApp.customprompt('warning', 'The reversal date is future date. Are you sure you want to proceed?', function () {
                        actualSetData();
                    });
                } else {
                    actualSetData();
                }
            }
            pymtreversal.SetJsonData = setJsonData;

            function actualSetData() {
                var res = coreWebApp.customprompt('error', 'This action is irreversible. Are you sure you want to proceed?', function () {
                    $('#brules').html('');
                    $('#divbrules').hide();
                    if (pymtreversal.ModelBo.reversal_date() == null ||
                            pymtreversal.ModelBo.reversal_date() == '' ||
                            pymtreversal.ModelBo.rev_remark() == null ||
                            pymtreversal.ModelBo.rev_remark() == '') {
                        var litems = '<strong>Broken Rules</strong>';
                        litems += "<li> Reversal date and remarks are required.</li>";
                        if (pymtreversal.ModelBo.reversal_date() < pymtreversal.ModelBo.doc_date()) {
                            litems += "<li> Reversal date can not be earlier than settlement date.</li>";
                        }
                        $('#brules').append(litems);
                        $('#divbrules').show();
                        return;
                    }
                    var data = ko.mapping.toJSON(pymtreversal.ModelBo);
                    $.ajax({
                        url: '?r=core%2Fac%2Fpymt-reversal%2Fsetdata',
                        type: 'POST',
                        dataType: 'json',
                        data: data,
                        beforeSend: function () {
                            coreWebApp.startloading();
                        },
                        complete: function () {
                            coreWebApp.stoploading();
                        },
                        success: function (resultdata) {
                            $('#brules').html('');
                            if (resultdata.jsondata.brokenrules.length > 0) {
                                coreWebApp.toastmsg('warning', 'Reversal Failed', '', false);
                                var brules = resultdata.jsondata.brokenrules;
                                var litems = '<strong>Broken Rules</strong>';
                                for (var i = 0; i < brules.length; i++) {
                                    litems += "<li>" + brules[i] + "</li>";
                                }
                                $('#brules').append(litems);
                                $('#divbrules').show();
                                $('#est_info').show();
                            } else if (resultdata.jsondata.status == 'OK') {
                                coreWebApp.toastmsg('success', 'Payment reversed.', '', false);
                                $('#cmdupdateinv').hide();
                            } else {
                                coreWebApp.toastmsg('error', 'Payment not reversed', '', false);
                            }
                        },
                        error: function (data) {
                            coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                            coreWebApp.stoploading();
                        }
                    });
                    return false;
                });
            }
            pymtreversal.actualSetData = actualSetData;

        }(window.coreWebApp.pymtreversal));
    </script>
