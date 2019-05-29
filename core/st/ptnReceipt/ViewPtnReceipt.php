<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Fst%2Fptn-receipt%2Fgetdata';
$purl = '?r=core%2Fst%2Fptn-receipt%2Fsetdata';
$status_option = array();
$status_option[0] = 'Pending Post';
$status_option[1] = 'Posted';


$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begins = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
$from_date =  FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));

$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_ends = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
$to_date =  FormatHelper::FormatDateForDisplay(app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/st/ptnReceipt/PtnReceipt_cc.js') ?>"></script>
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/st/ptn/Ptn_clientcode.js') ?>"></script>
        <div id="collheader" class="row">
            <h3>Production Transfer/Received</h3>
        </div>
        <div id="collfilter" class="row">

            <form class="form-horizontal required" id="stparkpost" name ="stparkpost" 
                  target="stparkpostdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">

                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-2 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="status">Status</label>
                    <?= Html::dropDownList('status', 'Pending Post', $status_option, ['class' => 'form-control', 'id' => 'status'])
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
                        'start_date' => $year_begins,
                        'end_date' => $year_ends]
                    )
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="to_date">To</label>
                    <?=
                    Html::input('Date', 'to_date', $to_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'To Date is required.',
                        'id' => 'to_date', 'name' => 'to_date',
                        'start_date' => $year_begins,
                        'end_date' => $year_ends]
                    )
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.stparkpost.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <!--<button class="btn btn-sm btn-default" id="cmdupdateinv" style="display: none;"
                        onclick="coreWebApp.stparkpost.SetJsonData('<?= $purl ?>', 'POST', 'stparkpostdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>-->
            </div>
        </div>
        <div id="collfilter" class="row">
             <div>
                <span id="note1" class="form-group clabel col-md-9 col-xs-12" style="margin-top: 0px;margin-bottom: 0px; margin-left: 10px;">
                    Note : For status Pending Post, date filters are not applied.
                </span>
            </div>            
        </div>
        <div id="stparkpostdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">        
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th style="width:150px;">Voucher id</th>
                        <th style="width:150px;">Voucher Date</th>
                        <th style="width:150px;">Source Branch</th>
                        <th style="width:20px;">...</th>
                        <!--<th style="width:20px;">...</th>
                        <th style="width:10px;">Posted</th>
                        <th style="width:150px;">Date</th>
                        <th>Reference</th>-->
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'stparkpostdata-template', foreach: dt, afterRender: coreWebApp.stparkpost.CalTS() }">
                </tbody>
            </table>
        </div>
        <script id="stparkpostdata-template" type="text/html">
            <tr> 
                <td data-bind="text: stock_id"/>
                <td data-bind="dateValue: st_date"/>
                <td data-bind="text: branch_name"/>
                <td class="">
                    <button id="btn_post" class=" btn simple-button" style="margin:0; padding:2px 10px; border:1px solid lightgrey;" data-bind="click: function() {ptnr.post_stpp($data) }">...</button>
                </td>
                <!--<td class="">
                    <button id="btn_disp" class=" btn simple-button" style="margin:0; padding:2px 10px; border:1px solid lightgrey;" data-bind="click: function() {core_st.core_stocktransferparkpost.display_stpp($data) }">...</button>
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: posted, click: coreWebApp.stparkpost.CheckChanged($data)">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10 datetime" 
                           style="padding-left: 5px; padding-right: 5px;"
                           class=" datetime" start_date= "<?= $year_begins ?>" end_date= "<?= $year_ends ?>" data-bind="dateValue: doc_date, enable: posted, attr: {'id': 'cd'+$index()} ">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: reference, enable: posted" maxlength="50">
                </td>-->
            </tr>
            </script>
        </div>
    </div>
    <div id="cdialog" class="view-min-width view-window2" style="display: none;">
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">


        $('#stparkpost').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });


        //create and bind stparkpost namespace
        window.coreWebApp.stparkpost = {};
        (function (stparkpost) {
            function getData() {
                $('#brules').html('');
                var res = $('#stparkpost').serialize();
                res = res.replace(/=on/g, '=1');
                res = res.replace(/=True/g, '=1');

                form_method = $('#stparkpost').attr('method');
                form_action = $('#stparkpost').attr('action');
                form_target = $('#stparkpost').attr('target');
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
                        stparkpost.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#stparkpostdata').show();
                        ko.cleanNode($('#stparkpostdata')[0]);
                        ko.applyBindings(stparkpost.ModelBo, $('#stparkpostdata')[0]);
                        stparkpost.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            stparkpost.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                if ($('#status').val() == 1) {
                    coreWebApp.toastmsg('warning', 'Status Update', 'Cannot reverse Stock Transfers/Received and acknowledged');
                    return;
                }
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(stparkpost.ModelBo);
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
                            ko.cleanNode($('#stparkpostdata')[0]);
                            stparkpost.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            ko.applyBindings(stparkpost.ModelBo, $('#stparkpostdata')[0]);
                            coreWebApp.applyDatepicker('');
                            stparkpost.ToggleUpdate();
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
            stparkpost.SetJsonData = setJsonData;


            function calTS() {
                $('[data-bind="dateValue: ro_date"]').each(function () {
                    var temp = stparkpost.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            stparkpost.CalTS = calTS;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            stparkpost.GetTimestamp = getTimestamp;

            function toggleUpdate() {
                $('#cmdupdateinv').hide();
                //                if ($("#view_type_id option:is_dispatched").text() !== 'Released') {
                if (stparkpost.ModelBo.dt().length > 0) {
                    $('#cmdupdateinv').show();
                }
                //                }
            }
            stparkpost.ToggleUpdate = toggleUpdate;

            // Sets the As On date as the reco date by default
            function checkChanged(item) {
                //                if(item.posted() && item.doc_date() == '1970-01-01') {
                //                    var dateval = $('#to_date').val();
                //                    var as_on = coreWebApp.unformatDate(dateval);
                //                    item.doc_date(as_on);
                //                    coreWebApp.applyDatepicker($('#doc_date'));
                //                } else if (!item.posted() && item.doc_date() != '1970-01-01') {
                //                    item.doc_date('1970-01-01'); // setting the default date
                //                }
            }
            stparkpost.CheckChanged = checkChanged;
            
        }(window.coreWebApp.stparkpost));
    </script>
