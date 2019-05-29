<?php

use yii\helpers\Html;

$get_url = '?r=core/tx/gst-return/get-gst-detail-data';

?>
<script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/tx/gstr1Detail/gstr1_detail_cc.js') ?>"></script>
<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row cformheader">
            <h3>GSTR1 Detail</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="matsel" name ="matsel" 
                  target="matbal" method="GET" action="<?= $get_url ?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class="col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="gst_ret_id">GST Return</label>
                    <?=
                    Html::input('SmartCombo', 'gst_ret_id', -1, ['class' => 'smartcombo form-control required',
                        'id' => 'gst_ret_id', 'name' => 'gst_ret_id',
                        'data-validation' => 'required',
                        'data-filter' => '',
                        'data-valuemember' => 'gst_ret_id',
                        'data-displaymember' => 'gst_ret_period',
                        'data-namedlookup' => '../core/tx/lookups/Gstr1Lookup.xml',
                        'data-validations' => 'number',
                        'style' => 'padding:0px;', 'notyetsmart' => true,
                        'data-validation-error-msg' => 'Please select return period'])
                    ?>
                </div>
                <div class="col-md-4 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="detail_type">Report Type</label>
                    <select id="detail_type" name="detail_type" class="smartcombo form-control required">
                        <option value="4">4] Taxable Outward Supplies to Registered Persons - B2B</option>
                        <option value="7">7] Taxable Outward Supplies to Unregistered Persons - B2CS</option>
                        <option value="8">8] Nil rated, exempt and non-GST outward Supplies - EXEMP</option>
                    </select>
                </div>
            </form>
            <div class=" col-md-1 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="core_tx.gstr1_detail.get_data();">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
                </button>
            </div>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <button class="btn btn-sm btn-default" id="btn_download_csv" onclick="core_tx.gstr1_detail.get_gstr1_detail_csv();" class="btn col-md-2">Download CSV</button>
            </div>
        </div>
        <div class="row" style="margin-left: 20px;">
            <span id="lbl_gd">Fetching Data. Please wait ...</span>
        </div>
        <div id="detail_data_b2b" class="row" style="margin-left: 20px; margin-bottom: 20px;">
            <div class="col-md-11">
                <h4>4] Taxable Outward Supplies to Registered Persons - B2B</h4>
                <span style="margin-left: 15px;">Excluding Zero rated and deemed exports (item no. 6)</span>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Invoice Dt</th>
                            <th class="col-md-1">Invoice #</th>
                            <th class="col-md-2">Branch</th>
                            <th class="col-md-1">GSTIN</th>
                            <th class="col-md-1">Taxable Value</th>
                            <th class="col-md-1">Total SGST</th>
                            <th class="col-md-1">Total CGST</th>
                            <th class="col-md-1">Total IGST</th>
                            <th class="col-md-1">Total Value</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_b2b_list', foreach: b2b }">

                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="text-align: center; font-weight: bold">Total</td>
                            <td></td>
                            <td style="font-weight: bold"data-bind="text: b2b.length"></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('bt_amt', b2b), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('sgst_amt', b2b), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('cgst_amt', b2b), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('igst_amt', b2b), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total(['bt_amt','sgst_amt','cgst_amt','igst_amt'], b2b), 2)"></td>
                        </tr>
                    </tfoot>
                </table>
                <span data-bind="visible: b2b.length == 0">** No Transactions during the period **</span>
            </div>
            <script type="text/html" id="templ_b2b_list">
                <tr>
                    <td class="col-md-1" data-bind="text: coreWebApp.formatDate(doc_date)"></td>
                    <td class="col-md-1" data-bind="text: voucher_id"></td>
                    <td class="col-md-1" data-bind="text: branch_name"></td>
                    <td class="col-md-1" data-bind="text: customer_gstin"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt, 2)"></td>
                </tr>
            </script>
        </div>
        <div id="detail_data_b2cs" class="row" style="margin-left: 20px; margin-bottom: 20px;">
            <div class="col-md-11">
                <h4>7] Taxable Outward Supplies to Unregistered Persons - B2CS</h4>
                <span style="margin-left: 15px;">(Includes Interstate less than INR 2.5 Lakhs)</span>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Invoice Dt</th>
                            <th class="col-md-1">Invoice #</th>
                            <th class="col-md-2">Branch</th>
                            <th class="col-md-1">Taxable Value</th>
                            <th class="col-md-1">Total SGST</th>
                            <th class="col-md-1">Total CGST</th>
                            <th class="col-md-1">Total IGST</th>
                            <th class="col-md-1">Total Value</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_b2cs_list', foreach: b2cs }">

                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="text-align: center; font-weight: bold">Total</td>
                            <td style="font-weight: bold"data-bind="text: b2cs.length"></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('bt_amt', b2cs), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('sgst_amt', b2cs), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('cgst_amt', b2cs), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('igst_amt', b2cs), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total(['bt_amt','sgst_amt','cgst_amt','igst_amt'], b2cs), 2)"></td>
                        </tr>
                    </tfoot>
                </table>
                <span data-bind="visible: b2cs.length == 0">** No Transactions during the period **</span>
            </div>
            <script type="text/html" id="templ_b2cs_list">
                <tr>
                    <td class="col-md-1" data-bind="text: coreWebApp.formatDate(doc_date)"></td>
                    <td class="col-md-1" data-bind="text: voucher_id"></td>
                    <td class="col-md-1" data-bind="text: branch_name"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt + sgst_amt + cgst_amt + igst_amt, 2)"></td>
                </tr>
            </script>
        </div>
        <div id="detail_data_exemp" class="row" style="margin-left: 20px; margin-bottom: 20px;">
            <div class="col-md-11">
                <h4>8] Nil Rated, exempt and non-GST outward Supplies EXEMP</h4>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Invoice Dt</th>
                            <th class="col-md-1">Invoice #</th>
                            <th class="col-md-2">Branch</th>
                            <th class="col-md-1">Supply Type</th>
                            <th class="col-md-1">GSTIN Status</th>
                            <th class="col-md-1">Nil GST</th>
                            <th class="col-md-1">Exempt</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_exemp_list', foreach: exemp }">

                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="text-align: center; font-weight: bold">Total</td>
                            <td style="font-weight: bold"data-bind="text: exemp.length"></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('nil_amt', exemp), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1_detail.get_col_total('exempt_amt', exemp), 2)"></td>
                        </tr>
                    </tfoot>
                </table>
                <span data-bind="visible: exemp.length == 0">** No Transactions during the period **</span>
            </div>
            <script type="text/html" id="templ_exemp_list">
                <tr>
                    <td class="col-md-1" data-bind="text: coreWebApp.formatDate(doc_date)"></td>
                    <td class="col-md-1" data-bind="text: voucher_id"></td>
                    <td class="col-md-1" data-bind="text: branch_name"></td>
                    <td class="col-md-1" data-bind="text: supply_type"></td>
                    <td class="col-md-1" data-bind="text: customer_gstin"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil_amt, 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(exempt_amt, 2)"></td>
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
    $('[id^="detail_data_"]').hide();
    $('#lbl_gd').hide();
</script>
