<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
$startdate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
$year_begin = date_format($startdate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());
$enddate = \DateTime::createFromFormat('Y-m-d|', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
$year_end = date_format($enddate, \app\cwf\vsla\utils\FormatHelper::GetDateFormatForPHP());


$get_url = '?r=core/st/bal-update-util/get';
$post_url = '?r=core/st/bal-update-util/update';


?>
<script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/st/balUpdateUtil/bu_util_cc.js') ?>"></script>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Update Material Opening Balance</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="matsel" name ="matsel" 
                  target="matbal" method="GET" action="<?= $get_url ?>" style="margin-left: 10px;">
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
                    <label class="control-label" for="stock_location_id">Stock Location</label>
                    <?=
                    Html::input('SmartCombo', 'stock_location_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'stock_location_id', 'name' => 'stock_location_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'stock_location_id',
                        'data-displaymember' => 'stock_location_name',
                        'data-namedlookup' => '../core/st/lookups/StockLocation.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select Stock Location'])
                    ?>
                </div>
                <div class=" col-md-2 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="as_on">As On</label>
                    <?=
                    Html::input('Date', 'as_on', $as_on_date, ['class' => ' datetime form-control required',
                        'type' => 'Text',
                        'data-validation-format' => $form_date_format,
                        'data-validation' => 'date',
                        'data-validation-error-msg' => 'As on Date is required.',
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
                        onclick="core_st.bu_util.get_data();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdate" style="display: none;"
                        onclick="core_st.bu_util.post_data();">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div class="col-md-12 row" id="filebuttons" style="display: none;">
            <button class="btn btn-sm btn-default col-md-2" id="cmddownload"
                onclick="core_st.bu_util.file_download();">
                <span class="glyphicon glyphicon-download" aria-hidden="true"></span> Download         
            </button>
            <form id="upload-form" name="upload-form" class="col-md-10">
                <input type="file" id="fupload" name="fupload" class="btn btn-sm btn-default col-md-6"
                       accept=".csv"/>
                <a class="btn btn-sm btn-default col-md-3" style="margin-left: 20px;" onclick="core_st.bu_util.file_upload();">
                    Populate Data From File
                </a>
            </form>
        </div>
        <div id="matbal" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran" style="display: block;" cellspacing="0">
                <thead>
                    <tr>
                        <th>Stock Type</th>
                        <th style="width: 200px" >Stock Item</th>
                        <th>Op. Bal.</th>
                        <th>Receipts</th>
                        <th>Issues</th>
                        <th>Cl. Bal.</th>
                        <th>Revise</th>
                        <th>Revised Cl. Bal.</th>
                        <th>Revised Op. Bal.</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'tmpl-matbal', foreach: matbal}">
                </tbody>
            </table>
        </div>
    </div>
    <script id="tmpl-matbal" type="text/html">
        <tr> 
            <td data-bind="text: material_type">
            </td>
            <td data-bind="html: material_name" style="max-width: 250px;">
            </td>
            <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(op_bal(), 3)">
            </td>
            <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(receipts(), 3)">
            </td>
            <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(issues(), 3)">
            </td>
            <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cl_bal(), 3)">
            </td>
            <td style="text-align: center">
                <input type="checkbox" data-bind="checked: revise">
            </td>
            <td style="text-align: right;" >
                <input id="revised_cl_bal" type="Text" class="col-md-10" 
                       style="padding-left: 5px; padding-right: 5px;" scale="3"
                       data-bind="numericValue: revised_cl_bal, enable: revise, visible: revise">
            </td>
            <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(revised_op_bal(), 3), 
                    style: { color:  parseFloat(revised_op_bal()) < 0 ? 'red' : (parseFloat(revised_op_bal()) == 0 ? 'black' : 'green') },
                    visible: revise">
            </td>
        </tr>
        </script>
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
