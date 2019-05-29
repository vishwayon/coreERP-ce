<?php
/* 
 * This view is a partial view rendered to the client
 * to display EOD Closure data
 */
?>

<div id="div_eod" class="row col-md-12" data-bind="if: show_eod_data">
    <div class="ctranheader" style="border-top: 1px solid teal;">
        <h5>EOD Summary</h5>
    </div>
    <div class="row" style="margin-left: 20px;">
        <div class="col-md-6">
            <table class="table table-hover table-condensed">
                <tbody>
                    <thead>
                        <tr>
                            <th class="col-md-1"></th>
                            <th class="col-md-1"></th>
                            <th class="col-md-1"></th>
                        </tr>
                    </thead>
                    <tr>
                        <td>Company</td>
                        <td data-bind="text: eod_data.company"></td>
                    </tr>
                    <tr>
                        <td>Terminal</td>
                        <td data-bind="text: eod_data.terminal"></td>
                    </tr>
                    <tr>
                        <td>Txn. Date</th>
                        <td data-bind="text: eod_data.tday_date"></td>
                    </tr>
                    <tr>
                        <td>User</th>
                        <td data-bind="text: eod_data.full_user_name"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <div class="row">
                <button id="btn_eod_print" class=" btn" style="margin:25px 15px 0 0; float:left;" 
                    data-bind="click: printClick">Print Summary</button>
            </div>
            <div class="row">
                <button id="btn_eod_end" class=" btn btn-warning" style="margin:25px 15px 0 0; float:left;" 
                    data-bind="click: core_pos.tday_eod_start_handover ">Close Day for Handover</button>
            </div>
        </div>
    </div>
    
    
    <div class="row" style="margin-left: 20px;">
        <div class="col-md-12 ctranheader">
            <h5>Total Sales for the day</h5>
        </div>
        <div class="row">
            <div class="col-md-8">
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Txn. Type</th>
                            <th class="col-md-1">Amount</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_sale_detail', foreach: eod_data.dtSaleDetail }">

                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="col-md-1"><text style="font-size: 16px; font-weight: bold;">Net Sales for the day</text></td>
                            <td></td>
                            <td class="col-md-1" style="text-align: right; font-size: 16px; font-weight: bold;" data-bind="text: coreWebApp.formatNumber(eod_data.inv_amt(), 2)"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="col-md-10 ctranheader">
            <h5>Settlements</h5>
        </div>
        <div class="col-md-8">
            <table class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Type</th>
                        <th class="col-md-2">Settled To</th>
                        <th class="col-md-1">Amount</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'templ_settle', foreach: eod_data.dtSettle }">
                    
                </tbody>
                <tfoot>
                        <tr>
                            <td class="col-md-1"><text style="font-size: 16px; font-weight: bold;">Net Collection</text></td>
                            <td></td>
                            <td></td>
                            <td class="col-md-1" style="text-align: right; font-size: 16px; font-weight: bold;" data-bind="text: coreWebApp.formatNumber(eod_data.settle_amt(), 2)"></td>
                        </tr>
                    </tfoot>
            </table>
        </div>
        <div class="col-md-12 ctranheader">
            <h5>Pending Invoices(s)</h5>
        </div>
        <div class="col-md-8">
            <table  class="table table-hover table-condensed">
                <thead>
                    <tr>
                        <th class="col-md-1">Invoice #</th>
                        <th class="col-md-2">Date</th>
                        <th class="col-md-1">Invoice Amt</th>
                    </tr>
                </thead>
                <tbody data-bind="template: { name: 'pending_doc', foreach: eod_data.dtPendingDoc }">
                    
                </tbody>
            </table>
        </div>
    </div>
    <script id="templ_sale_detail" type="text/html">
        <tr>
            <td class="col-md-1" data-bind="text: txn_type"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt(), 2)"></td>
        </tr>
    </script>
    <script id="templ_settle" type="text/html">
        <tr>
            <td class="col-md-1" data-bind="text: settle_type"></td>
            <td class="col-md-3" data-bind="text: settle_desc"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(settle_amt(), 2)"></td>
        </tr>
    </script>
    <script id="pending_doc" type="text/html">
        <tr>
            <td class="col-md-1" data-bind="text: inv_id"></td>
            <td class="col-md-3" data-bind="text: doc_date"></td>
            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_amt(), 2)"></td>
            
        </tr>
    </script>
    <script type="text/javascript">
        function printClick() {
            var pwin = window.open('');
            var htmldoc = $('<html></html>');
            var head = $('<head>'+document.head.innerHTML+'</head>');
            htmldoc.append(head);
            // This should be a simple parent div to ensure that it does not take printer page space
            var rptParent = $($('#div_eod').html());
            rptParent.attr('margin-left', '40px');
            rptParent.find('#btn_eod_end').css('visibility', 'collapse');
            rptParent.find('#btn_eod_print').css('visibility', 'collapse');
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
</div>

