// Declare core_ap Namespace
typeof window.core_fa == 'undefined' ? window.core_fa = {} : '';
window.core_fa.ap = {};


(function (ap) {
    ap.sl_no = 0;

    function afterload() {
        ap.sl_no = coreWebApp.ModelBo.ap_tran().length;
        if (coreWebApp.ModelBo.ap_without_po() == '0') {
            $('#cmd_addnew_ap_tran').hide();
        }
    }
    ap.afterload = afterload;

    function account_filter(fltr) {
        if (coreWebApp.ModelBo.en_purchase_type() == 0) {
            fltr = ' account_type_id = 2 ';
        } else if (coreWebApp.ModelBo.en_purchase_type() == 1) {
            fltr = ' account_type_id = 1 ';
        } else if (coreWebApp.ModelBo.en_purchase_type() == 2) {
            fltr = ' account_type_id = 12';
        } else if (coreWebApp.ModelBo.en_purchase_type() == 3) {
            fltr = ' account_type_id not in (0, 1, 2, 7, 12, 23, 24, 21, 22, 18, 38)';
        } else {
            fltr += " account_type_id = -1 ";
        }
        return fltr;
    }
    ap.account_filter = account_filter;

    function ap_tran_add(row) {
        ap.sl_no += 1;
        row.sl_no(ap.sl_no);
        row.use_start_date(coreWebApp.ModelBo.doc_date());
        if (row.asset_qty() == -1) {
            row.asset_qty(0);
        }
        row.gtt_apply_itc(true);
        if (row.hsn_sc_id() != -1) {
            ap.fetch_hsn_info(row);
        }
    }
    ap.ap_tran_add = ap_tran_add;

    function item_calc(row) {
        var bt_amt = parseFloat(row.bt_amt());
        // This is GST
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        ap.total_calc();
    }
    ap.item_calc = item_calc;

    function total_calc(dataItem) {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);

        ap.sl_no = 0;
        // Total each bill item
        ko.utils.arrayForEach(coreWebApp.ModelBo.ap_tran(), function (row) {
            ap.sl_no += 1;
            row.sl_no(ap.sl_no);
            tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
            item_amt_tot += Number.parseFloat(row.bt_amt());
            row.purchase_amt((tax_amt_tot + item_amt_tot).toFixed(2));
        });

        coreWebApp.ModelBo.gross_credit_amt(item_amt_tot.toFixed(2));

        if (coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.annex_info.tax_amt(0.00);
        } else {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(0.00);
            coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
        }

        var purchase_amt_tot = Number.parseFloat(coreWebApp.ModelBo.gross_credit_amt()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt()) + Number.parseFloat(coreWebApp.ModelBo.round_off_amt());

        coreWebApp.ModelBo.credit_amt(purchase_amt_tot.toFixed(2));
        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.credit_amt());
            adv_settle_fc += Number.parseFloat(row.credit_amt_fc());
        });

        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));

        coreWebApp.ModelBo.net_credit_amt((Number.parseFloat(coreWebApp.ModelBo.credit_amt()) - Number.parseFloat(coreWebApp.ModelBo.advance_amt())).toFixed(2));

    }
    ap.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.ap_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    ap.redo_item_calc = redo_item_calc;

    function enable_gst(dataItem) {
        if (dataItem.en_purchase_type() == 2) {
            return false;
        } else {
            return true;
        }
    }
    ap.enable_gst = enable_gst;

    function disable_gst(dataItem) {
        if (dataItem.en_purchase_type() == 2) {
            return true;
        } else {
            return false;
        }
    }
    ap.disable_gst = disable_gst;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    ap.tax_amt_ov = tax_amt_ov;

    function supplier_state_update() {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_COMPOS,
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id(gstOpts.vat_type_id);
            $('[id="annex_info.gst_input_info.vat_type_id"]').trigger('change');
            if (coreWebApp.ModelBo.ap_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.ap_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    ap.supplier_state_update = supplier_state_update;

    function fetch_supp_info() {
        // Fetch GST related information only if purchase type id Credit
        if (coreWebApp.ModelBo.en_purchase_type() == 2) {
            opts = {
                supp_id: coreWebApp.ModelBo.account_id(),
                after_update: fetch_supp_info_after_update
            };
            core_ap.get_address(opts);
        }
    }
    ap.fetch_supp_info = fetch_supp_info;

    function fetch_supp_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_address(opts.result.addr);
        } else {
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(-1);
            coreWebApp.trigger_change('annex_info.gst_input_info.supplier_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin("");
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_address("");
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: (typeof opts.result != 'undefined' ? opts.result.is_ctp : false)
            };
            core_tx.gst.get_vat_type(gstOpts);

            coreWebApp.ModelBo.annex_info.gst_input_info.is_ctp(gstOpts.is_ctp);

            $('#vat_type_id').trigger('change');
            if (coreWebApp.ModelBo.ap_tran().length > 0) {
                gstOpts.tran = coreWebApp.ModelBo.ap_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }

    }
    ap.fetch_supp_info_after_update = fetch_supp_info_after_update;

    function select_supp_addr() {
        var opts = {
            supp_id: coreWebApp.ModelBo.account_id(),
            after_update: select_supp_addr_after_update
        };
        core_ap.select_address(opts);
    }
    ap.select_supp_addr = select_supp_addr;

    function select_supp_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_address(opts.result.addr);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_COMPOS,
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id(gstOpts.vat_type_id);
            $('#vat_type_id').trigger('change');
            if (coreWebApp.ModelBo.ap_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.ap_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    ap.select_supp_addr_after_update = select_supp_addr_after_update;

    function supplier_state_update() {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.is_ctp()
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id(gstOpts.vat_type_id);
            $('[id="annex_info.gst_input_info.vat_type_id"]').trigger('change');
            if (old_vat_type_id != gstOpts.vat_type_id) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
                $('[id="annex_info.gst_rc_info.rc_sec_id"]').trigger('change');
            }
            if (coreWebApp.ModelBo.ap_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.ap_tran;
                gstOpts.call_back = total_calc();
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    ap.supplier_state_update = supplier_state_update;

    function fetch_hsn_info(row) {
        var hsn_sc_id = row.hsn_sc_id();
        $.ajax({
            url: '?r=core/tx/form/get-hsn-gst-info',
            type: 'GET',
            dataType: 'json',
            data: {hsn_sc_id: hsn_sc_id},
            success: function (gst_hsn_info) {
                if (typeof gst_hsn_info.hsn_sc_code !== 'undefined') {
                    stop_calc = true;
                    // This is GST
                    core_tx.gst.item_gtt_reset({
                        txn_type: core_tx.gst.TXN_PURCH,
                        origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                        target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_COMPOS,
                        gst_hsn_info: gst_hsn_info,
                        row: row
                    });
                    stop_calc = false;
                    item_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected HSN SC', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    ap.fetch_hsn_info = fetch_hsn_info;

    function adv_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.ap_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.credit_amt(),
                credit_amt_total_fc: coreWebApp.ModelBo.credit_amt_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    ap.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function ap_tran_delete() {
        total_calc();
    }
    ap.ap_tran_delete = ap_tran_delete;

    function adv_alloc_clear_click() {
        coreWebApp.ModelBo.payable_ledger_alloc_tran.removeAll();
        total_calc();
    }
    ap.adv_alloc_clear_click = adv_alloc_clear_click;

    function apply_rc_update(dataItem) {
        if (!dataItem()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
        } else {
            if (coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_SGST_CGST) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(94);
            } else if (coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_IGST
                    || coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_IMPORT
                    || coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_SEZ) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(54);
            }
        }
        total_calc();
    }
    ap.apply_rc_update = apply_rc_update;

    function  cheque_info_visible(dataItem) {
        if (coreWebApp.ModelBo.en_purchase_type() == 1) {
            return true;
        }
        return false;
    }
    ap.cheque_info_visible = cheque_info_visible;

    function po_sel() {
        if (coreWebApp.ModelBo.account_id() == -1) {
            coreWebApp.toastmsg('warning', 'PO Click Error', 'Select Supplier to view Purchase Orders.', false);
            return;
        }
        if (coreWebApp.ModelBo.en_purchase_type() == 2) {
            var opts = {
                voucher_id: coreWebApp.ModelBo.ap_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                supplier_id: coreWebApp.ModelBo.account_id(),
                ap_tran: coreWebApp.ModelBo.ap_tran,
                tran_item_calc_callback: item_calc,
                tran_add_callback: ap_tran_add,
                after_update: total_calc
            };
            core_sm.po_for_ap.po_sel_ui(opts);
        }
    }
    ap.po_sel = po_sel;

    function sel_po_visible(dataItem) {
        if (coreWebApp.ModelBo.en_purchase_type() == 3) {
            return true;
        }
        return false;
    }
    ap.sel_po_visible = sel_po_visible;

    function gst_rate_select(row) {
        gstOpts = {
            txn_type: core_tx.gst.TXN_PURCH,
            origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
            target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_COMPOS,
            //gst_hsn_info: gst_hsn_info,
            row: row,
            after_update: redo_item_calc
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tx.gst_rate.select_gst_rate(gstOpts);
    }
    ap.gst_rate_select = gst_rate_select;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.ap_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    ap.redo_item_calc = redo_item_calc;

    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_fa.ap.view_gl_init');
    }
    ap.view_gl = view_gl;

    function view_gl_init() {
        core_ac.gl_distribution('fa.ap_control', coreWebApp.ModelBo.ap_id());
    }
    ap.view_gl_init = view_gl_init;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.ap_id() != '' && coreWebApp.ModelBo.ap_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    ap.visible_gl_distribution = visible_gl_distribution;

}(window.core_fa.ap));