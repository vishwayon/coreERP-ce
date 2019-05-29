<div id="rptRoot" style="margin: 5px; border-top: 1px solid gray;">
    <ul class="nav nav-tabs">
        <li class="active"><a data-toggle="tab" href="#tab-match">Pending Reconciliation</a></li>
        <li ><a data-toggle="tab" href="#tab-saved-reco">Saved Reconciliation</a></li>
    </ul>

    <div class="tab-content">
        <div id="tab-match" class="tab-pane active">
            <div class="row" >
                <div class="row">
                    <div class="col-md-6"><h5 style="color: teal;">GSTR 2A Reconciliation</h5></div>
                    <div class="col-md-2 checkbox"><input id="chkmtchonly" type="checkbox" data-bind="checked: matched_only">Matched Only</input></div>
                    <div class="col-md-2 checkbox" style="margin-top: 10px;"><input id="chkunmtchonly" type="checkbox" data-bind="checked: unmatched_only">Unmatched Only</input></div>
                </div>
                <div class="row">
                    <table class="table table-hover table-condensed">
                        <thead>
                            <tr>
                                <th style="width: 20pt;"></th>
                                <th class="col-md-1">Origin</th>
                                <th class="col-md-2">Document #</th>
                                <th class="col-md-1">Document Dt</th>
                                <th class="col-md-2">Bill #</th>
                                <th class="col-md-1">Bill Dt</th>
                                <th class="col-md-2">2A Bill #</th>
                                <th class="col-md-1">2A Bill Dt</th>
                                <th class="col-md-1">Bill Amt</th>
                                <th class="col-md-1">Taxable Amt</th>
                                <th class="col-md-1">Tax Amt</th>
                                <th class="col-md-1">2A Taxable Amt</th>
                                <th class="col-md-1">2A Tax Amt</th>
                                <th class="col-md-2">Status</th>
                            </tr>
                        </thead>
                        <tbody data-bind="template: { name: 'templ_gstr2a_match', foreach: reco_data }">

                        </tbody>
                        <tfoot>
                            <tr data-bind="visible: reco_data().length == 0">
                                <td colspan="6">
                                    <span>** No Transactions to display **</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <script type="text/html" id="templ_gstr2a_match">
                <tr>
                    <td colspan="3" data-bind="text: ctin" style="font-weight: bold"></td>
                    <td colspan="3" data-bind="text: supplier" style="font-weight: bold"></td>
                </tr>
                <!-- ko foreach: matched -->
                <tr row-type="matched">
                    <td />
                    <td>Purch. Reg</td>
                    <td data-bind="text: prg_bill.voucher_id"></td>
                    <td data-bind="text: prg_bill.doc_date"></td>
                    <td data-bind="text: prg_bill.bill_no"></td>
                    <td data-bind="text: prg_bill.bill_date"></td>
                    <td data-bind="text: gstr2a_bill.bill_no"></td>
                    <td data-bind="text: gstr2a_bill.bill_dt"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(prg_bill.bt_amt() + prg_bill.gst_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(prg_bill.bt_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(prg_bill.gst_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gstr2a_bill.bill_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gstr2a_bill.gst_amt(), 2)"></td>
                    <td style="text-align: center;">Matched</td>
                </tr>
                <!-- /ko -->
                <!-- ko foreach: prg_missed -->
                <tr row-type="prg_missed">
                    <td />
                    <td>Purch. Reg</td>
                    <td data-bind="text: voucher_id"></td>
                    <td data-bind="text: doc_date"></td>
                    <td data-bind="text: bill_no"></td>
                    <td data-bind="text: bill_date"></td>
                    <td />
                    <td />
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt()+gst_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bt_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_amt(), 2)"></td>
                    <td />
                    <td />
                    <td style="text-align: center;"><a href="#" data-bind="click: core_tx.gstr2aReco.user_match_click">Unmatched</a></td>
                </tr>
                <!-- /ko -->
                <!-- ko foreach: gstr2a_missed -->
                <tr row-type="gstr2a_missed">
                    <td />
                    <td>GSTR2a</td>
                    <td />
                    <td />
                    <td />
                    <td />
                    <td data-bind="text: bill_no"></td>
                    <td data-bind="text: bill_dt"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bill_amt(), 2)"></td>
                    <td />
                    <td />
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(base_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_amt(), 2)"></td>
                    <td style="text-align: center;">UM</td>
                </tr>
                <!-- /ko -->
            </script>
        </div>
        <script type="text/html" id="dialog-match-tmpl">
            <div class="row">
                <div id="dialog-match" class="col-md-12">
                    <div class="row col-md-12">
                        <h3 data-bind="text: supplier"></h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <span data-bind="text: 'Doc: ' + match_for.voucher_id() + ' dt. ' + match_for.doc_date() + ' Bill: ' + match_for.bill_no() + ' dt. ' + match_for.bill_date()"></span>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 10px;">
                        <div class="col-md-7"></div>
                        <div class="col-md-5">
                            <span data-bind="html: 'Amt: <b>' + coreWebApp.formatNumber(match_for.bt_amt(), 2) + '</b> GST: <b>' + coreWebApp.formatNumber(match_for.gst_amt(), 2) + '</b>'"></span>
                        </div>
                    </div>
                    <div class="row col-md-12" style="margin-top: 10px; max-height: 200px; overflow-x: auto;">
                        <table class="table table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Bill No</th>
                                    <th>Bill Dt</th>
                                    <th>Bill Amt</th>
                                    <th>GST Amt</th>
                                </tr>
                            </thead>
                            <tbody data-bind="template: { name: 'gstr2a-m-tmpl', foreach: gstr2a_missed }">

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </script>
        <script type="text/html" id="gstr2a-m-tmpl">
            <tr>
                <td style="text-align: center;"><input type="checkbox" data-bind="checked: select"></td>
                <td data-bind="text: bill_no"></td>
                <td data-bind="text: bill_dt"></td>
                <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(base_amt(), 2)"></td>
                <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_amt(), 2)"></td>
            </tr>
        </script>
        
        <div id="tab-saved-reco" class="row tab-pane fade">
            <div class="row">
                <h3>Saved Reconciliation</h3>
            </div>
            <div class="row">
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th style="width: 20pt;"></th>
                            <th class="col-md-2">Document #</th>
                            <th class="col-md-1">Document Dt</th>
                            <th class="col-md-2">Bill #</th>
                            <th class="col-md-1">Bill Dt</th>
                            <th class="col-md-1">Bill Amt</th>
                            <th class="col-md-1">Taxable Amt</th>
                            <th class="col-md-1">Tax Amt</th>
                            <th class="col-md-2">Matched By</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_gstr2a_saved', foreach: saved_data }">

                    </tbody>
                    <tfoot>
                        <tr data-bind="visible: saved_data().length == 0">
                            <td colspan="6">
                                <span>** No Transactions to display **</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <script type="text/html" id="templ_gstr2a_saved">
                <tr>
                    <td colspan="3" data-bind="text: supp_gstin" style="font-weight: bold"></td>
                    <td colspan="3" data-bind="text: supplier" style="font-weight: bold"></td>
                </tr>
                <!-- ko foreach: bill_data -->
                    <tr >
                        <td />
                        <td data-bind="text: voucher_id"></td>
                        <td data-bind="text: doc_date"></td>
                        <td data-bind="text: bill_no"></td>
                        <td data-bind="text: bill_dt"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(base_amt() + gst_amt(), 2)"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(base_amt(), 2)"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gst_amt(), 2)"></td>
                        <!-- ko if: match_by()=='U' -->
                        <td style="text-align: center;"><a href="#" data-bind="click: core_tx.gstr2aReco.user_unmatch_click,text:match_by"></a></td>
                        <!-- /ko -->
                        <!-- ko ifnot: match_by()=='U' -->
                        <td style="text-align: center;" data-bind="text: match_by"></td>
                        <!-- /ko -->
                    </tr>
                <!-- /ko -->
            </script>
        </div>
    </div>
</div>
