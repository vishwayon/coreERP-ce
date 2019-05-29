<div id="rptRoot" style="margin: 5px; border-top: 1px solid gray;">
    <form>
        <div class="row">
            <div>
                <h5 style="color: teal;" data-bind="text: 'GSTR 2A Matched (' + reco_data.gstr2a_match().length + ')'">GSTR 2A Matched</h5>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Supplier GSTIN</th>
                            <th class="col-md-2">Supplier</th>
                            <th class="col-md-1">Bill Date</th>
                            <th class="col-md-1">Bill #</th>
                            <th class="col-md-1">Document #</th>
                            <th class="col-md-1">Invoice Value</th>
                            <th class="col-md-1">Taxable Value</th>
                            <th class="col-md-1">Tax Amount</th>
                            <th class="col-md-1">ITC Amount</th>
                            <th class="col-sm-1">CFS</th>
                            <th class="col-sm-1">Flag</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_gstr2a_match', foreach: reco_data.gstr2a_match }">

                    </tbody>
                    <tfoot>
                        <tr data-bind="visible: reco_data.gstr2a_match().length == 0">
                            <td colspan="6">
                                <span>** No Transactions to display **</span>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold">Total</td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_gstr2a_match_inv_total(reco_data.gstr2a_match), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_gstr2a_match_bt_total(reco_data.gstr2a_match), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_gstr2a_match_inv_tax_total(reco_data.gstr2a_match), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_gstr2a_match_itc_total(reco_data.gstr2a_match), 2)"></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <script type="text/html" id="templ_gstr2a_match">
                <tr>
                    <td class="col-md-1" data-bind="text: gstin"></td>
                    <td class="col-md-2" data-bind="text: supplier"></td>
                    <td class="col-md-1" data-bind="text: gstr2a.idt"></td>
                    <td class="col-md-1" data-bind="text: gstr2a.inum"></td>
                    <td class="col-md-1" data-bind="text: b2b.voucher_id"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(gstr2a.val(), 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.taxable_val(gstr2a), 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.tax_total(gstr2a), 2)"></td>
                    <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(b2b.itc_amt(), 2)"></td>
                    <td class="col-sm-1" style="text-align: center;" data-bind="text: gstr2a.cfs"></td>
                    <td class="col-sm-1" style="text-align: center;" data-bind="text: gstr2a.flag"></td>
                </tr>
            </script>
        </div>
        <div class="row">
            <div>
                <h5 style="color: teal;" data-bind="text: 'GSTR 2A Unmatched (' + reco_data.gstr2a_unmatched().length + ' Supplier(s))'">GSTR 2A Unmatched</h5>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Source</th>
                            <th class="col-md-1">Bill Date</th>
                            <th class="col-md-1">Bill #</th>
                            <th class="col-md-1">Doc. Date</th>
                            <th class="col-md-1">Document #</th>
                            <th class="col-md-1"></th>
                            <th class="col-md-1">Invoice Value</th>
                            <th class="col-md-1">Tax Amount</th>
                            <th class="col-md-1">ITC Amount</th>
                        </tr>
                        
                    </thead>
                    <tbody data-bind="template: { name: 'templ_gstr2a_unmatched', foreach: reco_data.gstr2a_unmatched }">

                    </tbody>
                    <tfoot>
                        <tr data-bind="visible: reco_data.gstr2a_unmatched().length == 0">
                            <td colspan="6">
                                <span>** No Transactions to display **</span>
                            </td>
                        </tr>
                         <!--<tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold">Total</td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_inv_total(reco_data.gstr2a_unmatched), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_inv_tax_total(reco_data.gstr2a_unmatched), 2)"></td>
                        </tr>-->
                    </tfoot>
                </table>
            </div>
            <script type="text/html" id="templ_gstr2a_unmatched">
                <tr>
                    <td style="font-weight: bold" data-bind="text: ctin"></td>
                    <td style="font-weight: bold" data-bind="text: supplier" colspan="4"></td>
                    <td style="text-align: center;"><a class="btn btn-sm" data-bind="click: core_tx.gstr2aReco.manual_match_click">Match</a></td>
                </tr>
                <!-- ko foreach: unmatched_inv2a -->
                    <tr>
                        <td>GSTR2A</td>
                        <td data-bind="text: idt"></td>
                        <td data-bind="text: inum"></td>
                        <td></td>
                        <td data-bind="text: voucher_id"></td>
                        <td style="text-align: center;"><input type="checkbox" data-bind="checked: select, visible: voucher_id() == ''" style="margin: 3px 3px 0 0;"/></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(val(), 2)"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.tax_total($data), 2)"></td>
                        <td style="text-align: center;" data-bind="text: flag"></td>
                    </tr>
                <!-- /ko -->
                <!-- ko foreach: missing_b2b -->
                <tr data-bind="visible: show">
                        <td>coreERP</td>
                        <td data-bind="text: coreWebApp.formatDate(bill_date())"></td>
                        <td data-bind="text: bill_no"></td>
                        <td data-bind="text: coreWebApp.formatDate(doc_date())"></td>
                        <td data-bind="text: voucher_id"></td>
                        <td style="text-align: center;"><input type="checkbox" data-bind="checked: select" style="margin: 3px 3px 0 0;"/></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bill_amt(), 2)"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(tax_amt(), 2)"></td>
                        <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(itc_amt(), 2)"></td>
                    </tr>
                <!-- /ko -->
            </script>
        </div>
        <div class="row">
            <div>
                <h5 style="color: teal;" data-bind="text: 'GSTR 2A Missing (' + reco_data.gstr2a_missing().length + ')'">GSTR 2A Missing</h5>
                <table class="table table-hover table-condensed">
                    <thead>
                        <tr>
                            <th class="col-md-1">Supplier GSTIN</th>
                            <th class="col-md-2">Supplier</th>
                            <th class="col-md-1">Document Date</th>
                            <th class="col-md-1">Document #</th>
                            <th class="col-md-1">Bill Date</th>
                            <th class="col-md-1">Bill #</th>
                            <th class="col-md-1">Invoice Value</th>
                            <th class="col-md-1">Tax Amount</th>
                            <th class="col-md-1">ITC Amount</th>
                            <th class="col-md-1">Flag</th>
                        </tr>
                    </thead>
                    <tbody data-bind="template: { name: 'templ_gstr2a_missing', foreach: reco_data.gstr2a_missing }">

                    </tbody>
                    <tfoot>
                        <tr data-bind="visible: reco_data.gstr2a_missing().length == 0">
                            <td colspan="6">
                                <span>** No Transactions to display **</span>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right; font-weight: bold">Total</td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_col_total('bill_amt', reco_data.gstr2a_missing), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_col_total('tax_amt', reco_data.gstr2a_missing), 2)"></td>
                            <td style="text-align: right; font-weight: bold" data-bind="text: coreWebApp.formatNumber(core_tx.gstr2aReco.get_col_total('itc_amt', reco_data.gstr2a_missing), 2)"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <script type="text/html" id="templ_gstr2a_missing">
                <tr>
                    <td data-bind="text: supplier_gstin"></td>
                    <td data-bind="text: supplier"></td>
                    <td data-bind="text: coreWebApp.formatDate(doc_date())"></td>
                    <td data-bind="text: voucher_id"></td>
                    <td data-bind="text: coreWebApp.formatDate(bill_date())"></td>
                    <td data-bind="text: bill_no"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(bill_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(tax_amt(), 2)"></td>
                    <td style="text-align: right;" data-bind="text: coreWebApp.formatNumber(itc_amt(), 2)"></td>
                    <td><select data-bind="options: $parent.flagOptions,
                       optionsText: 'flag_desc',
                       optionsValue: 'flag_val',
                       value: flag"></select></td>
                </tr>
            </script>
        </div>
    </form>
</div>
