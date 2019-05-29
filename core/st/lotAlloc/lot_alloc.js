typeof window.core_st == 'undefined' ? window.core_st = {} : '';
core_st.lot_alloc = {};

(function (lot_alloc) {

    // sl_lot_types
    lot_alloc.SLT_NORM_ACCEPTED = 101;
    lot_alloc.SLT_REJECTED = 102;
    lot_alloc.SLT_PRESERVED = 103;
    lot_alloc.SLT_DAMAGED = 104;
    lot_alloc.SLT_QUARANTINED = 105;

    function select_lot_manual(opts) {
        opts.module = 'core/st';
        opts.alloc_view = '/lotAlloc/LotAlloc';
        opts.call_init = select_lot_init;
        opts.call_update = lot_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    lot_alloc.select_lot_manual = select_lot_manual;

    function select_lot(row, ptran, lot_state_id = lot_alloc.SLT_NORM_ACCEPTED) {
        var opts = {
            vch_id: coreWebApp.ModelBo.__doc_id(),
            mat_id: row.material_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            sloc_id: row.stock_location_id(),
            lot_state_id: lot_state_id,
            issued_qty: row.issued_qty(),
            parent_row: row,
            ptran: ptran
        };
        opts.module = 'core/st';
        opts.alloc_view = '/lotAlloc/LotAlloc';
        opts.call_init = select_lot_init;
        opts.call_update = lot_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    lot_alloc.select_lot = select_lot;

    function select_lot_init(opts, after_init) {
        $.ajax({
            url: '?r=core/st/lot-alloc/lot-bal',
            data: {mat_id: opts.mat_id, vch_id: opts.vch_id, doc_date: opts.doc_date, sloc_id: opts.sloc_id, lot_state_id: opts.lot_state_id},
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var lot_sel = new function () {
                    self = this;
                };
                lot_sel.lot_temp = resultdata.lot_bal;
                lot_sel.mat_name = resultdata.mat_name;
                lot_sel.issued_qty = ko.observable(opts.issued_qty);
                lot_sel.alloc_bal = ko.pureComputed(function () {
                    var alloc_sum = 0.0;
                    $.each(self.lot_temp, function (idx, itm) {
                        alloc_sum += parseFloat(itm.alloc_qty());
                    });
                    return parseFloat(self.issued_qty()) - alloc_sum;
                });
                lot_sel.uom = resultdata.uom;
                // loop and select balances for already entered values
                $.each(lot_sel.lot_temp, function (idx, itm) {
                    itm.alloc_qty = ko.observable(0.00);
                    $.each(opts.parent_row.sl_lot_alloc(), function (adx, atm) {
                        if (atm.sl_lot_id() == itm.sl_lot_id) {
                            itm.alloc_qty(atm.lot_issue_qty());
                        }
                    });
                });
                // loop the parent and try to find other mat
                $.each(opts.ptran(), function (idx, itm) {
                    if (itm != opts.parent_row && itm.material_id() == opts.parent_row.material_id()) {
                        $.each(itm.sl_lot_alloc(), function (slidx, slitm) {
                            $.each(lot_sel.lot_temp, function (li, lt) {
                                if (slitm.sl_lot_id() == lt.sl_lot_id) {
                                    lt.bal_qty = parseFloat(lt.bal_qty) - parseFloat(slitm.lot_issue_qty());
                                }
                            });
                        });
                    }
                });
                // set the model
                opts.model = lot_sel;
                $('#sl-loading').hide();

                after_init(); // We will not do standard init.
                var tbl = $('#lot_temp').DataTable({
                    data: lot_sel.lot_temp,
                    order: [],
                    columns: [
                        {data: "test_insp_id", title: "Insp #", width: "15%"},
                        {data: "test_insp_date", title: "Insp_Dt", width: "12%",
                            render: function (cellData) {
                                return coreWebApp.formatDate(cellData);
                            }
                        },
                        {data: "lot_no", title: "Lot/Batch", width: "23%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<textarea class="" readonly="" style="width:300px; border: none; resize: none;" data-bind="text: lot_no"></textarea>');
                                ko.applyBindings(rowData, $(td)[0]);
                            }},
                        {data: "mfg_date", title: "Mfg_Dt", width: "10%",
                            render: function (cellData) {
                                return coreWebApp.formatDate(cellData);
                            }
                        },
                        {data: "exp_date", title: "Exp_Dt", width: "10%",
                            render: function (cellData) {
                                return coreWebApp.formatDate(cellData);
                            }
                        },
                        {data: "bal_qty", title: "Bal_Qty", width: "10%", className: "dt-right",
                            render: function (cellData) {
                                return coreWebApp.formatNumber(cellData, 3);
                            }
                        },
                        {data: "alloc_qty", title: "Alloc Qty", width: "20%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input type="textbox" id="alloc_qty" class="textbox form-control" name="alloc_qty" \n\
                                                allownegative="false" maxval="0" scale="3" data-bind="numericValue: alloc_qty" \n\
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
                var l = $('#lot_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
                $('#lot_temp-cont').find('.dataTables_scrollBody').css('min-height', '58px');
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Stock Lot Alloc', 'Failed with errors on server', false);
            }
        });

    }
    lot_alloc.select_lot_init = select_lot_init;


    function lot_alloc_update(opts) {
        if (parseFloat(opts.model.alloc_bal()) != parseFloat(opts.model.issued_qty())) {
            if (parseFloat(opts.model.alloc_bal()) < 0) {
                coreWebApp.toastmsg('warning', 'Stock Lot Alloc', 'Allocations cannot exceed Issue Qty', false);
            }
            if (parseFloat(opts.model.alloc_bal()) > 0) {
                coreWebApp.toastmsg('warning', 'Stock Lot Alloc', 'Allocations do not match Issue Qty', false);
            }
        }

        opts.parent_row.sl_lot_alloc.removeAll();
        $.each(opts.model.lot_temp, function (idx, itm) {
            if (parseFloat(itm.alloc_qty()) > 0) {
                var nr = coreWebApp.ModelBo.addNewRow('sl_lot_alloc', opts.parent_row);
                nr.sl_lot_id(itm.sl_lot_id);
                nr.material_id(opts.mat_id);
                nr.lot_issue_qty(itm.alloc_qty());
            }
        });
        lot_alloc.is_short(opts.parent_row);
        typeof opts.tran_add_completed_callback != 'undefined' ? opts.tran_add_completed_callback(opts.parent_row) : '';
        delete opts.model; // remove the temporary model created
        return true;
    }

    function select_auto() {
        var sum_qty = parseFloat(self.issued_qty());
        $.each(self.lot_temp, function (idx, itm) {
            itm.alloc_qty(0);
        });

        $.each(self.lot_temp, function (idx, itm) {
            if (sum_qty > 0) {
                if (parseFloat(itm.bal_qty) < parseFloat(sum_qty)) {
                    itm.alloc_qty(parseFloat(itm.bal_qty));
                    sum_qty = sum_qty - parseFloat(itm.bal_qty);
                } else {
                    itm.alloc_qty(sum_qty);
                    sum_qty = 0;
                    return;
                }
            }
        });
        return;
    }
    lot_alloc.select_auto = select_auto;

    function is_short(row, lot_alloc_table = 'sl_lot_alloc') {
        if (typeof row.sl_lot_short !== 'undefined') {
            if (row.has_qc()) {
                var alloc_sum = 0.00;
                $.each(row[lot_alloc_table](), function (idx, idt) {
                    alloc_sum += parseFloat(idt.lot_issue_qty());
                });
                if (parseFloat(row.issued_qty()) != alloc_sum) {
                    row.sl_lot_short('lightcoral');
                } else {
                    row.sl_lot_short('lightseagreen');
                }
            }
            return row.sl_lot_short();
        }
        return 'lightgrey';
    }
    lot_alloc.is_short = is_short;

}(window.core_st.lot_alloc));


