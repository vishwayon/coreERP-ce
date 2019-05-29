// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.pr = {};

window.pr = {};
(function (pr) {
    // dcn Types
    pr.PURCHASE_RETURN = 0;
    pr.RATE_ADJUST = 1;
    pr.POST_BILL_DISC = 2;

    stop_calc = false;
    skip_ts_fetch = false;


    function rate_enable(dataItem) {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == pr.RATE_ADJUST
                || coreWebApp.ModelBo.annex_info.dcn_type() == pr.POST_BILL_DISC) {
            return true;
        } else {
            return false;
        }
    }
    pr.rate_enable = rate_enable;

    function amt_desc() {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == pr.PURCHASE_RETURN
                || coreWebApp.ModelBo.annex_info.dcn_type() == pr.POST_BILL_DISC) {
            return "Debit Amt";
        } else {
            return "Credit Amt";
        }
    }
    pr.amt_desc = amt_desc;

    function after_load() {
        $('#cmd_addnew_stock_tran').hide();
        if (coreWebApp.ModelBo.stock_id() == '') {
            coreWebApp.ModelBo.stock_tran().forEach(function (row) {
                fetch_hsn_info(row);
            });

            total_calc();
        }
    }
    pr.after_load = after_load;

    function item_calc(row) {
        console.log('item_calc');
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var pur_rate = Number.parseFloat(row.rate());
        var tax_amt = new Number(0.00);
        var bt_amt = (issued_qty * pur_rate);
        row.bt_amt(bt_amt.toFixed(2));
        bt_amt = parseFloat(row.bt_amt());
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });

        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        tax_amt = parseFloat(row.tax_amt()); // always pickup tax_amt to avoid float errors
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        pr.total_calc();
        stop_calc = false;
    }
    pr.item_calc = item_calc;

    function total_calc() {
        console.log('total_calc');
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var supp_lc_amt_tot = new Number(0.00);
        var is_rc = false;
        // Total each stock item
        var i = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            row.sl_no(++i);
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
            is_rc = row.gtt_is_rc();
        });
