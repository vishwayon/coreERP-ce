<?php

use yii\helpers\Html;

$viewerurl = '?r=cwf%2Fsys%2Fpending-docs%2Fgetdata';
?>

<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-8">
                <h3>Pending documents</h3>                  
            </div>

            <form class="form-horizontal required col-md-10" id="pendingreq" name ="pendingreq" 
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
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="from_user_id">From user</label>
                    <?=
                    Html::input('SmartCombo', 'from_user_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'from_user_id', 'name' => 'from_user_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'user_id',
                        'data-displaymember' => 'full_user_name',
                        'data-namedlookup' => '../cwf/sys/lookups/UserWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select user'])
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="to_user_id">To user</label>
                    <?=
                    Html::input('SmartCombo', 'to_user_id', NULL, ['class' => 'smartcombo form-control required',
                        'id' => 'to_user_id', 'name' => 'to_user_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'user_id',
                        'data-displaymember' => 'full_user_name',
                        'data-namedlookup' => '../cwf/sys/lookups/UserWithAll.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select user'])
                    ?>
                </div>
                <div class="col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="doc_action">Doc Status</label>
                    <select id="doc_action_id" class="form-control" name="doc_action_id" data-bind="value: doc_action">
                        <option value="W">In workflow</option>
                        <option value="S">Sent (in WF)</option>
                        <option value="A">Approved (in WF)</option>
                        <option value="R">Rejected (in WF)</option>
                        <option value="U">Unposted (in WF)</option>
                        <option value="I">Assigned (in WF)</option>
                        <option value="O">Saved (not in WF)</option>
                    </select>
                </div>
            </form>
            <div class="col-md-1" style="float: right;margin-top:16px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.wf_pending.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>                
            </div>
        </div>
        <div id="preqdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
            </table>            
        </div>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">

        $('#pendingreq').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });

        //create and bind wf_pending namespace
        window.coreWebApp.wf_pending = {};
        (function (wf_pending) {
            function getData() {
                $('#brules').html('');
                var res = $('#pendingreq').serialize();
                $.ajax({
                    url: '?r=cwf%2Fsys%2Fpending-docs%2Fgetdata',
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
                            if ($.fn.dataTable.isDataTable('#vch_tran')) {
                                var t = $('#vch_tran').DataTable();
                                t.destroy();
                            }
                            $('#preqdata').show();
                            $('#vch_tran').show();
                            ko.cleanNode($('#preqdata')[0]);
                            wf_pending.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                            var tbl = $('#vch_tran').DataTable({
                                columns: [                                    
                                    {data: "branch_name", title: "Branch"},
                                    {data: "menu_text", title: "Doc Type"},
                                    {data: "doc_id", title: "Voucher ID"},
                                    {data: {_: "doc_sent_on", display: "doc_sent_on.display", sort: "doc_sent_on.sort"}, title: "Assign Date", type: "num"},
                                    {data: "doc_action", title: "Status",
                                        createdCell: function (td, cellData, rowData, row, col) {
                                            $(td).html('<select id="doc_action" class="form-control" name="doc_action" data-bind="value: doc_action, enable:false"> \n\
                                                              <option value="S">Sent</option> \n\
        \n\                                                   <option value="A">Approved</option> \n\
        \n\                                                   <option value="R">Rejected</option> \n\
        \n\                                                   <option value="P">Posted</option> \n\
        \n\                                                   <option value="U">Unposted</option> \n\
        \n\                                                   <option value="I">Assigned</option> \n\
        \n\                                                   <option value="O">Saved</option> \n\
        \n\                                             </select>');
                                            ko.applyBindings(rowData, $(td)[0]);
                                        }
                                    },
                                    {data: "from_user", title: "From User"},
                                    {data: "to_user", title: "To User"}
                                ],
                                data: wf_pending.ModelBo.dt_request(),
                                deferRender: true,
                                scrollY: wf_pending.getscrollheight() + 'px',
                                scrollCollapse: true,
                                scroller: true
                            });
                            $('.dataTables_scrollBody').height(wf_pending.getscrollheight());
                            $('.dataTables_scrollBody').css('background', 'white');
                            var l = $('#vch_tran_length');
                            if (l !== 'undefined') {
                                l.hide();
                            }
                            $('.dataTables_empty').text('No data to display');
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
            }
            wf_pending.GetData = getData;

            function getTimestamp(ctr) {
                var dateval = $(ctr).val();
                var unfdate = coreWebApp.unformatDate(dateval);
                var ts = new Date(unfdate).getTime();
                return ts;
            }
            wf_pending.GetTimestamp = getTimestamp;

            function calTS() {
                $('[data-bind="dateValue: doc_date"]').each(function () {
                    var temp = wf_pending.GetTimestamp(this);
                    $(this).attr('data-order', temp);
                });
            }
            wf_pending.CalTS = calTS;
            
            function getScrollHeight() {
                r1 = parseInt($('#collheader').height());
                r2 = parseInt($('#collfilter').height());
                cntht = parseInt($('#content-root').height());
                var calht = cntht - r1 - r2 - 170;
                return calht;
            }
            wf_pending.getscrollheight = getScrollHeight;

        }(window.coreWebApp.wf_pending));
    </script>
