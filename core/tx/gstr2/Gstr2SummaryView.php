<div id="div_gstr2_summ" class="row col-md-12" style="padding: 0;">
    <div class="ctranheader row" style="border-top: 1px solid teal;padding-top: 10px;">
        <h3 class="col-md-3">GSTR2 Summary</h3>
        <h5 class="col-md-4" style="width: auto;margin-right: 10px;">Details of inward supplies of goods or services</h5>
        <input type="hidden" id="gstn_auth" name="gstn_auth" value="<?= ($res->session_exists ? 'true' : 'false') ?>">
        <button id="btn_summ_print" class=" btn col-md-2" data-bind="click: printClick" 
                style="margin-right: 10px;width: auto;">Print Summary</button>
        <button id="btn_summ_file" data-bind="click: core_tx.gstr2.get_gstr2_detail_file" 
                class="btn col-md-2" style="margin-right: 10px; width: auto;">Get JSON File</button>
        <button id="btn_upld_file" data-bind="click: core_tx.gstr2.gstr2_gstn_upload" 
                class="btn col-md-2" style="margin-right: 10px; width: auto;<?= ($res->session_exists ? "" : "display:none;") ?>">Upload to GSTN</button>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <table class="table table-hover table-condensed">
                <tbody>
                    <tr>
                        <td></td>
                        <td>Return Period</td>
                        <td data-bind="text: return_period"></td>
                    </tr>
                    <tr>
                        <td>1]</td>
                        <td>GSTIN</td>
                        <td data-bind="text: gstin"></td>
                    </tr>
                    <tr>
                        <td>2]</td>
                        <td>Name of Registered Person</td>
                        <td data-bind="text: company_name"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>3] Inward Supplies received from Registered Persons - B2B</h4>
            <span style="margin-left: 15px;">Other than supplies attracting Reverse Charge</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Bill Count</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                        <th class="col-md-1">SGST ITC</th>
                        <th class="col-md-1">CGST ITC</th>
                        <th class="col-md-1">IGST ITC</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_b2b_list', foreach: b2b }">

                </tbody>
                <tfoot>
                    <tr data-bind="visible: b2b().length == 0">
                        <td colspan="6">
                            <span>** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('inv_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_itc_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_itc_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_itc_amt_tot', b2b), 2)"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <script type="text/html" id="templ_b2b_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatNumber(inv_count(), 0)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_itc_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_itc_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_itc_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-11">
            <h4>4] Inward Supplies on which tax is to be paid on Reverse Charge - B2C</h4>
            <h5 style="margin-left: 15px; margin-top: 10px;">A1. Reverse Charge u/s 9(3) (From registered Suppliers)</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">SGST Payable</th>
                        <th class="col-md-1">CGST Payable</th>
                        <th class="col-md-1">IGST Payable</th>
                        <th class="col-md-1">SGST ITC</th>
                        <th class="col-md-1">CGST ITC</th>
                        <th class="col-md-1">IGST ITC</th>
                        <th class="col-md-1">Self Inv. #</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_b2c_93_list', foreach: b2c_93_rs }">

                </tbody>
                <tfoot>
                    <tr data-bind="visible: b2c_93_rs().length == 0">
                        <td colspan="6">
                            <span>** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_itc_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_itc_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_itc_amt', b2c_93_rs), 2)"></td>
                        <td style="text-align: right; font-weight: bold"></td>
                    </tr>
                </tfoot>
            </table>
            <h5 style="margin-left: 15px; margin-top: 10px;">A2. Reverse Charge u/s 9(3) (From unregistered Suppliers)</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">SGST Payable</th>
                        <th class="col-md-1">CGST Payable</th>
                        <th class="col-md-1">IGST Payable</th>
                        <th class="col-md-1">SGST ITC</th>
                        <th class="col-md-1">CGST ITC</th>
                        <th class="col-md-1">IGST ITC</th>
                        <th class="col-md-1">Self Inv. #</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_b2c_93_list', foreach: b2c_93 }">

                </tbody>
                <tfoot>
                    <tr data-bind="visible: b2c_93().length == 0">
                        <td colspan="6">
                            <span>** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_itc_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_itc_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_itc_amt', b2c_93), 2)"></td>
                        <td style="text-align: right; font-weight: bold"></td>
                    </tr>
                </tfoot>
            </table>
            <h5 style="margin-left: 15px; margin-top: 10px;">B. Reverse Charge u/s 9(4)</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">SGST Payable</th>
                        <th class="col-md-1">CGST Payable</th>
                        <th class="col-md-1">IGST Payable</th>
                        <th class="col-md-1">SGST ITC</th>
                        <th class="col-md-1">CGST ITC</th>
                        <th class="col-md-1">IGST ITC</th>
                        <th class="col-md-1">Self Inv. #</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_b2c_94_list', foreach: b2c_94 }">

                </tbody>
                <tfoot>
                    <tr data-bind="visible: b2c_94().length == 0">
                        <td colspan="6">
                            <span>** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_itc_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_itc_amt', b2c_94), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_itc_amt', b2c_94), 2)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: b2c_94().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_b2c_93_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: doc_date"></td>
                <td class="col-md-1" data-bind="text: gst_tax_tran_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_itc_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_itc_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_itc_amt(), 2)"></td>
                <td class="col-md-1" data-bind="text: si_tran_id"></td>
            </tr>
        </script>
        <script type="text/html" id="templ_b2c_94_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: doc_date"></td>
                <td class="col-md-1" data-bind="text: gst_tax_tran_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_itc_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_itc_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_itc_amt(), 2)"></td>
                <td class="col-md-1" data-bind="text: si_tran_id"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>5] Import/Capital Goods received from Overseas or SEZ Units - IMP</h4>
            <h5 style="margin-left: 15px; margin-top: 10px;">A. Imports</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">IGST Payable</th>
                        <th class="col-md-1">IGST ITC</th>
                        <th class="col-md-1">Self Inv. #</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_imp_list', foreach: imp_ovs }">

                </tbody>
            </table>
            <span data-bind="visible: imp_ovs().length == 0">** No Transactions during the period **</span>
            <h5 style="margin-left: 15px; margin-top: 10px;">B. Received from SEZ</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">IGST Payable</th>
                        <th class="col-md-1">IGST ITC</th>
                        <th class="col-md-1">Self Inv. #</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_imp_list', foreach: imp_sez }">

                </tbody>
            </table>
            <span data-bind="visible: imp_sez().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_imp_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: doc_date"></td>
                <td class="col-md-1" data-bind="text: gst_tax_tran_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_itc_amt(), 2)"></td>
                <td class="col-md-1" data-bind="text: si_tran_id"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>6] Ammendments to Taxable Outward Supplies (Debit/Credit Notes) - CDN</h4>
            <span style="margin-left: 15px;">Original</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Origin Bill #</th>
                        <th class="col-md-1">Origin Bill Date</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_cdn_list', foreach: cdn }">

                </tbody>
            </table>
            <span data-bind="visible: cdn().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_cdn_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: voucher_id"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatDate(doc_date())"></td>
                <td class="col-md-1" data-bind="text: origin_inv_id"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatDate(origin_inv_date())"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>7] Supplies received from Composition/Exempt/Nil Rated/Non-GST - NIL</h4>
            <h5 style="margin-left: 15px; margin-top: 10px;">A. Exempt Items u/s 9(4) read with 11(1) Notification No.8/2017</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Bill Type</th>
                        <th class="col-md-1">Doc Date</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Taxable Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_nil_rc94_list', foreach: nil_rc94 }">

                </tbody>
                <tfoot>
                    <tr data-bind="visible: nil_rc94().length == 0">
                        <td colspan="6">
                            <span>** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt', nil_rc94), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <h5 style="margin-left: 15px; margin-top: 10px;">B. Other Items</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Supply Type</th>
                        <th class="col-md-1">Composition</th>
                        <th class="col-md-1">Exempted/Nil Rated (local)</th>
                        <th class="col-md-1">Exempted/Nil Rated (Interstate)</th>
                        <th class="col-md-1">Non-GST</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="col-md-1" >Supplies</td>
                        <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil.cp(), 2)"></td>
                        <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil.exemp(), 2)"></td>
                        <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil.exemp_inter(), 2)"></td>
                        <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil.non_gst(), 2)"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script type="text/html" id="templ_nil_list">
            <tr>
                <td class="col-md-1" data-bind="text: supply_type"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(comp_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(exempt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(ngst_amt_tot(), 2)"></td>
            </tr>
        </script>
        <script type="text/html" id="templ_nil_rc94_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: doc_date"></td>
                <td class="col-md-1" data-bind="text: gst_tax_tran_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>8] ISD Credit Received</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
            </table>
            <span>** Currently not available in coreERP **<span>
        </div>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>9] TDS/TCS Credit Received</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
            </table>
            <span>** Currently not available in coreERP **<span>
        </div>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>10] Consolidated Statement of Advances Paid/Adjusted in the current period</h4>
            <h5 style="margin-left: 15px;">10A. Advance paid for Reverse Charge Supplies - TXI</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Supply Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Advance Received</th>
                        <th class="col-md-1">Place of Supply</th>
                        <th class="col-md-1">Rate of Tax</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_txi_list', foreach: txi }">

                </tbody>
            </table>
            <span data-bind="visible: txi().length == 0">** No Transactions during the period **</span>
            <h5 style="margin-left: 15px;">10B. Advance paid in earlier period and adjusted in current period for Reverse Charge Supplies - TXPD</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Supply Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Advance Adjusted</th>
                        <th class="col-md-1">Place of Supply</th>
                        <th class="col-md-1">Rate of Tax</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_txpd_list', foreach: txpd }">

                </tbody>
            </table>
            <span data-bind="visible: txpd().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_txpd_list">
            <tr>
                <td class="col-md-1" data-bind="text: supply_type"></td>
                <td class="col-md-1" data-bind="text: voucher_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" data-bind="text: gst_state_code() + ' - ' + state_name()"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_pcnt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>11] Input Tax Reversal/Reclaim</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
            </table>
            <span>** Feature currently not available in coreERP **</span>
        </div>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>12] Addition/Reduction of amount in output tax for mismatch and other reasons</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
            </table>
            <span>** Feature currently not available in coreERP **<span>
        </div>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>13] HSN Summary of Inward Supplies - HSNSUM</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Sl #</th>
                        <th class="col-md-1">HSN/SAC</th>
                        <th class="col-md-1">Total Value</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_hsnsum_list', foreach: hsnsum }">

                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">
                            <span data-bind="visible: hsnsum().length == 0">** No Transactions during the period **</span>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('inv_amt_tot', hsnsum), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('bt_amt_tot', hsnsum), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('sgst_amt_tot', hsnsum), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('cgst_amt_tot', hsnsum), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2.get_col_total('igst_amt_tot', hsnsum), 2)"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <script type="text/html" id="templ_hsnsum_list">
            <tr>
                <td class="col-md-1" data-bind="text: sl_no"></td>
                <td class="col-md-1" data-bind="text: hsn_sc_code"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
</div>
<script type="text/javascript">
    function printClick() {
        var pwin = window.open('');
        var htmldoc = $('<html></html>');
        var head = $('<head>'+document.head.innerHTML+'</head>');
        htmldoc.append(head);
        // This should be a simple parent div to ensure that it does not take printer page space
        var rptParent = $($('#div_gstr2_summ').html());
        rptParent.css('margin-left', '10px');
        rptParent.find('#btn_summ_print').css('visibility', 'collapse');
        var body = $('<body></body>');
        body.attr('onload', 'pageLoaded()');
        body.append(rptParent);
        htmldoc.append(body);
        var script=pwin.document.createElement('script');
        script.type = 'text/javascript';
        script.text = 'function pageLoaded() { window.print(); window.close(); }';
        htmldoc.append(script);
        pwin.document.write(htmldoc.html());
        pwin.document.close();
        //pwin.close();
    }
</script>