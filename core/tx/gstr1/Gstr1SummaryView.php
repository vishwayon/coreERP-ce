<div id="div_gstr1_json_errors" class="row col-md-12" style="padding: 0; display: none;">
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4 style="color: red;">GSTR1 - JSON Validation Errors</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">GSTIN</th>
                        <th class="col-md-1">Invoice #</th>
                        <th class="col-md-1">Date</th>
                        <th class="col-md-1">Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_error_list', foreach: invalidctin }">

                </tbody>
            </table>
        </div>
        <script type="text/html" id="templ_error_list">
            <tr>
                <td class="col-md-1" style="font-weight: bold;" data-bind="text: ctin" colspan="4"></td>
            </tr>
            <!-- ko foreach: inv -->
                <tr>
                    <td></td>
                    <td data-bind="text: inum"></td>
                    <td data-bind="text: idt"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(val(), 2)"></td>
                </tr>
            <!-- /ko -->
        </script>
    </div>
</div>
<div id="div_gstr1_summ" class="row col-md-12" style="padding: 0;">
    <div class="ctranheader row" style="border-top: 1px solid teal;padding-top: 10px;">
        <h3 class="col-md-3">GSTR1 Summary</h3>
        <h5 class="col-md-4">Details of outward supplies of goods or services</h5>
        <button id="btn_summ_print" class="btn btn-sm btn-default" data-bind="click: printClick" style="margin-right: 15px;">Print Summary</button>
        <button id="btn_summ_file" data-bind="click: core_tx.gstr1.get_gstr1_detail_file" class="btn btn-sm btn-default">Get JSON File</button>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <table class="table table-hover table-condensed">
                <tbody>
                    <tr>
                        <td></td>
                        <td>Return Period</td>
                        <td data-bind="text: return_period">Please Wait. Fetching Data ...</td>
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
                    <tr>
                        <td>3]</td>
                        <td>Current Year Turnover upto previous month</td>
                        <td data-bind="text: cy_pm_turnover"></td>
                    </tr>
                </tbody>
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
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>4] Taxable Outward Supplies to Registered Persons - B2B</h4>
            <span style="margin-left: 15px;">Excluding Zero rated and deemed exports (item no. 6)</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Invoice Type</th>
                        <th class="col-md-1">Invoice Count</th>
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
                        <td style="font-weight: bold"data-bind="text: core_tx.gstr1.get_col_total('inv_count', b2b)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', b2b), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('inv_amt_tot', b2b), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: b2b().length == 0">** No Transactions during the period **</span>
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
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>5] Taxable Outward Interstate Supplies to unregistered Persons in excess of INR 2.5 Lakhs - B2CL</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Invoice Type</th>
                        <th class="col-md-1">Invoice Count</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_b2cl_list', foreach: b2cl }">

                </tbody>
            </table>
            <span data-bind="visible: b2cl().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_b2cl_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatNumber(inv_count(), 0)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>6] Zero rated Supplies and Deemed Exports - EXP</h4>
            <h5 style="margin-left: 15px; margin-top: 10px;">A. Exports</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Invoice Type</th>
                        <th class="col-md-1">Invoice Count</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_exp_list', foreach: exp_ex }">

                </tbody>
            </table>
            <span data-bind="visible: exp_ex().length == 0">** No Transactions during the period **</span>
            <h5 style="margin-left: 15px; margin-top: 10px;">B. Supplies made to SEZ unit or SEZ Developer</h5>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Invoice Type</th>
                        <th class="col-md-1">Invoice Count</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_exp_list', foreach: exp_sez }">

                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: center; font-weight: bold">Total</td>
                        <td style="font-weight: bold"data-bind="text: core_tx.gstr1.get_col_total('inv_count', exp_sez)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', exp_sez), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', exp_sez), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('inv_amt_tot', exp_sez), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: exp_sez().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_exp_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatNumber(inv_count(), 0)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-9">
            <h4>7] Taxable Outward Supplies to Unregistered Persons - B2CS</h4>
            <span style="margin-left: 15px;">(Includes Interstate less than INR 2.5 Lakhs)</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Invoice Type</th>
                        <th class="col-md-1">Invoice Count</th>
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
                        <td style="font-weight: bold"data-bind="text: core_tx.gstr1.get_col_total('inv_count', b2cs)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', b2cs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', b2cs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', b2cs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', b2cs), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('inv_amt_tot', b2cs), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: b2cs().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_b2cs_list">
            <tr>
                <td class="col-md-1" data-bind="text: doc"></td>
                <td class="col-md-1" data-bind="text: coreWebApp.formatNumber(inv_count(), 0)"></td>
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
            <h4>8] Nil rated, exempt and non-GST outward Supplies - EXEMP</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Supply Type</th>
                        <th class="col-md-1">Recipient</th>
                        <th class="col-md-1">Nil Rated</th>
                        <th class="col-md-1">Exempted</th>
                        <th class="col-md-1">Non-GST</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_exemp_list', foreach: exemp }">

                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: center; font-weight: bold">Total Nil/Exempt</td>
                        <td style="font-weight: bold"data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total(['nil_amt_tot','exempt_amt_tot'], exemp), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: exemp().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_exemp_list">
            <tr>
                <td class="col-md-1" data-bind="text: supply_type"></td>
                <td class="col-md-1" data-bind="text: gstin_status"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(nil_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(exempt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" >N.A.</td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>9] Ammendments to Taxable Outward Supplies (Debit/Credit Notes) - CDN</h4>
            <span style="margin-left: 15px;">Relating to Supplies made to registered persons</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Origin Inv #</th>
                        <th class="col-md-1">Origin Inv Date</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_cdnr_list', foreach: cdnr }">

                </tbody>
                <tfoot>
                    <tr>
                        <td></td><td></td><td></td><td></td>
                        <td style="text-align: center; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', cdnr), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', cdnr), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', cdnr), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', cdnr), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('inv_amt_tot', cdnr), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: cdnr().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_cdnr_list">
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
            <h4>10] Ammendments to Taxable Outward Supplies (Debit/Credit Notes) to Unregistered Persons - CDNUR</h4>
            <span style="margin-left: 15ps;">Relating to Supplies made in previous periods</span>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-1">Document #</th>
                        <th class="col-md-1">Document Date</th>
                        <th class="col-md-1">Origin Inv #</th>
                        <th class="col-md-1">Origin Inv Date</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                        <th class="col-md-1">Total Value</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_cdnur_list', foreach: cdnur }">

                </tbody>
            </table>
            <span data-bind="visible: cdnur().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_cdnur_list">
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
        <div class="col-md-12">
            <h4>11] Consolidated Statement of Advances Received/Adjusted in the current period</h4>
            <h5 style="margin-left: 15px;">11A. Advance received in current period for which Invoice has not been issued</h5>
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
                <tbody data-bind="template: { name: 'templ_at_list', foreach: at }">

                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', at), 2)"></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', at), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', at), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', at), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: at().length == 0">** No Transactions during the period **</span>
            <h5 style="margin-left: 15px;">11B. Advance received in earlier period and adjusted in current period</h5>
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
                <tbody data-bind="template: { name: 'templ_atadj_list', foreach: atadj }">

                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_settl_amt', atadj), 2)"></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', atadj), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', atadj), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', atadj), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: atadj().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_at_list">
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
        <script type="text/html" id="templ_atadj_list">
            <tr>
                <td class="col-md-1" data-bind="text: supply_type"></td>
                <td class="col-md-1" data-bind="text: voucher_id"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_settl_amt(), 2)"></td>
                <td class="col-md-1" data-bind="text: gst_state_code() + ' - ' + state_name()"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_pcnt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_settl_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_settl_amt(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_settl_amt(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>12] HSN Summary of Outward Supplies</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Sl #</th>
                        <th class="col-md-1">HSN/SAC</th>
                        <th class="col-md-1">UoM</th>
                        <th class="col-md-1">Qty</th>
                        <th class="col-md-1">Taxable Value</th>
                        <th class="col-md-1">Total SGST</th>
                        <th class="col-md-1">Total CGST</th>
                        <th class="col-md-1">Total IGST</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_hsn_list', foreach: hsn }">

                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align: right; font-weight: bold">Total</td>
                        <td style="text-align: right; font-weight: bold"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('bt_amt_tot', hsn), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('sgst_amt_tot', hsn), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('cgst_amt_tot', hsn), 2)"></td>
                        <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr1.get_col_total('igst_amt_tot', hsn), 2)"></td>
                    </tr>
                </tfoot>
            </table>
            <span data-bind="visible: hsn().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="templ_hsn_list">
            <tr>
                <td class="col-md-1" data-bind="text: sl_no"></td>
                <td class="col-md-1" data-bind="text: hsn_sc_code"></td>
                <td class="col-md-1" data-bind="text: hsn_sc_uom"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(hsn_qty_tot(), 3)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(sgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(cgst_amt_tot(), 2)"></td>
                <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(igst_amt_tot(), 2)"></td>
            </tr>
        </script>
    </div>
    <div class="row" style="margin-left: 20px; margin-bottom: 20px;">
        <div class="col-md-12">
            <h4>13] Documents Issued During The Tax Period</h4>
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Sl #</th>
                        <th class="col-md-2">Document Type</th>
                        <th class="col-md-2">Sl From</th>
                        <th class="col-md-2">Sl To</th>
                        <th class="col-md-1">Total Number</th>
                        <th class="col-md-1">Cancelled</th>
                        <th class="col-md-1">Net Issued</th>
                </thead>
                <tbody data-bind="template: { name: 'doc_issue_list', foreach: doc_issue }">

                </tbody>
            </table>
            <span data-bind="visible: doc_issue().length == 0">** No Transactions during the period **</span>
        </div>
        <script type="text/html" id="doc_issue_list">
            <tr>
                <td class="col-md-1" data-bind="text: sl_no"></td>
                <td class="col-md-1" data-bind="text: doc_type"></td>
                <td colspan="5">
                    <table class="table table-hover table-condensed">
                        <tbody data-bind="template: { name: 'doc_list_item', foreach: doc_list }">

                        </tbody>
                    </table>
                </td>
            </tr>
        </script>
        <script type="text/html" id="doc_list_item">
            <tr>
                <td class="col-md-2" data-bind="text: doc_min"></td>
                <td class="col-md-2" data-bind="text: doc_max"></td>
                <td class="col-md-1" style="text-align: center;" data-bind="text: doc_count"></td>
                <td class="col-md-1" style="text-align: center;" data-bind="text: cancelled">N.A.</td>
                <td class="col-md-1" style="text-align: center;" data-bind="text: doc_count() + cancelled()"></td>
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
        var rptParent = $($('#div_gstr1_summ').html());
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