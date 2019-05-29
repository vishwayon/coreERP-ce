<div id="contentholder" class="panel-group" style="margin: 5px;padding: 5px;">
    <div id="collheader" class="row cformheader">
        <div class="col-md-1 col-xs-2" style="padding-left: 0; padding-right:0;">
            <button id="mob-menu-back" class="btn btn-default" 
                    style="background-color:lightgrey;border-color:lightgrey;color:black;"
                    onclick="$('#contentholder').hide(); $('#mobile-menu-view').show();">
                <span id="mob-menu-bk-ic" class="glyphicon glyphicon-arrow-left" style="font-size: 14px;margin-left:-3px;"></span>
            </button>
        </div>
        <h3 class="col-md-6 col-xs-8" style="text-align:center;"> Pending approval requests</h3>
        <div class="col-md-4 col-xs-2 cformheaderbuttons" style="padding-left:0;">
            <button class="btn btn-sm btn-default" id="collrefresh"
                    style="float: right;" 
                    onclick="coreWebApp.wf_userdocs.GetData();" 
                    type="button">
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
            </button>
        </div>
    </div>
    <div id="collectiondata" name="collectiondata" style="margin-top: 10px;">
    </div>
    <div id="mcollcontents" class="panel panel-default">
        <ul id="ul-reqcollMob" class="list-group">

        </ul>
    </div>
</div>
<div id="details" style="display: none;">
</div>
<script type="text/javascript">
    $('#mobile-menu-view').hide();
    //create and bind wf_userdocs namespace
    window.coreWebApp.wf_userdocs = {};
    (function (wf_userdocs) {

        function getData() {
            $('#brules').html('');
            var res = $('#pendingreq').serialize();
            $.ajax({
                url: '?r=cwf%2Fsys%2Fwf-approval%2Fget-data',
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
                    wf_userdocs.ModelBo = ko.mapping.fromJS(jsonResult['jsondata']);
                    $('#ul-reqcollMob').html('');
                    litems = '';
                    if (jsonResult.jsondata.dt_request.length > 0) {
                        for (var i = 0, len = jsonResult.jsondata.dt_request.length; i < len; i++) {
                            litem = '<li id="req_' + jsonResult.jsondata.dt_request[i].doc_id + '"'
                                    + ' class="list-group-item" style="display: table; width: 100%;" onclick="click:coreWebApp.wf_userdocs.openSummary'
                                    + '(' + i
                                    + ')"><div class="col-xs-12" style="padding: 0;">';
                            litem += '<span class="col-xs-2" style="padding: 5px; font-weight:bold;">' + jsonResult.jsondata.dt_request[i].apr_type + '</span>';
                            litem += '<span class="col-xs-5" style="padding: 5px; font-weight:bold;">' + jsonResult.jsondata.dt_request[i].doc_id + '</span>';
                            litem += '<span class="col-xs-5" style="padding: 5px; text-align: right;">'
                                    + coreWebApp.formatDate(jsonResult.jsondata.dt_request[i].doc_date) + '</span>';
                            litem += '<span class="col-xs-12" style="padding: 5px;">' + jsonResult.jsondata.dt_request[i].bo_id + ' Sent on <strong>'
                                    + jsonResult.jsondata.dt_request[i].added_on + '</strong></span>';
                            litem += '<span class="col-xs-12" style="padding: 5px;"> by ' + jsonResult.jsondata.dt_request[i].from_user + '</span>';
                            litem += '</div></li>';
                            litems += litem;
                        }
                    } else {
                        litems = '<li class="list-group-item" style="display: table; width: 100%;">All approvals are processed</li>';
                    }
                    $('#ul-reqcollMob').html(litems);
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        wf_userdocs.GetData = getData;

        function getTimestamp(ctr) {
            var dateval = $(ctr).val();
            var unfdate = coreWebApp.unformatDate(dateval);
            var ts = new Date(unfdate).getTime();
            return ts;
        }
        wf_userdocs.GetTimestamp = getTimestamp;

        function calTS() {
            $('[data-bind="dateValue: doc_date"]').each(function () {
                var temp = wf_userdocs.GetTimestamp(this);
                $(this).attr('data-order', temp);
            });
        }
        wf_userdocs.calTS = calTS;

        function openSummary(i) {
            wf_userdocs.docdata = wf_userdocs.ModelBo.dt_request()[i];
            var qpRoute = wf_userdocs.docdata.route();
            var qpForm = wf_userdocs.docdata.formname();
            var qpKey = wf_userdocs.docdata.formparams();
            lnk = '?r=/' + qpRoute + '/form/summary&formName=' + qpForm + '&formParams=' + qpKey;
            coreWebApp.rendercontents(lnk, 'details', 'contentholder', 'coreWebApp.wf_userdocs.afterFormLoad');
        }
        wf_userdocs.openSummary = openSummary;

        function afterFormLoad() {
            $('#cmdsave').hide();
            ko.cleanNode($('#cmdclose')[0]);
            $('#cmdclose').click(function (e) {
                coreWebApp.wf_userdocs.wfcloseMobDetail();
            });
            $("[id^=div_wfar_]").css('margin-top', '10px');
            $("[id^=docwf_cmd_]").css('margin-top', '0');
            $("[id^=docwf_cmd_]").css('margin-bottom', '10px');
            $("#docwf_cmd_accept").css('float', 'left');
            $("#docwf_cmd_reject").css('float', 'right');
            $('#frm_summary').css('font-size', '14px');
            var afterloadevent = $('#hkAfterLoadEvent').val();
            if (typeof afterloadevent != 'undefined' && afterloadevent != '') {
                var func = new Function('{' + afterloadevent + '();}');
                func();
            }
        }
        wf_userdocs.afterFormLoad = afterFormLoad;

        function wfcloseMobDetail() {
            $('#details').html('');
            $('#details').hide();
            $('#contentholder').show();
            wf_userdocs.GetData();
            return false;
        }
        wf_userdocs.wfcloseMobDetail = wfcloseMobDetail;

        function setData(ifapproved) {
            $('#brules').html('');
            var resarr = Object();
            resarr.wf_approved = ifapproved;
            resarr.wf_comment = $('#docwf_userto_comments').val();
            resarr.doc_id = $('#doc_id_text').text();
            resarr.wf_ar_id = $('#wf_ar_id').val();
            var res = JSON.stringify(resarr);
            $.ajax({
                url: '?r=cwf%2Fsys%2Fwf-approval%2Fset-data',
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
                    if (jsonResult.brokenrules.length > 0) {
                        var brules = jsonResult.brokenrules;
                        var litems = '<strong>Broken Rules</strong>';
                        for (var i = 0; i < brules.length; i++) {
                            litems += "<li>" + brules[i] + "</li>";
                        }
                        $('#brules').append(litems);
                        $('#divbrules').show();
                    } else {
                        coreWebApp.closeDetail();
                        wf_userdocs.GetData();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        wf_userdocs.setData = setData;

    }(window.coreWebApp.wf_userdocs));

    coreWebApp.wf_userdocs.GetData();
</script>
