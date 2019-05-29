typeof window.core_ac === 'undefined' ? window.core_ac = {} : '';
window.core_ac.ref_alloc = {};

(function (ref_alloc) {

    function sel_ref_alloc(opts) {
        opts.module = 'core/ac';
        opts.alloc_view = 'refAlloc/RefAlloc';
        opts.call_init = ref_alloc_init;
        opts.call_update = ref_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    ref_alloc.sel_ref_alloc = sel_ref_alloc;

    function ref_alloc_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ac/form/ref-ledger-alloc',
            type: 'GET',
            dataType: 'json',
            data: {'voucher_id': opts.voucher_id, 'doc_date': opts.doc_date, 'account_id': opts.account_id, 'dc': 'C', 'branch_id': opts.branch_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                if (resultdata.status === 'ok') {
                    var ref_alloc_sel = new function () {
                        self = this;
                    };
//                    // Using a datatable to render data
//                    if ($.fn.dataTable.isDataTable('#ref_alloc_temp')) {
//                        var t = $('#ref_alloc_temp').DataTable();
//                        t.destroy(true);
//                        var p = $('#ref_alloc_temp-cont');
//                        p.append('<table id="ref_alloc_temp" class="table table-hover table-condensed dataTable no-footer"></table>');
//                    }

                    ref_alloc_sel.ref_alloc_temp = resultdata.ref_bal;
                    ref_alloc_sel.acc_head = resultdata.acc_head;
                    ref_alloc_sel.debit_amt = ko.observable(opts.parent_row.debit_amt());
                    ref_alloc_sel.alloc_bal = ko.pureComputed(function () {
                        var alloc_sum = 0.0;
                        $.each(self.ref_alloc_temp, function (idx, itm) {
                            alloc_sum += parseFloat(itm.alloc_amt());
                        });
                        return parseFloat(self.debit_amt()) - alloc_sum;
                    });
                    // loop and select balances for already entered values
                    $.each(ref_alloc_sel.ref_alloc_temp, function (idx, itm) {
                        itm.alloc_amt = ko.observable(0.00);
                        $.each(opts.parent_row.ref_ledger_alloc_tran(), function (adx, atm) {
                            if (atm.ref_ledger_id() == itm.ref_ledger_id) {
                                itm.alloc_amt(atm.net_credit_amt());
                            }
                        });
                    });

                    // set the model
                    opts.model = ref_alloc_sel;
                    $('#rl-loading').hide();

                    after_init(); // We will not do standard init.
                    var tbl = $('#ref_alloc_temp').DataTable({
                        data: ref_alloc_sel.ref_alloc_temp,
                        order: [],
                        columns: [
                            {data: "voucher_id", title: "Voucher #", width: "15%"},
                            {data: "doc_date", title: "Ref_Dt", width: "12%",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "ref_no", title: "Ref No", width: "15%"},
                            {data: "ref_desc", title: "Desc", width: "15%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<textarea class="" readonly="" style="width:300px; border: none; resize: none;" data-bind="text: ref_desc"></textarea>');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }},
                            {data: "balance", title: "Balance", width: "10%", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 3);
                                }
                            },
                            {data: "alloc_amt", title: "Alloc Amt", width: "20%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="textbox" id="alloc_amt" class="textbox form-control" name="alloc_amt" \n\
                                                allownegative="false" maxval="0" scale="3" data-bind="numericValue: alloc_amt" \n\
                                                style="text-align: right;">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }
                            }
                        ],
                        deferRender: true,
                        scrollY: '250px',
                        scrollCollapse: true,
                        scroller: true
                    });
                    var l = $('#ref_alloc_temp_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                    $('#ref_alloc_temp-cont').find('.dataTables_scrollBody').css('min-height', '58px');
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }

    function ref_alloc_update(opts) {
        if (parseFloat(opts.model.alloc_bal()) != parseFloat(opts.model.debit_amt())) {
            if (parseFloat(opts.model.alloc_bal()) < 0) {
                coreWebApp.toastmsg('warning', 'Ref Alloc', 'Allocations cannot exceed Amount', false);
            }
            if (parseFloat(opts.model.alloc_bal()) > 0) {
                coreWebApp.toastmsg('warning', 'Ref Alloc', 'Allocations do not match Amount', false);
            }
        }

        opts.parent_row.ref_ledger_alloc_tran.removeAll();
        var sl = 0;
        $.each(opts.model.ref_alloc_temp, function (idx, itm) {
            if (parseFloat(itm.alloc_amt()) > 0) {
                sl = sl + 1;
                var nr = coreWebApp.ModelBo.addNewRow('ref_ledger_alloc_tran', opts.parent_row);
                nr.branch_id(coreWebApp.ModelBo.branch_id());
                nr.affect_voucher_id(opts.parent_row.stock_id());
                nr.affect_vch_tran_id(opts.parent_row.stock_lc_tran_id());
                nr.affect_doc_date(coreWebApp.ModelBo.doc_date());
                nr.account_id(opts.parent_row.account_id());
                nr.net_debit_amt(0);
                nr.net_credit_amt(itm.alloc_amt());
                nr.ref_ledger_id(itm.ref_ledger_id);
                nr.ref_ledger_alloc_id(sl)
                nr.status(coreWebApp.ModelBo.status());
            }
        });
        typeof opts.tran_add_completed_callback != 'undefined' ? opts.tran_add_completed_callback(opts.parent_row) : '';
        delete opts.model; // remove the temporary model created
        return true;
    }
    ref_alloc.ref_alloc_update = ref_alloc_update;
}(window.core_ac.ref_alloc));


