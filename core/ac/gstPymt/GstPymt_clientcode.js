// Declare core_ap Namespace
typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
typeof window.core_ac.gst_pymt == 'undefined' ? window.core_ac.gst_pymt = {} : '';

(function (gst_pymt) {
    gst_pymt.sl_no = 0;

    function afterload() {
        core_ac.vch_afterload();
        gst_pymt.sl_no = coreWebApp.ModelBo.vch_tran().length;
    }
    gst_pymt.afterload = afterload;

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
            if (old_vat_type_id != gstOpts.vat_type_id) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
            }
            if (coreWebApp.ModelBo.vch_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.vch_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    gst_pymt.supplier_state_update = supplier_state_update;

    function supplier_state_enable() {
        return coreWebApp.ModelBo.vch_tran().length == 0;
    }
    gst_pymt.supplier_state_enable = supplier_state_enable;

    function apply_rc() {
        return coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc();
    }
    gst_pymt.apply_rc = apply_rc;

    function apply_rc_update(dataItem) {
        if (!dataItem()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
            coreWebApp.ModelBo.vch_tran().forEach(function (x) {
                if (x.gtt_is_rc()) {
                    x.gtt_is_rc(false);
                }
            });
        }
        total_calc();
    }
    gst_pymt.apply_rc_update = apply_rc_update;

    function hide_bill_supp() {
        return !coreWebApp.ModelBo.annex_info.line_item_gst();
    }
    gst_pymt.hide_bill_supp = hide_bill_supp;

    function hide_bill_amt() {
        return coreWebApp.ModelBo.annex_info.line_item_gst();
    }
    gst_pymt.hide_bill_amt = hide_bill_amt;


    function select_hsn(row) {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select GST Rate', 'Select Supplier State before selecting GST rate.</br>For unregistered supplier, enter the state code only');
            return;
        }
        opts = {
            txn_type: core_tx.gst.TXN_PURCH,
            origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
            target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            is_ctp: row.gtt_is_ctp(),
            //gst_hsn_info: gst_hsn_info,
            row: row,
            after_update: redo_item_calc
        };
        core_tx.hsn.select_hsn(opts);
    }
    gst_pymt.select_hsn = select_hsn;

    function gtt_is_rc_update(row) {
        total_calc();
    }
    gst_pymt.gtt_is_rc_update = gtt_is_rc_update;

    function rc_sec_filter(fltr) {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_SGST_CGST) {
            fltr += ' rc_sec_id in (93,94)';
        } else if (coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_IGST
                || coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_IMPORT
                || coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_SEZ) {
            fltr += ' rc_sec_id in (53,54)';
        } else if (coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id() == core_tx.gst.PURCH_COMPOS) {
            fltr += ' rc_sec_id = -1';
        }
        return fltr;
    }
    gst_pymt.rc_sec_filter = rc_sec_filter;

    function tran_rc_check(row) {
        if (!row.gtt_is_rc()) {
            row.gtt_rc_sec_id(-1);
        }
        item_calc(row);
    }
    gst_pymt.tran_rc_check = tran_rc_check;

    function rc_btn_text(row) {
        if (row.gtt_rc_sec_id() == 53) {
            return '5(3)';
        } else if (row.gtt_rc_sec_id() == 54) {
            return '5(4)';
        } else if (row.gtt_rc_sec_id() == 93) {
            return '9(3)';
        } else if (row.gtt_rc_sec_id() == 94) {
            return '9(4)';
        }
        return '...';
    }
    gst_pymt.rc_btn_text = rc_btn_text;

    function account_filter(fltr) {
        if (coreWebApp.ModelBo.annex_info.pymt_type() == 0) {//Bank
            fltr += " account_type_id = 1 ";
        } else if (coreWebApp.ModelBo.annex_info.pymt_type() == 1) {
            fltr += " account_type_id not in (1,2,6,7,12,17,18,32,45,46,47) ";
        } else if (coreWebApp.ModelBo.annex_info.pymt_type() == 2) {//Cash
            fltr += " account_type_id = 2 ";
        } else {
            fltr += " account_type_id = -1 ";
        }
        return fltr;
    }
    gst_pymt.account_filter = account_filter;

    function vch_before_tran_add() {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Add Line Items', 'Please select Supplier GST State before adding line items');
            return false;
        }
        return true;
    }
    gst_pymt.vch_before_tran_add = vch_before_tran_add;

    function vch_tran_add(row) {
        core_ac.vch_tran_add_new_row_debit(row);
        gst_pymt.sl_no += 1;
        row.sl_no(gst_pymt.sl_no);
        row.gtt_apply_itc(true);
        row.dc("D");
        row.branch_id(coreWebApp.ModelBo.branch_id());
    }
    gst_pymt.vch_tran_add = vch_tran_add;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    gst_pymt.tax_amt_ov = tax_amt_ov;

    function item_calc(row) {
        if (!coreWebApp.ModelBo.annex_info.line_item_gst()) {
            // clear line item roff
            if (row.roff_amt() != 0) {
                row.roff_amt(0.00);
            }
        }
        var bt_amt = parseFloat(row.debit_amt());
        // This is GST
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        total_calc();
    }
    gst_pymt.item_calc = item_calc;

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_rc_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var gtt_bt_amt_tot = new Number(0.00);
        var rf_amt = 0.00;

        gst_pymt.sl_no = 0;
        // Total each bill item
        if (coreWebApp.ModelBo.annex_info.line_item_gst()) {
            $.each(coreWebApp.ModelBo.vch_tran(), function (idx, row) {
                gst_pymt.sl_no += 1;
                row.sl_no(gst_pymt.sl_no);
                gtt_bt_amt_tot += parseFloat(row.gtt_bt_amt());
                item_amt_tot += parseFloat(row.debit_amt());
                if (row.gtt_is_rc()) {
                    tax_amt_rc_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                            + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
                } else {
                    tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                            + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
                }
                rf_amt += parseFloat(row.roff_amt());
            });
            coreWebApp.ModelBo.annex_info.round_off_amt(rf_amt.toFixed(2));
        } else {
            $.each(coreWebApp.ModelBo.vch_tran(), function (idx, row) {
                gst_pymt.sl_no += 1;
                row.sl_no(gst_pymt.sl_no);
                gtt_bt_amt_tot += parseFloat(row.gtt_bt_amt());
                item_amt_tot += parseFloat(row.debit_amt());
                if (coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc()) {
                    tax_amt_rc_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                            + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
                } else {
                    tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                            + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
                }
            });
        }
        coreWebApp.ModelBo.annex_info.bt_amt(gtt_bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(tax_amt_rc_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
        var gross_amt = parseFloat(coreWebApp.ModelBo.annex_info.bt_amt()) + parseFloat(coreWebApp.ModelBo.annex_info.tax_amt());
        rf_amt = parseFloat(coreWebApp.ModelBo.annex_info.round_off_amt());
        coreWebApp.ModelBo.credit_amt((gross_amt + rf_amt).toFixed(2));
        coreWebApp.ModelBo.bill_diff((parseFloat(coreWebApp.ModelBo.credit_amt()) - parseFloat(coreWebApp.ModelBo.annex_info.bill_amt())).toFixed(2));
    }
    gst_pymt.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.vch_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    gst_pymt.redo_item_calc = redo_item_calc;

    function pymt_view_gl_init() {
        core_ac.gl_distribution('ac.vch_control', coreWebApp.ModelBo.voucher_id());
    }
    gst_pymt.pymt_view_gl_init = pymt_view_gl_init;


    function pymt_view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_ac.gst_pymt.pymt_view_gl_init');
    }
    gst_pymt.pymt_view_gl = pymt_view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.voucher_id() != '' && coreWebApp.ModelBo.voucher_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    gst_pymt.visible_gl_distribution = visible_gl_distribution;

    function ib_branch_filter(fltr) {
        fltr += " gst_state_id = " + coreWebApp.branch_gst_info.gst_state_id;
        return fltr;
    }
    gst_pymt.ib_branch_filter = ib_branch_filter;

    function sub_head_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total: coreWebApp.ModelBo.annex_info.bill_amt(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                dc: 'C',
                sl_no: 0,
                ref_no: coreWebApp.ModelBo.ref_no(),
                ref_desc: coreWebApp.ModelBo.ref_desc(),
                row: coreWebApp.ModelBo,
                shl_tran_name: 'shl_head_tran',
                rla_tran_name: 'rla_head_tran',
                after_update: sub_head_alloc_after_update
            };
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    gst_pymt.sub_head_alloc_click = sub_head_alloc_click;

    function sub_head_alloc_after_update() {
        total_calc();
    }

    function sub_head_alloc_tran_click(row) {
        if (row['account_id']() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: row['account_id'](),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total: row['debit_amt'](),
                debit_amt_total_fc: row['debit_amt'](),
                sl_tran: row['sub_head_ledger_tran'], // The observable array is sent 
                ref_ledger_tran: row['ref_ledger_alloc_tran'], // The observable array is sent  
                dc: 'D',
                sl_no: row['sl_no'](),
                ref_no: row['ref_no'](),
                ref_desc: row['ref_desc'](),
                row: row,
                shl_tran_name: 'sub_head_ledger_tran',
                rla_tran_name: 'ref_ledger_alloc_tran',
                after_update: sub_head_alloc_after_update
            };
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    gst_pymt.sub_head_alloc_tran_click = sub_head_alloc_tran_click;

    function vch_tran_delete() {
        total_calc();
    }
    gst_pymt.vch_tran_delete = vch_tran_delete;

    function gtt_supplier_gstin_enable(row) {
        if (!row.gtt_is_rc() && row.hsn_sc_id() != -1 && row.hsn_sc_id() != 0 && row.hsn_sc_id() != 99000
                && coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin().length <= 2) {
            return true;
        }
        return false;
    }
    gst_pymt.gtt_supplier_gstin_enable = gtt_supplier_gstin_enable;

    function gst_rate_select(row) {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select GST Rate', 'Select Supplier State before selecting GST rate.</br>For unregistered supplier, enter the state code only');
            return;
        }
        gstOpts = {
            txn_type: core_tx.gst.TXN_PURCH,
            origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
            target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            is_ctp: row.gtt_is_ctp(),
            //gst_hsn_info: gst_hsn_info,
            row: row,
            after_update: redo_item_calc
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tx.gst_rate.select_gst_rate(gstOpts);
    }
    gst_pymt.gst_rate_select = gst_rate_select;

    function select_rc_info(row) {
        if (row.gtt_is_rc()) {
            opts = {
                row: row
            };
            core_ac.gst_pymt.rc_popup.select_rc_info(opts);
        } else {
            coreWebApp.toastmsg('message', 'Reverse Charge', 'Additional details available only when Reverse Charge applied');
        }
    }
    gst_pymt.select_rc_info = select_rc_info;

}(window.core_ac.gst_pymt));


