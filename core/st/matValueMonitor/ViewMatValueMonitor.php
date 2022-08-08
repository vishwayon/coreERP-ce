<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());

?>
<script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/st/matValueMonitor/mat_val_mon_cc.js') ?>"></script>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Stock Valuation Monitor</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="matsel" name ="matsel" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class="col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="report_type">Report Type</label>
                    <?=Html::dropDownList('report_type', 1, [
                            1 => 'EOD Negative Stock',
                            2 => 'WAC Co-variance'
                        ], ['id' => 'report_type', 'class' => 'form-control']);
                    ?>
                </div>
                <div class="col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="material_type_id">Material Type</label>
                    <?=
                    Html::input('SmartCombo', 'material_type_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'material_type_id', 'name' => 'material_type_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'material_type_id',
                        'data-displaymember' => 'material_type',
                        'data-namedlookup' => '@app/core/st/lookups/MaterialType.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select material type'])
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="core_st.mat_val_mon.get_data();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
            </div>
        </div>
        <div id="div-negstock" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="negstock" class="row-border hover tran" style="display: block;" cellspacing="0">
                <thead>
                    <tr>
                        <th>Stock Type</th>
                        <th>Stock Code</th>
                        <th style="width: 300px" >Stock Item</th>
                        <th>Txn. Date</th>
                        <th>Negative Balance</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'tmpl-negstock', foreach: negstock}">
                </tbody>
            </table>
            <script id="tmpl-negstock" type="text/html">
                <tr> 
                    <td data-bind="text: material_type">
                    </td>
                    <td data-bind="text: material_code" style="max-width: 100px;">
                    </td>
                    <td data-bind="html: material_name" style="max-width: 300px;">
                    </td>
                    <td data-bind="text: coreWebApp.formatDate(txn_date())">
                    </td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(neg_bal(), 3)">
                    </td>
                </tr>
            </script>
        </div>
        <div id="div-waccv" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <table id="waccv" class="row-border hover tran" style="display: block;" cellspacing="0">
                <thead>
                    <tr>
                        <th>Stock Type</th>
                        <th>Stock Code</th>
                        <th style="width: 300px" >Stock Item</th>
                        <th>Receipts</th>
                        <th>Issues</th>
                        <th>Avg. Rate</th>
                        <th>SL Avg. Rate</th>
                        <th>Std-dev.</th>
                        <th>Co-var.</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'tmpl-waccv', foreach: waccv}">
                </tbody>
            </table>
            <script id="tmpl-waccv" type="text/html">
                <tr> 
                    <td data-bind="text: material_type"></td>
                    <td data-bind="text: material_code" style="max-width: 100px;"></td>
                    <td data-bind="html: material_name" style="max-width: 300px;"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(received_qty(), 3)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(issued_qty(), 3)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(unit_rate_lc(), 3)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(unit_rate_sl(), 3)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(wac_stddev(), 3)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(wac_cv(), 3)"></td>
                </tr>
            </script>
        </div>
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">

</div>
<script type="text/javascript">
    $('#matsel').find('input').each(function () {
    if ($(this).hasClass('smartcombo')) {
        coreWebApp.applySmartCombo(this);
    } else if ($(this).hasClass('datetime')) {
        coreWebApp.applyDatepicker(this);
    } else if ($(this).attr('type') == 'decimal') {
        coreWebApp.applyNumber(this);
    }
});
</script>
