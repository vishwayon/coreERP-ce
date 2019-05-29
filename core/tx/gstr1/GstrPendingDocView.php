<?php
/*
 * This view renders the html required for displaying pending docs
 * as part of Pre-Process for GSTR1
 */
?>

<div class="ctranheader" style="border-top: 1px solid teal;">
    <h5>Pending Document Summary</h5>
</div>
<div class="row" style="margin-left: 20px;">
    <div class="col-md-9">
        <h4>1] Documents in Workflow/Not Posted</h4>
        <table class="table table-hover table-condensed">
            <thead>
                <tr>
                    <th class="col-md-2">Document Type</th>
                    <th class="col-md-1">Doc Date</th>
                    <th class="col-md-1">Document #</th>
                    <th class="col-md-1">Before Tax Value</th>
                    <th class="col-md-1">Total Tax</th>
                    <th class="col-md-1">Total Value</th>
                </tr>
            </thead>
            <tbody data-bind="template: { name: 'templ_pending_doc', foreach: pending }">
                
            </tbody>
        </table>
    </div>
    <div class="col-md-3">
        <button data-bind="click: core_tx.gstr1.view_gstr1_summary_click" class="btn btn-sm btn-default">View GSTR1 Summary</button>
    </div>
    <script type="text/html" id="templ_pending_doc">
        <tr>
            <td class="col-md-1" data-bind="text: doc"></td>
            <td class="col-md-1" data-bind="text: coreWebApp.formatDate(doc_date())"></td>
            <td class="col-md-1" data-bind="text: voucher_id"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(tax_amt(), 2)"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(total_amt(), 2)"></td>
        </tr>
    </script>
</div>
<div class="row" style="margin-left: 20px; margin-bottom: 20px;">
    <div class="col-md-9">
        <h4>2] Pending Self Invoice</h4>
        <table class="table table-hover table-condensed">
            <thead>
                <tr>
                    <th class="col-md-2"></th>
                    <th class="col-md-1">Invoice Count</th>
                    <th class="col-md-1">Taxable Value</th>
                    <th class="col-md-1">Total SGST</th>
                    <th class="col-md-1">Total CGST</th>
                    <th class="col-md-1">Total IGST</th>
                    <th class="col-md-1">Total Value</th>
                </tr>
            </thead>
            <tbody data-bind="template: { name: 'templ_si_list', foreach: si }">

            </tbody>
        </table>
        <span data-bind="visible: si().length == 0">** No Transactions during the period **<span>
    </div>
    <script type="text/html" id="templ_si_list">
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