//        //Total Landed Costs
//        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_lc_tran(), function (row) {
//            if (row.supplier_paid()) {
//                if (row.apply_itc()) {
//                    supp_lc_amt_tot += parseFloat(row.debit_amt());
//                    tax_amt_tot += parseFloat(row.tax_amt());
//                } else {
//                    supp_lc_amt_tot += parseFloat(row.debit_amt()) + parseFloat(row.tax_amt());
//                }
//            }
//        });

        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.misc_taxable_amt(supp_lc_amt_tot.toFixed(2));
        var rof_amt = parseFloat(coreWebApp.ModelBo.round_off_amt());
        if (!is_rc) {
            coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
            coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot + supp_lc_amt_tot + rof_amt).toFixed(2));
        } else {
            coreWebApp.ModelBo.annex_info.rc_tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.gross_amt((bt_amt_tot).toFixed(2));
            coreWebApp.ModelBo.total_amt((bt_amt_tot + supp_lc_amt_tot + rof_amt).toFixed(2));
        }
        coreWebApp.ModelBo.net_amt(parseFloat(coreWebApp.ModelBo.total_amt()).toFixed(2));
    }
    pr.total_calc = total_calc;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    pr.enable_visible_fc = enable_visible_fc

    function fc_changed(dataItem) {
        console.log('fc_changed');
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (a) {
            if (fc_type_id == 0) {
                a.rate_fc(0);
                a.item_amt_fc(0);
            } else {
                a.rate((parseFloat(a.rate_fc()) * exch_rate).toFixed(3));
                a.item_amt((parseFloat(a.item_amt_fc()) * exch_rate).toFixed(2));
            }
        });
    }
    pr.fc_changed = fc_changed;

    function lc_item_calc(row) {
        if (stop_calc)
            return;
        stop_calc = true;
        var debit_amt = parseFloat(row.debit_amt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_pcnt = parseFloat(row.tax_pcnt());
        var tax_amt = new Number(0.00);
        if (en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = debit_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
        }
        pr.total_calc();
        stop_calc = false;
    }
    pr.lc_item_calc = lc_item_calc;

    function lc_tran_delete() {
        total_calc();
    }
    pr.lc_tran_delete = lc_tran_delete;

    function liability_acc_enable(dataItem) {
        if (typeof dataItem.supplier_paid === 'undefined')
            return;
        if (dataItem.supplier_paid() === false) {
            return true;
        } else {
            dataItem.account_affected_id(-1);
            return false;
        }
    }
    pr.liability_acc_enable = liability_acc_enable;

    function stock_lc_tax_enable(dataItem) {
        if (typeof dataItem.supplier_paid === 'undefined')
            return;
        if (dataItem.supplier_paid() === true) {
            return true;
        } else {
            dataItem.tax_schedule_id(-1);
            dataItem.en_tax_type(-1);
            dataItem.tax_pcnt(0);
            dataItem.tax_amt(0.00);
            return false;
        }
    }
    pr.stock_lc_tax_enable = stock_lc_tax_enable;

    function apply_itc(data) {
        total_calc();
    }
    pr.apply_itc = apply_itc;


    function sp_sel() {
        if (coreWebApp.ModelBo.account_id() == -1) {
            coreWebApp.toastmsg('warning', 'Add Pur Items Click Error', 'Select Supplier to view Purchase Items.', false);
            return;
        }
        var opts = {
            origin_inv_id: coreWebApp.ModelBo.annex_info.origin_inv_id(),
            stock_tran: coreWebApp.ModelBo.stock_tran,
            tran_item_calc_callback: item_calc,
            tran_add_callback: pr.item_calc,
            update_rate: coreWebApp.ModelBo.annex_info.dcn_type() == pr.PURCHASE_RETURN,
            //fetch_mat_callback: fetch_mat_info,
            after_update: total_calc
        };
        core_st.sp_for_pr.sp_sel_ui(opts);
    }
    pr.sp_sel = sp_sel;

    function lc_tran_visible() {
        return false;
    }
    pr.lc_tran_visible = lc_tran_visible;

    function fetch_hsn_info(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-purch',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code, mat_id: mat_id},
            success: function (result) {
                var gst_hsn_info = $.parseJSON(result.gst_hsn_info);
                if (typeof result.mat_id !== 'undefined') {
                    // Get Gst info
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_PURCH,
                        origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                        target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        is_ctp: coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_COMPOS,
                        gst_hsn_info: gst_hsn_info,
                        row: row
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);

                    stop_calc = false;
                    item_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    pr.fetch_hsn_info = fetch_hsn_info;

}(window.core_st.pr));

// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
// GST Methods and utils that are part of tx
window.core_st.spg_prv_wiz = {};
(function (spg_prv_wiz) {

    function select_spg_init(args) {
        $('#tbl-SelectStockPurchase').DataTable({
            data: args.model.SelectStockPurchase(),
            order: [],
            columns: [
                {data: "selected", title: "...",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: selected">');
                        ko.applyBindings(rowData, $(td)[0]);
                        $(td).css('text-align', 'center');
                    }
                },
                {data: "stock_tran_id", title: "Stock Purchase Item #"},
                {data: "doc_date", title: "Date",
                    render: function (cellData) {
                        return coreWebApp.formatDate(cellData());
                    }
                },
                {data: "material_type", title: "Stock Type"},
                {data: "material_name", title: "Stock Item"},
                {data: "uom_desc", title: "UoM"},
                {data: "reject_qty", title: "Rejected Qty", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 3);
                    }
                }
            ],
            deferRender: true,
            scrollY: '400px',
            scrollCollapse: true,
            scroller: true,
        });
    }
    spg_prv_wiz.select_spg_init = select_spg_init;

}(window.core_st.spg_prv_wiz));
