// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.sr = {};

(function (sr) {
    // dcn Types
    sr.SALE_RETURN = 0;
    sr.RATE_ADJUST = 1;
    sr.POST_INV_DISC = 2;
    sr.DAMAGED_SUPPLY = 3;
    
    stop_calc = false;
    skip_ts_fetch = false;

    function rate_enable(dataItem) {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == sr.RATE_ADJUST 
                || coreWebApp.ModelBo.annex_info.dcn_type() == sr.POST_INV_DISC) {
            return true;
        } else {
            return false;
        }
    }
    sr.rate_enable = rate_enable;
    
    function amt_desc() {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == sr.SALE_RETURN
                || coreWebApp.ModelBo.annex_info.dcn_type() == sr.POST_INV_DISC
                || coreWebApp.ModelBo.annex_info.dcn_type() == sr.DAMAGED_SUPPLY) {
            return "Credit Amt";
        } else {
            return "Debit Amt";
        }
    }
    sr.amt_desc = amt_desc;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    sr.enable_visible_fc = enable_visible_fc;


    function fc_changed(dataItem) {
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
//        //dataItem.invoice_amt(parseFloat(dataItem.invoice_amt_fc())*exch_rate);
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (a) {
            if (fc_type_id == 0) {
                a.rate_fc(0);
                a.item_amt_fc(0);
            } else {
                a.rate((parseFloat(a.rate_fc()) * exch_rate).toFixed(3));
                a.item_amt((parseFloat(a.item_amt_fc()) * exch_rate).toFixed(3));
            }
        });
    }
    sr.fc_changed = fc_changed;

    function sr_afterload(row) {
        $('#cmd_addnew_stock_tran').hide();
        total_calc();
    }
    sr.sr_afterload = sr_afterload;

    function item_calc(row) {
        console.log('item_calc');
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var item_rate = Number.parseFloat(row.rate());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
        var bt_amt = received_qty * item_rate;
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
        total_calc();
        stop_calc = false;
    }
    sr.item_calc = item_calc;

    function st_tran_delete() {
        total_calc();
    }
    sr.st_tran_delete = st_tran_delete;

    function total_calc() {
        console.log('total_calc');
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        // Total each stock item
        var i = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            row.sl_no(++i);
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
        var rof_amt = Number.parseFloat((bt_amt_tot + tax_amt_tot).toFixed(0)) - (bt_amt_tot + tax_amt_tot);
        coreWebApp.ModelBo.round_off_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot + rof_amt).toFixed(2));

        coreWebApp.ModelBo.net_amt(parseFloat(coreWebApp.ModelBo.total_amt()).toFixed(2));
    }
    sr.total_calc = total_calc;
    
    function si_sel() {
        if (coreWebApp.ModelBo.account_id() == -1) {
            coreWebApp.toastmsg('warning', 'PO Click Error', 'Select Supplier to view Purchase Orders.', false);
            return;
        }
        var opts = {
            origin_inv_id: coreWebApp.ModelBo.annex_info.origin_inv_id(),
            stock_tran: coreWebApp.ModelBo.stock_tran,
            tran_item_calc_callback: item_calc,
            tran_add_callback: sr.item_calc,
            update_rate: (coreWebApp.ModelBo.annex_info.dcn_type() == sr.SALE_RETURN
                            || coreWebApp.ModelBo.annex_info.dcn_type() == sr.DAMAGED_SUPPLY),
            //fetch_mat_callback: fetch_mat_info,
            after_update: total_calc
        };
        core_st.si_for_sr.si_sel_ui(opts);
    }
    sr.si_sel = si_sel;

}(window.core_st.sr));
