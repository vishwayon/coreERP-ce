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
            <tbody data-bind="template: { name: 'templ_pending_doc', foreach: dt_pending_doc }">
                
            </tbody>
        </table>
    </div>
    <div class="col-md-3">
        <button data-bind="click: core_tx.gstr2.view_gstr2_summary_click" class="btn simple-button">View GSTR2 Summary</button>
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



