<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$viewerurl = '?r=cwf%2Fsys%2Fmain%2Fget-audit-trail';
?>

<div id="contentholder"  class="view-min-width view-window1" style="margin: 5px;width:99%;">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <div class="col-md-12">
                <h3>Audit Trail Viewer</h3>                  
            </div>
            <form class="form-horizontal required col-md-3" id="reassignreq" name ="reassignreq" 
                  target="divattdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class="required" style="margin-top: 0px;">
                    <label class="control-label" for="att_vch_id">Voucher ID</label>
                    <?=
                    Html::input('text', 'att_vch_id', NULL, [//'class' => 'form-control',
                        'id' => 'att_vch_id', 'name' => 'att_vch_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-validations' => 'string',
                        'style' => 'height: 24px;margin-left:10px;
                                    padding-top: 2px;padding-bottom: 2px;
                                    padding-left: 5px;padding-right: 2px;
                                    font-size: 12px;color: #111;',
                        'data-validation-error-msg' => 'Please enter voucher id'])
                    ?>
                </div>
            </form>
            <div class="col-md-2">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" onclick="coreWebApp.attv.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
            </div>
        </div>
        <div id="dummydiv" class="row" style="display: none;">
        </div>
        <div id="divattdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="detailsat" class="row" style="display: none;">
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">

    window.coreWebApp.attv = {};
    (function (attv) {
        function getData() {
            $('#brules').html('');
            var res = $('#att_vch_id').val();
            if (typeof res == 'undefined' || res == '') {
                return;
            }
            $.ajax({
                url: '?r=cwf%2Fsys%2Fmain%2Fget-audit-trail',
                dataType: 'json',
                type: 'GET',
                data: {'docid': res, 'reqtime': new Date().getTime()},
                beforeSend: function () {
                    coreWebApp.startloading();
                },
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = resultdata;
                    if (jsonResult['status'] !== 'OK') {
                        coreWebApp.toastmsg('warning', 'Search', jsonResult['status'], false);
                        return;
                    } else {
                        $('#divattdata').show();
                        lnk = '?r=/cwf/sys/main/audittrail&formName=/' + jsonResult['qpRoute'] + '/' + jsonResult['qpForm']
                                + '&formParams={"' + jsonResult['qpKey'] + '":"' + jsonResult['qpid'] + '"}&formUrl=/' + jsonResult['qpRoute'];
                        coreWebApp.rendercontents(lnk, 'detailsat', 'dummydiv','coreWebApp.attv.afterload');
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        attv.GetData = getData;

        function afterload() {
            var htcroot = parseInt($('#content-root').height());
            $('#custom-form').height(htcroot - 5);
            $('#detailsat').height($('#custom-form').height());
            if ($('#dataTables_scrollBody').length !== 0) {
                $('#dataTables_scrollBody').height(htcroot - 165);
                $('#dataTables_scrollBody').width($('#content-root').width() - 30);
                if ($('#thelist2').length != 0) {
                    $('#thelist2').height($('#custom-form').height() - 175);
                    $('#thelist2').width($('#content-root').width() - 60);
                    $('#thelist2').children('tbody').height($('#custom-form').height() - 180);
                }
            }
        }
        attv.afterload = afterload;

    }(window.coreWebApp.attv));
</script>