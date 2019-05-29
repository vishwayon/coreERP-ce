<?php

use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl = '?r=core%2Ftds%2Ftds-challan-info%2Fgetdata';
$purl = '?r=core%2Ftds%2Ftds-challan-info%2Fsetdata';
$view_type_option = array();
$view_type_option[0] = 'Not Updated';
$view_type_option[1] = 'Updated';
$view_type_option[2] = 'All';
$as_on_date = FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
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
            <h3>TDS Challan Info</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="tdschallan" name ="tdschallan" 
                  target="tdsdata" method="GET" action="<?= $viewerurl ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class=" col-md-2 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="view_type_id">Status</label>
                    <?= Html::dropDownList('view_type_id', 'Not Updated', $view_type_option, ['class' => 'form-control', 'id' => 'view_type_id'])
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
                        'name' => 'as_on',
                        'start_date' => $year_begin,
                        'end_date' => $year_end]
                    )
                    ?>
                </div>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="tdsdata();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
                <button class="btn btn-sm btn-default" id="cmdupdatebankreco" style="display: none;"
                        onclick="coreWebApp.setJsonData('<?= $purl ?>', 'POST', 'tdsdata');">
                    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Update         
                </button>
            </div>
        </div>
        <div id="tdsdata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <div id="divbrules" name="divbrules" style="display: none;" class="row">
                <ul id="brules" name="brules" style="color: #a94442;"></ul>
            </div>
            <table id="vch_tran" class="row-border hover tran"  cellspacing="0">
                <thead>
                    <tr>
                        <th>Doc Date</th>
                        <th>Voucher id</th>
                        <th style="text-align: center">Amount</th>
                        <th>Select</th>
                        <th>Challan BSR</th>
                        <th>Challan Serial</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'tdsdata-template', foreach: dt }">
                </tbody>
            </table>
        </div>
        <script id="tdsdata-template" type="text/html">
            <tr> 
                <td data-bind="dateValue: doc_date">
                </td>
                <td data-bind="text: voucher_id">
                </td>
                <td data-bind="numericValue: amt" style="text-align: right">
                </td>
                <td style="text-align: center">
                    <input type="checkbox" data-bind="checked: selected">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: challan_bsr, enable: selected ">
                </td>
                <td class="">
                    <input type="Text" class="col-md-10" 
                           style="padding-left: 5px; padding-right: 5px;"
                           data-bind="value: challan_serial, enable: selected ">
                </td>
            </tr>
            </script>
        </div>
    </div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
    <script type="text/javascript">
        //    applysmartcontrols();
        function tdsdata() {
            var res = {};
            $.each($('#tdschallan').serializeArray(), function () {
                res[this.name] = this.value;
            });
            if (res['as_on'] === '') {
                alert('As On date must be selected.');
                return;
            }
            coreWebApp.getJsonData('tdschallan', 'GET');
            $('#tdsdata').show();
        }
    </script>
