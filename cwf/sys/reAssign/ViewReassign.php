<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=cwf%2Fsys%2Freassign%2Fgetdata';
$purl = '?r=cwf%2Fsys%2Freassign%2Fsetdata';

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
            <div class="col-md-8">
                <h3>Reassign document flow request</h3>                  
            </div>

            <form class="form-horizontal required col-md-8" id="reassignreq" name ="reassignreq" 
                  target="preqdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="branch_id">Branch</label>
                    <?=
                    Html::input('SmartCombo', 'branch_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'branch_id', 'name' => 'branch_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'branch_id',
                        'data-displaymember' => 'branch_name',
                        'data-namedlookup' => '../cwf/sys/lookups/BranchWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select branch'])
                    ?>
                </div>                
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="doc_bo_id">Document Type</label>
                    <?=
                    Html::input('SmartCombo', 'doc_bo_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'doc_bo_id', 'name' => 'doc_bo_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'bo_id',
                        'data-displaymember' => 'menu_text',
                        'data-namedlookup' => '../cwf/sys/lookups/WfBOlist.xml',
                        'data-validations' => 'string',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select doc type'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="from_user_id">From user</label>
                    <?=
                    Html::input('SmartCombo', 'from_user_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'from_user_id', 'name' => 'from_user_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'user_id',
                        'data-displaymember' => 'full_user_name',
                        'data-namedlookup' => '../cwf/sys/lookups/UserCompany.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select user'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="to_user_id">To user</label>
                    <?=
                    Html::input('SmartCombo', 'to_user_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'to_user_id', 'name' => 'to_user_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'user_id',
                        'data-displaymember' => 'full_user_name',
                        'data-namedlookup' => '../cwf/sys/lookups/UserCompany.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select user'])
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
                        'style' => 'padding:0px;',
                        'data-validation-error-msg' => 'Please enter voucher id'])
                    ?>
                </div>
            </form>

            <div class="col-md-2" style="float: right;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.wf_reassign.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatewfreq" style="display: none;"
                        onclick="coreWebApp.wf_reassign.SetJsonData('<?= $purl ?>', 'POST', 'preqdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="preqdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Voucher id</th>
                        <th>Assign Date</th>
                        <th>Action</th>
                        <th>From User</th>
                        <th>To User</th>
                        <th>Reassign</th>
                        <th>Remark</th>
                        <th>Assign to User</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'preqdata-template', foreach: dt_request, afterRender: coreWebApp.wf_reassign.CalTS() }">
                </tbody>
            </table>            
        </div>

        <script id="preqdata-template" type="text/html">
            <tr> 
                <td data-bind="text: doc_id">
                </td>
                <td data-bind="dateValue: doc_sent_on, attr:{'data-sort': doc_date_sort}">
                </td>
                <td>
                    <select id="wf_id" class="form-control" name="wf_id" data-bind="value: doc_action" disabled="true">
                        <option value="S">Sent</option>
                        <option value="A">Approved</option>
                        <option value="R">Rejected</option>
                        <option value="P">Posted</option>
                        <option value="U">Unposted</option>
                        <option value="I">Assigned</option>
                    </select>
                </td>
                <td data-bind="text: from_user">
                </td>
                <td data-bind="text: to_user">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: select, click: coreWebApp.wf_reassign.wfchecked($data, $element)">
                </td>
                <td>
                    <input id="remark" class="form-control" name="remark" data-validation="required" data-bind="text: remark, enable: select"
                           data-filter="" data-validations="string" style="padding:0px;" data-validation-error-msg="Please enter remark" type="text">
                </td>
                <td style="" class="td-new_user_id_to" colspan="1">
                    <select id="new_user_id_to" class="form-control" name="new_user_id_to" data-bind="value: new_user_id_to, enable: select" disabled="true">
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

        $('#reassignreq').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        //create and bind wf_reassign namespace
        window.coreWebApp.wf_reassign = {};
        (function (wf_reassign) {

            function getData() {
                $('#brules').html('');
                var res = $('#reassignreq').serialize();
                $.ajax({
                    url: '?r=cwf%2Fsys%2Freassign%2Fgetdata',
                    type: 'GET',
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
                            $('#preqdata').show();
                            $('#vch_tran').hide();
                        } else {
                            wf_reassign.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            $('#preqdata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#preqdata')[0]);
                            ko.applyBindings(wf_reassign.ModelBo, $('#preqdata')[0]);
                            wf_reassign.ToggleUpdate();
                            coreWebApp.initCollection('vch_tran');
                            var dtht = $('#contentholder').height() - $('#collheader').height() - 77;
                            $('.dataTables_scrollBody').css('min-height', dtht);
                            $('.dataTables_scrollBody').height(dtht);
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            wf_reassign.GetData = getData;

            function setJsonData(formaction, formmethod, contentid) {
                form_method = formmethod;
                form_action = formaction;
                form_target = contentid;
                var data = ko.mapping.toJSON(wf_reassign.ModelBo);
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

                        if ($.fn.dataTable.isDataTable('#vch_tran')) {
                            var t = $('#vch_tran').DataTable();
                            t.destroy();
                        }
                        $('#vch_tran').show();
                        ko.cleanNode($('#preqdata')[0]);
                        wf_reassign.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                        ko.applyBindings(wf_reassign.ModelBo, $('#preqdata')[0]);
                        coreWebApp.applyDatepicker('');
                        wf_reassign.ToggleUpdate();
                        coreWebApp.initCollection('vch_tran');
                        var dtht = $('#contentholder').height() - $('#collheader').height() - 77;
                        $('.dataTables_scrollBody').css('min-height', dtht);
                        $('.dataTables_scrollBody').height(dtht);
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            }
            wf_reassign.SetJsonData = setJsonData;

            function docRoleData(role_id, branch_id,ctrl) {
                $.ajax({
                    url: '?r=cwf/sys/reassign/role-users',
                    type: 'GET',
                    data: {'role_id': role_id, 'branch_id': branch_id, 'reqtime': new Date().getTime()},
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var raw = $.parseJSON(resultdata);
                        var udata = ko.mapping.fromJS(raw);
                        $.each(udata.user_list(), function (index, val) {
                            $(ctrl).append($('<option value="'+val.user_id()+'">'+val.full_user_name()+'</option>'));
                        });
                    },
                    error: function (data) {
                        toastmsg('error', 'Server Error', data.responseText, true);
                    }
                });
            }
            wf_reassign.docRoleData = docRoleData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            wf_reassign.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: doc_date"]').each(function () {
                    var temp = wf_reassign.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            wf_reassign.CalTS = calTS;

            function toggleUpdate() {
                $('#cmdupdatewfreq').hide();
                if ($("#view_type_id option:selected").text() !== 'All') {
                    if (wf_reassign.ModelBo.dt_request().length > 0) {
                        $('#cmdupdatewfreq').show();
                    }
                }
            }
            wf_reassign.ToggleUpdate = toggleUpdate;

            function wfchecked(item, ctrl) {
                if (item.select()) {
                    if (item.next_role.next_role_id() != '') {  
                        var user_combo = $(ctrl).parent().parent().children().find('#new_user_id_to');
                        wf_reassign.docRoleData(item.next_role.next_role_id(), item.branch_id(), user_combo);
//                        var next_roles = item.next_role.next_role_id().split(',');                      
//                        $.each(next_roles, function (index, value) {
//                            wf_reassign.docRoleData(value, item.branch_id(), user_combo);
//                        });
                    }
                }
            }
            wf_reassign.wfchecked = wfchecked;

        }(window.coreWebApp.wf_reassign));
    </script>