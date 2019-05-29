// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.sp = {};

(function (sp) {
    stop_calc = false;
    skip_ts_fetch = false;
    sp.sl_no = 0;
    sp.gst_sl_no = 0;

    function after_load() {
        sp.sl_no = coreWebApp.ModelBo.stock_tran().length;
        sp.gl_sl_no = coreWebApp.ModelBo.annex_info.gst_tax_tran().length;
        if (coreWebApp.ModelBo.status() != 5) {
            var htcontents = $('#content-root').height();
            $('#cboformbody').height(htcontents * 0.9);
            stght = 0;
            if ($('#doc_stage_info').length > 0) {
                stght = $('#doc_stage_info').height();
            }
            $('#cboformbody').height($('#cboformbody').height() - stght);
        }
    }
    sp.after_load = after_load;

    function fetch_supp_info() {
        opts = {
            supp_id: coreWebApp.ModelBo.account_id(),
            after_update: fetch_supp_info_after_update
        };
        core_ap.get_address(opts);
    }
    sp.fetch_supp_info = fetch_supp_info;

    function fetch_supp_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.lookupCache.add('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_addr(opts.result.addr);
        } else {
            coreWebApp.lookupCache.add('annex_info.gst_input_info.supplier_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(-1);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin("");
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_addr("");
        }
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin().length != 15) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc(true);
            if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() !=
                    coreWebApp.branch_gst_info.gst_state_id) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(54);
            } else {
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(94);
            }
            if (coreWebApp.ModelBo.stock_tran().length > 0) {
                sp.total_calc();
            }
        } else {
            coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc(false);
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
            if (coreWebApp.ModelBo.stock_tran().length > 0) {
                sp.total_calc();
            }
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
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.stock_tran().length > 0) {
                gstOpts.tran = coreWebApp.ModelBo.stock_tran;
                gstOpts.row_update_callback = item_calc;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    sp.fetch_supp_info_after_update = fetch_supp_info_after_update;

    function select_supp_addr() {
        if (coreWebApp.ModelBo.v_cash_supp_regd_id() == coreWebApp.ModelBo.account_id()) {
            // Cash supplier to be entered manually
            var gstin = window.prompt('Please enter the GSTIN', "");
            var regex = new RegExp('^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}[Z]{1}[0-9A-Z]{1}$');
            console.log(regex.test(gstin));
            if (gstin !== '' && regex.test(gstin)) {
                coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(gstin);
                coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc(false);
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
                if (coreWebApp.ModelBo.stock_tran().length > 0) {
                    sp.total_calc();
                }
            } else {
                coreWebApp.toastmsg('message', 'Incorrect GSTIN format. Not accepted');
            }
        } else {
            var opts = {
                supp_id: coreWebApp.ModelBo.account_id(),
                after_update: select_supp_addr_after_update
            };
            core_ap.select_address(opts);
        }
    }
    sp.select_supp_addr = select_supp_addr;

    function select_supp_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.lookupCache.add('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_addr(opts.result.addr);
            if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin().length != 15) {
                coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc(true);
                if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() !=
                        coreWebApp.branch_gst_info.gst_state_id) {
                    coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(54);
                } else {
                    coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(94);
                }
                if (coreWebApp.ModelBo.stock_tran().length > 0) {
                    sp.total_calc();
                }
            } else {
                coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc(false);
                coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
                if (coreWebApp.ModelBo.stock_tran().length > 0) {
                    sp.total_calc();
                }
            }
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: opts.result.is_ctp
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.vat_type_id();
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.stock_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.stock_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
            //Deprecated after select2.update fixed
            //$('#vat_type_id').trigger('change');
        }
    }
    sp.select_supp_addr_after_update = select_supp_addr_after_update;

    function item_calc(row) {
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var pur_rate = Number.parseFloat(row.rate());
        var disc_amt = Number.parseFloat(row.disc_amt());
        // Set fetch bt_amt
        row.bt_amt(((received_qty * pur_rate) - disc_amt).toFixed(2));
        var bt_amt = parseFloat(row.bt_amt());
        var tax_amt = new Number(0.00);
        ;
        if (!coreWebApp.ModelBo.annex_info.bill_level_tax()) {
            core_tx.gst.item_gtt_calc({
                bt_amt: bt_amt,
                row: row
            });
            // set fetch tax amt
            tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
            row.tax_amt(tax_amt.toFixed(2));
            tax_amt = parseFloat(row.tax_amt());
        } else {
            // Bill level tax also pending
            row.gtt_tax_amt_ov(true); // This would prevent tax being calculated and placed in amount.
            core_tx.gst.item_gtt_calc({
                bt_amt: bt_amt,
                row: row
            });
        }
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        sp.total_calc();
        stop_calc = false;
    }
    sp.item_calc = item_calc;

    function bill_gst_item_calc(row) {
        if (row.tax_amt_ov()) {
            // call only when tax amt is manual entry
            total_calc();
        }
    }
    sp.bill_gst_item_calc = bill_gst_item_calc;

    function total_calc() {
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var supp_lc_amt_tot = new Number(0.00);
        var adv_amt_tot = new Number(0.00);
        // Total each stock item
        sp.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            sp.sl_no += 1;
            row.sl_no(sp.sl_no);
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        //Total Purchase Tax
        if (coreWebApp.ModelBo.annex_info.bill_level_tax()) {
            set_purchase_tax_ass_val();
            coreWebApp.ModelBo.annex_info.gst_tax_tran().forEach((gti) => {
                if (!gti.tax_amt_ov()) {
                    var bt_amt = parseFloat(gti.bt_amt());
                    gti.sgst_amt((bt_amt * parseFloat(gti.sgst_pcnt()) / 100).toFixed(2));
                    gti.cgst_amt((bt_amt * parseFloat(gti.cgst_pcnt()) / 100).toFixed(2));
                    gti.igst_amt((bt_amt * parseFloat(gti.igst_pcnt()) / 100).toFixed(2));
                    gti.cess_amt((bt_amt * parseFloat(gti.cess_pcnt()) / 100).toFixed(2));
                }
                var tax_amt = parseFloat(gti.sgst_amt()) + parseFloat(gti.cgst_amt())
                        + parseFloat(gti.igst_amt()) + parseFloat(gti.cess_amt());
                tax_amt_tot += tax_amt;
            });
        }
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        if (coreWebApp.ModelBo.annex_info.bill_level_tax()) {
            bt_amt_tot -= parseFloat(coreWebApp.ModelBo.disc_amt());
        }
        coreWebApp.ModelBo.before_tax_amt(bt_amt_tot.toFixed(2));
        var rof_amt = Number.parseFloat(coreWebApp.ModelBo.round_off_amt());
        if (coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.tax_amt(0.00);
            coreWebApp.ModelBo.gross_amt((bt_amt_tot).toFixed(2));
            coreWebApp.ModelBo.misc_taxable_amt(supp_lc_amt_tot.toFixed(2));
            coreWebApp.ModelBo.total_amt((bt_amt_tot + supp_lc_amt_tot + rof_amt).toFixed(2));
        } else {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(0.00);
            coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
            coreWebApp.ModelBo.misc_taxable_amt(supp_lc_amt_tot.toFixed(2));
            coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot + supp_lc_amt_tot + rof_amt).toFixed(2));
        }

        //Total Advances
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (item) {
            adv_amt_tot += parseFloat(item.credit_amt());
        });
        coreWebApp.ModelBo.advance_amt(adv_amt_tot.toFixed(2));
        coreWebApp.ModelBo.net_amt((parseFloat(coreWebApp.ModelBo.total_amt()) - adv_amt_tot).toFixed(2));
    }
    sp.total_calc = total_calc;

    function redo_item_calc() {
        coreWebApp.ModelBo.annex_info.gst_tax_tran.removeAll();
        set_purchase_tax_ass_val();
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.stock_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    sp.redo_item_calc = redo_item_calc;

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    sp.material_filter = material_filter;

    function enable_mat_info(row) {
        return coreWebApp.ModelBo.doc_stage_id() == 'goods-receipt';
    }
    sp.enable_mat_info = enable_mat_info;

    function enable_doc_date(row) {
        return coreWebApp.ModelBo.doc_stage_id() == 'goods-receipt'
                || coreWebApp.ModelBo.doc_stage_id() == 'confirm-receipt';
    }
    sp.enable_doc_date = enable_doc_date;

    function fetch_mat_info(row) {
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
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    console.log(result.bar_code + ': ' + result.is_service);
                    row.is_service(result.is_service);
                    coreWebApp.lookupCache.add('material_type_id', result.material_type_id, result.mt_name);
                    row.material_type_id(result.material_type_id);
                    if (parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                        coreWebApp.lookupCache.add('material_id', result.material_id, result.mat_name);
                        row.material_id(result.mat_id);
                    }
                    coreWebApp.lookupCache.add('uom_id', result.uom_id, result.uom);
                    row.uom_id(result.uom_id);
                    if (parseFloat(coreWebApp.ModelBo.annex_info.ts_info.rate_pu()) > 0) {
                        row.rate(coreWebApp.ModelBo.annex_info.ts_info.rate_pu());
                    }
                    if (!coreWebApp.ModelBo.annex_info.bill_level_tax()) {
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
                    } else {
                        // Bill level tax also pending
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
                        set_purchase_tax_ass_val();
                    }
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
    sp.fetch_mat_info = fetch_mat_info;

    function st_tran_add(row) {
        sp.sl_no += 1;
        row.sl_no(sp.sl_no);
        set_default_sl(row);
        row.gtt_apply_itc(true);
    }
    sp.st_tran_add = st_tran_add;

    function st_tran_delete() {
        total_calc();
    }
    sp.st_tran_delete = st_tran_delete;

    function lc_tran_delete() {
        total_calc();
    }
    sp.lc_tran_delete = lc_tran_delete;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        coreWebApp.lookupCache.add('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
        row.stock_location_id(sl.stock_location_id());
    }

    function show_line_tax() {
        return !coreWebApp.ModelBo.annex_info.bill_level_tax();
    }
    sp.show_line_tax = show_line_tax;

    function tax_amt_override(row) {
        return row.tax_amt_ov();
    }
    sp.tax_amt_override = tax_amt_override;

    function is_service(row) {
        return row.is_service();
    }
    sp.is_service = is_service;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    sp.tax_amt_ov = tax_amt_ov;

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
    sp.liability_acc_enable = liability_acc_enable;

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
    sp.stock_lc_tax_enable = stock_lc_tax_enable;

    function set_purchase_tax_ass_val() {
        var map = new Map();
        // First summarise all tran items
        coreWebApp.ModelBo.stock_tran().forEach((item) => {
            if (item.gtt_gst_rate_id() != -1) {
                var key = item.gtt_hsn_sc_code() + ':' + item.gtt_gst_rate_id();
                if (typeof key !== "undefined") {
                    if (!map.has(key)) {
                        map.set(key, {
                            hsn_sc_code: item.gtt_hsn_sc_code(),
                            gst_rate_id: item.gtt_gst_rate_id(),
                            sgst_pcnt: item.gtt_sgst_pcnt(),
                            cgst_pcnt: item.gtt_cgst_pcnt(),
                            igst_pcnt: item.gtt_igst_pcnt(),
                            cess_pcnt: item.gtt_cess_pcnt(),
                            bt_amt: [item.bt_amt()]
                        });
                    } else {
                        map.get(key).bt_amt.push(parseFloat(item.bt_amt()));
                    }
                }
            }
        });

        // Add/Update gst Tax Summary
        var mkeys = map.keys();
        for (let mkey of mkeys) {
            var mi = map.get(mkey);
            var mi_found = false;
            coreWebApp.ModelBo.annex_info.gst_tax_tran().forEach((gtt) => {
                var hsn_id = gtt.hsn_sc_code() + ':' + gtt.gst_rate_id();
                if (hsn_id === mkey) {
                    fill_ass_val(gtt, mi);
                    mi_found = true;
                    return;
                }
            });
            if (!mi_found) {
                var new_ptax_row = coreWebApp.ModelBo.addNewRow('annex_info.gst_tax_tran', coreWebApp.ModelBo, true);
                new_ptax_row.hsn_sc_code(mi.hsn_sc_code);
                new_ptax_row.gst_rate_id(mi.gst_rate_id);
                new_ptax_row.sgst_pcnt(mi.sgst_pcnt);
                new_ptax_row.cgst_pcnt(mi.cgst_pcnt);
                new_ptax_row.igst_pcnt(mi.igst_pcnt);
                new_ptax_row.cess_pcnt(mi.cess_pcnt);
                new_ptax_row.apply_itc(true);
                fill_ass_val(new_ptax_row, mi);
                coreWebApp.afterNewRowAdded(false);
            }
        }

        // remove not required pruchase tax items
        for (var i = coreWebApp.ModelBo.annex_info.gst_tax_tran().length - 1; i >= 0; i--) {
            var pitem = coreWebApp.ModelBo.annex_info.gst_tax_tran()[i];
            var mitem = map.get(pitem.hsn_sc_code() + ':' + pitem.gst_rate_id());
            if (mitem == undefined) {
                // map does not contain this gst rate id. Therefore remove from summary
                var hsn_id = pitem.hsn_sc_code();
                var gst_rate_id = pitem.gst_rate_id();
                coreWebApp.ModelBo.annex_info.gst_tax_tran.remove(function (pt) {
                    return pt.hsn_sc_code() == hsn_id && pt.gst_rate_id() == gst_rate_id;
                });
            }
        }
        // apply sl no
        sp.gst_sl_no = 0;
        coreWebApp.ModelBo.annex_info.gst_tax_tran().forEach((gtt) => {
            sp.gst_sl_no += 1;
            gtt.sl_no(sp.gst_sl_no);
        });
    }

    function fill_ass_val(ptax_row, mi) {
        var ass_val = new Number(0.00);
        mi.bt_amt.forEach((av) => {
            ass_val += parseFloat(av);
        });
        var items_total_amt = parseFloat(coreWebApp.ModelBo.annex_info.items_total_amt());
        var disc_amt = parseFloat(coreWebApp.ModelBo.disc_amt());
        if (items_total_amt > 0 && disc_amt > 0) {
            ass_val -= (ass_val * disc_amt / items_total_amt).toFixed(2);
        }
        ptax_row.bt_amt(ass_val.toFixed(2));
    }

    function adv_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.stock_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.total_amt(),
                credit_amt_total_fc: coreWebApp.ModelBo.total_amt_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent   
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    sp.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function adv_alloc_clear_click() {
        coreWebApp.ModelBo.payable_ledger_alloc_tran.removeAll();
        total_calc();
    }
    sp.adv_alloc_clear_click = adv_alloc_clear_click;

    function po_sel() {
        if (coreWebApp.ModelBo.account_id() == -1) {
            coreWebApp.toastmsg('warning', 'PO Click Error', 'Select Supplier to view Purchase Orders.', false);
            return;
        }
        var opts = {
            voucher_id: coreWebApp.ModelBo.stock_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            supplier_id: coreWebApp.ModelBo.account_id(),
            stock_tran: coreWebApp.ModelBo.stock_tran,
            tran_item_calc_callback: item_calc,
            tran_add_callback: st_tran_add,
            fetch_mat_callback: fetch_mat_info,
            after_update: total_calc
        };
        core_sm.po_for_sp.po_sel_ui(opts);
    }
    sp.po_sel = po_sel;

    function enable_grn_close() {
        return coreWebApp.ModelBo.vallow_close();
    }
    sp.enable_grn_close = enable_grn_close;

    function btn_dcn_click() {
        var lnk = '?r=/core/st/form&formName=purchaseReturnGst/PurchaseReturnGstEditForm&formParams={"stock_id":"-1","doc_type":"PRV","for_pv":"' + coreWebApp.ModelBo.stock_id() + '"}';
        coreWebApp.rendercontents(lnk, 'details', 'contentholder', 'core_st.pr.after_load');
    }
    sp.btn_dcn_click = btn_dcn_click;

    function btn_dcn_visible() {
        if (coreWebApp.ModelBo.status() == 5 && coreWebApp.ModelBo.annex_info.dcn_ref_id() == '') {
            var filtered = ko.utils.arrayFilter(coreWebApp.ModelBo.stock_tran_qc(), function (itm) {
                return (parseFloat(itm.reject_qty()) > 0);
            });
            return filtered.length > 0;
        }
        return false;
    }
    sp.btn_dcn_visible = btn_dcn_visible;

    function dcn_ref_visible() {
        return coreWebApp.ModelBo.annex_info.dcn_ref_id() != '';
    }
    sp.dcn_ref_visible = dcn_ref_visible;

    function btn_pbc_visible() {
        stgs = ['confirm-receipt', 'book-purchase', 'post-purchase'];
        res = -1;
        res = stgs.indexOf(coreWebApp.ModelBo.doc_stage_id());
        if (res != -1) {
            return true;
        }
//        if (coreWebApp.ModelBo.status() == 5) {
//            return true;
//        }
        return false;
    }
    sp.btn_pbc_visible = btn_pbc_visible;

    function show_barcodeopts() {
        $.ajax({
            url: '?r=core/st/utils/view-spg-barcode',
            success: function (view) {
                $('#cformmain').hide();
                $('#bo-form').append(view);
                getSpgItemBCData();
            }
        });
    }
    sp.show_barcodeopts = show_barcodeopts;

    function close_barcodeopts() {
        $('#spg-print-view').remove();
        $('#cformmain').show();
        return false;
    }
    sp.close_barcodeopts = close_barcodeopts;

    sp.spg_bc_items = {};
    function getSpgItemBCData() {
        var spgid = coreWebApp.ModelBo.__doc_id();
        $.ajax({
            url: '?r=core/st/utils/get-spg-bc-data',
            method: 'GET',
            dataType: 'json',
            data: {
                spg_id: spgid
            },
            success: function (matdata) {
                fill_from_spg(matdata);
                sp.spg_bc_items = ko.mapping.fromJS(matdata);
                $('#sitem-data').DataTable({
                    data: sp.spg_bc_items(),
                    order: [],
                    columns: [
                        {data: "material_code", title: "BarCode", width: "10%"},
                        {data: "material_type", title: "Type", width: "15%"},
                        {data: "material_name", title: "Stock Item", width: "40%"},
                        {data: "received_qty", title: "Received Qty", width: "10%", className: "dt-right",
                            render: function (cellData) {
                                return coreWebApp.formatNumber(cellData(), 3);
                            }
                        },
                        {data: "uom_desc", title: "UoM", width: "10%", className: "dt-center"},
                        {data: "labelcount", title: "Label Cnt", width: "10%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input scale="0" data-bind="numericValue: labelcount">');
                                ko.applyBindings(rowData, $(td)[0]);
                            }
                        }
                    ],
                    deferRender: true,
                    scrollY: $('#content-root').height() * .75,
                    scrollCollapse: true,
                    scroller: true
                });
                $('#spg-print-view').find('.dataTables_scrollHead').children().find('th').css('color', 'white');
            }
        });
    }
    sp.getSpgItemBCData = getSpgItemBCData;

    function fill_from_spg(matdata) {
        $.each(coreWebApp.ModelBo.stock_tran(), function (id, ord) {
            var mat_id = ord.material_id();
            var mt = matdata.find(function (mitm) {
                return mitm.material_id == mat_id;
            });
            if (mt != undefined) {
                mt.labelcount = ord.received_qty;
            }
        });
    }

    function setSpgItemBCData() {
        var spgid = coreWebApp.ModelBo.__doc_id();
        var spgdata = sp.spg_bc_items();
        $.ajax({
            url: '?r=core/st/utils/set-spg-bc-data',
            method: 'POST',
            dataType: 'json',
            data: {
                spg_id: spgid, spg_data: spgdata
            },
            success: function (mdata) {
                var bc_id = mdata.stock_barcode_print_id;
                var printurl = '?r=cwf%2FfwShell%2Fjreport%2Fvchreporttopdf';//$('#divprintdata').attr('printurl');
                var pdata = new Object();
                $('#divprintdata :input').each(function () {
                    var attrid = $(this).attr('id');
                    if (typeof attrid != 'undefined' && attrid.match('^divp_')) {
                        var fldid = ($(this).attr('id')).replace('divp_', '');
                        pdata[fldid] = $(this).val();
                    }
                });
                if ('__bo' in coreWebApp.ModelBo) {
                    pdata['bo_id'] = coreWebApp.ModelBo.__bo();
                }
                if ('status' in coreWebApp.ModelBo) {
                    pdata['status'] = coreWebApp.ModelBo.status();
                }
                pdata['xmlPath'] = $('#barcodexml').val();
                pdata['pbc_id'] = bc_id;
                var jdata = JSON.stringify(pdata);
                $.ajax({
                    url: printurl,
                    type: 'POST',
                    jsonp: true,
                    beforeSend: function () {
                        coreWebApp.startloading();
                    },
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    data: {rptparams: jdata},
                    success: function (result, status, jqXHR) {
                        if (jqXHR.getResponseHeader("Output-Type") == "text/html") {
                            var printWindow = window.open('', '', 'height=0,width=0');
                            if (printWindow === null || typeof (printWindow) === 'undefined') {
                                coreWebApp.toastmsg('warning', 'Info', 'Please enable pop-ups and try again', false);
                            } else if (typeof (printWindow) === 'BrowserWindowProxy') {

                            } else {
                                printWindow.focus();
                                printWindow.document.write(result);
                                printWindow.document.close();
                                printWindow.print();
                                printWindow.close();
                            }
                        } else if (jqXHR.getResponseHeader("Output-Type") == "application/json") {
                            var rptInfo = $.parseJSON(result);
//                            var pwin = window.open('');
//                            if (pwin === null || typeof (pwin) === 'undefined') {
//                                coreWebApp.toastmsg('warning', 'Info', 'Please enable pop-ups and try again', false);
//                                return;
//                            }
                            var pwin = window.open(rptInfo.ReportRenderedPath);
//                            var htmldoc = $('<html></html>');
//                            var head = $('<head>' + rptInfo.PageStyle + '</head>');
//                            htmldoc.append(head);
//                            var data = rptInfo.Data;
//                            // This should be a simple parent div to ensure that it does not take printer page space
//                            var rptParent = $('<div id="rptParent" name="rptParent"></div>');
//                            for (i = 0; i < rptInfo.PageCount; i++) {
//                                var rptPage = $('<div id="rptPage' + i + '" class="print-format"></div>');
//                                var rptContainer = $('<div id="t' + i + '"></div>');
//                                var prop = 'Page' + i;
//                                var pagelink = data[prop];
//                                $.ajax({
//                                    async: false,
//                                    url: pagelink,
//                                    type: 'GET',
//                                    success: function (pagedata) {
//                                        var phtml = $(pagedata);
//                                        phtml.find('img').each(function () {
//                                            $(this).attr('src', rptInfo.ReportRenderedPath.substring(1, rptInfo.ReportRenderedPath.length) + '/' + $(this).attr('src'));
//                                        });
//                                        var t = phtml.find('table[class=jrPage]');
//                                        t.attr('id', 'jrPage-' + i);
//                                        rptContainer.append(t);
//                                    }
//                                })
//                                rptPage.append(rptContainer);
//                                rptParent.append(rptPage);
//                                // set the last page margin to Zero.
//                                // This would suppress the blank page being printed
//                                if (i == rptInfo.PageCount - 1) {
//                                    rptPage.attr('style', "margin-bottom: 0px;");
//                                }
//                            }
//                            var body = $('<body></body>');
//                            body.append(rptParent);
//                            if (!coreWebApp.detectIE()) {
//                                var script = pwin.document.createElement('script');
//                                script.type = 'text/javascript';
//                                script.text = 'function pageLoaded() {' +
//                                        '     setTimeout(function() {' +
//                                        '        window.print(); ' +
//                                        '        window.close(); ' +
//                                        '     }, 500); ' +
//                                        '};' +
//                                        'window.onload = pageLoaded;';
//                                body.append(script);
//                            }
//                            htmldoc.append(body);
//                            pwin.document.write(htmldoc.html());
//                            pwin.document.close();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                        coreWebApp.stoploading();
                    }
                });
                return false;
            },
            error: function (mdata) {
                return false;
            }
        });
        return false;
    }
    sp.setSpgItemBCData = setSpgItemBCData;

    function gst_rate_select(row) {
        gstOpts = {
            txn_type: core_tx.gst.TXN_PURCH,
            origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
            target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            is_ctp: coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_COMPOS,
            //gst_hsn_info: gst_hsn_info,
            row: row,
            after_update: redo_item_calc
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tx.gst_rate.select_gst_rate(gstOpts);
    }
    sp.gst_rate_select = gst_rate_select;

    function ts_info_visible() {
        return false;
        // To be removed. We use qc information to populate fat/snf
        //return coreWebApp.ModelBo.annex_info.ts_info.apply_ts();
    }
    sp.ts_info_visible = ts_info_visible;

    function ts_info_select() {
        if (coreWebApp.ModelBo.annex_info.ts_info.apply_ts()) {
            $.ajax({
                url: '?r=prod/utils/get-supp-rate',
                method: 'GET',
                dataType: 'json',
                data: {supp_id: coreWebApp.ModelBo.account_id()},
                success: function (result) {
                    if (result.length > 0) {
                        coreWebApp.ModelBo.annex_info.ts_info.supp_rate(result[0]['purch_rate']);
                    }
                }
            });
        }
    }
    sp.ts_info_select = ts_info_select;

    function ts_info_calc() {
        var ts_pcnt = parseFloat(coreWebApp.ModelBo.annex_info.ts_info.fat_pcnt())
                + parseFloat(coreWebApp.ModelBo.annex_info.ts_info.snf_pcnt());
        coreWebApp.ModelBo.annex_info.ts_info.ts_pcnt(ts_pcnt.toFixed(2));
        var supp_rate = (ts_pcnt * parseFloat(coreWebApp.ModelBo.annex_info.ts_info.supp_rate())) / 100;
        coreWebApp.ModelBo.annex_info.ts_info.rate_pu(supp_rate.toFixed(2));
    }
    sp.ts_info_calc = ts_info_calc;

    function ref_alloc(row, ptran) {
        var opts = {
            voucher_id: coreWebApp.ModelBo.stock_id(),
            account_id: row.account_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),  
            branch_id: coreWebApp.ModelBo.branch_id(), 
            parent_row: row,
            ptran: ptran
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_ac.ref_alloc.sel_ref_alloc(opts);
    }
    sp.ref_alloc = ref_alloc;

    function enable_ref_alloc(row) {
        return row.req_alloc();
    }
    sp.enable_ref_alloc = enable_ref_alloc;

    function visible_ref_alloc_tran() {
        return false;
    }
    sp.visible_ref_alloc_tran = visible_ref_alloc_tran;

    function allow_add_delete(dataItem) {
        if (coreWebApp.ModelBo.doc_stage_id() === 'goods-receipt') {
            return true;
        }
        coreWebApp.toastmsg('message', 'Stock Items', 'Cannot modify stock items after goods-receipt stage', false);
        return false;
    }
    sp.allow_add_delete = allow_add_delete;

}(window.core_st.sp));
