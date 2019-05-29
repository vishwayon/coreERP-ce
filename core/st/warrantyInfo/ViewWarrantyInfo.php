<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Fst%2Fwarranty-info%2Fgetdata';
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Warranty Information</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="wrinfo" name ="wrinfo" 
                  target="wrdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="material_type_id">Material Type</label>
                    <?=
                    Html::input('SmartCombo', 'material_type_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'material_type_id', 'name' => 'material_type_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'material_type_id',
                        'data-displaymember' => 'material_type',
                        'data-namedlookup' => '../core/st/lookups/MaterialType.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select material type'])
                    ?>
                </div>
                <div class=" col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="material_id">Material</label>
                    <?=
                    Html::input('SmartCombo', 'material_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'material_id', 'name' => 'material_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'filterevent' => 'coreWebApp.wrInfo.material_filter',
                        'data-valuemember' => 'material_id',
                        'data-displaymember' => 'material_name',
                        'data-namedlookup' => '../core/st/lookups/Material.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select material'])
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="coreWebApp.wrInfo.GetData();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
            </div>
        </div>
        <div id="wrdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
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
    //create and bind wrInfo namespace
    window.coreWebApp.wrInfo = {};
    (function (wrInfo) {

        function getData() {
            $('#brules').html('');
            var res = $('#wrinfo').serialize();

            // get actual data

            form_method = $('#wrinfo').attr('method');
            form_action = $('#wrinfo').attr('action');
            form_target = $('#wrinfo').attr('target');
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
                    var jsonResult = resultdata;
                    if ($.fn.dataTable.isDataTable('#vch_tran')) {
                        var t = $('#vch_tran').DataTable();
                        t.destroy();
                    }
                    $('#wrdata').show();
                    $('#contents').height($('#content-root').height() * 0.965);
                    var tbl = $('#vch_tran').DataTable({
                        data: jsonResult['jsondata'].wrdata.data,
                        columns: jsonResult['jsondata'].wrdata.columns,
                        deferRender: true,
                        scrollY: coreWebApp.getscrollheight() + 'px',
                        scrollCollapse: true,
                        scroller: true,
                    });
                    $('.dataTables_scrollBody').height(coreWebApp.getscrollheight());
                    $('.dataTables_scrollBody').css('background', 'white');
                    $('.dataTables_scrollBody').css("min-height", ($('.dataTables_scrollBody').height()).toString() + 'px');
                    var l = $('#vch_tran_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                    $('.dataTables_empty').text('No data to display');

                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
        }
        wrInfo.GetData = getData;

        function calTS() {
            $('[data-bind="dateValue: doc_date"]').each(function () {
                var temp = wrInfo.GetTimestamp(this);
                $(this).attr('data-order', temp);
            });
        }
        wrInfo.CalTS = calTS;

        function getTimestamp(ctr) {
            var dateval = $(ctr).val();
            var unfdate = coreWebApp.unformatDate(dateval);
            var ts = new Date(unfdate).getTime();
            return ts;
        }
        wrInfo.GetTimestamp = getTimestamp;

        function material_filter(fltr, dataItem) {
            if (parseInt($('#material_type_id').val()) !== -1) {
                fltr = ' material_type_id = ' + $('#material_type_id').val();
            }
            return fltr;
        }
        wrInfo.material_filter = material_filter;

    }(window.coreWebApp.wrInfo));

    $('#wrinfo').find('input').each(function () {
        if ($(this).hasClass('smartcombo')) {
            coreWebApp.applySmartCombo(this);
        } else if ($(this).hasClass('datetime')) {
            coreWebApp.applyDatepicker(this);
        } else if ($(this).attr('type') == 'decimal') {
            coreWebApp.applyNumber(this);
        }
    });
</script>
