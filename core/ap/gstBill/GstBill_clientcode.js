// Declare core_ap Namespace
typeof window.core_ap == 'undefined' ? window.core_ap = {} : '';
window.core_ap.gst_bill = {};

(function (gst_bill) {

    gst_bill.sl_no = 0;

    function afterload() {
        gst_bill.sl_no = coreWebApp.ModelBo.bill_tran().length;
    }
    gst_bill.afterload = afterload;

    function fetch_supp_info() {
        // Fetch supplier tds/unstl adv. amt.
        $.ajax({
            url: '?r=core/ap/form/fetch-supp-name',
            type: 'GET',
            dataType: 'json',
            data: {
                supplier_id: coreWebApp.ModelBo.supplier_id(),
                doc_date: coreWebApp.ModelBo.doc_date()
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.annex_info.is_tds_applied(jsonResult['is_tds_applied']);
                    coreWebApp.ModelBo.unstl_adv_amt(jsonResult['unstl_adv_amt']);
                    coreWebApp.lookupCache.add('btt_person_type_id', jsonResult['person_type_id'], jsonResult['person_type']);
                    coreWebApp.ModelBo.btt_person_type_id(jsonResult['person_type_id']);
                    coreWebApp.lookupCache.add('btt_section_id', jsonResult['section_id'], jsonResult['section']);
                    coreWebApp.ModelBo.btt_section_id(jsonResult['section_id']);
                    if (jsonResult['rate_info'].length > 0) {
                        coreWebApp.ModelBo.btt_tds_base_rate_perc(jsonResult['rate_info'][0]['base_rate_perc']);
                        coreWebApp.ModelBo.btt_tds_base_rate_amt(0);
                        coreWebApp.ModelBo.btt_tds_base_rate_amt_fc(0);
                        coreWebApp.ModelBo.btt_tds_ecess_perc(jsonResult['rate_info'][0]['ecess_perc']);
                        coreWebApp.ModelBo.btt_tds_ecess_amt(0);
                        coreWebApp.ModelBo.btt_tds_ecess_amt_fc(0);
                        coreWebApp.ModelBo.btt_tds_surcharge_perc(jsonResult['rate_info'][0]['surcharge_perc']);
                        coreWebApp.ModelBo.btt_tds_surcharge_amt(0);
                        coreWebApp.ModelBo.btt_tds_surcharge_amt_fc(0);
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        // Fetch GST related information
        opts = {
            supp_id: coreWebApp.ModelBo.supplier_id(),
            after_update: fetch_supp_info_after_update
        };
        core_ap.get_address(opts);
    }
    gst_bill.fetch_supp_info = fetch_supp_info;

    function fetch_supp_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.lookupCache.add('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_address(opts.result.addr);
        } else {
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(-1);
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
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            $('#vat_type_id').trigger('change');
            if (coreWebApp.ModelBo.bill_tran().length > 0) {
                gstOpts.tran = coreWebApp.ModelBo.bill_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    gst_bill.fetch_supp_info_after_update = fetch_supp_info_after_update;

    function select_supp_addr() {
        var opts = {
            supp_id: coreWebApp.ModelBo.supplier_id(),
            after_update: select_supp_addr_after_update
        };
        core_ap.select_address(opts);
    }
    gst_bill.select_supp_addr = select_supp_addr;

    function select_supp_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.lookupCache.add('annex_info.gst_input_info.supplier_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(opts.result.gst_state_id);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_input_info.supplier_address(opts.result.addr);
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
            var old_vat_type_id = coreWebApp.ModelBo.vat_type_id();
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            $('#vat_type_id').trigger('change');
            if (coreWebApp.ModelBo.bill_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.bill_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    gst_bill.select_supp_addr_after_update = select_supp_addr_after_update;

    function item_calc(row) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {// Foreign Currency            
            row.debit_amt((parseFloat(row.debit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            row.debit_amt_fc(0);
        }
        row.gtt_bt_amt(parseFloat(row.debit_amt()));
        var bt_amt = parseFloat(row.gtt_bt_amt());
        // This is GST
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });        
        
        var tax_amt = new Number(0.00);
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt()) 
                        + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        tax_amt = parseFloat(row.tax_amt()); // always pickup tax_amt to avoid float errors
        
        total_calc();
    }
    gst_bill.item_calc = item_calc;

    function bill_tran_delete() {
        total_calc();
    }
    gst_bill.bill_tran_delete = bill_tran_delete;

    function total_calc(dataItem) {
        var gtt_bt_amt_tot = new Number(0.00);
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var tax_amt_tot_fc = new Number(0.00);
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);

        gst_bill.sl_no = 0;
        // Total each bill item
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_tran(), function (row) {
            gst_bill.sl_no += 1;
            row.sl_no(gst_bill.sl_no);
            gtt_bt_amt_tot += Number.parseFloat(row.gtt_bt_amt());
            item_amt_tot += Number.parseFloat(row.debit_amt());
            item_amt_tot_fc += Number.parseFloat(row.debit_amt_fc());
            tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        });

        coreWebApp.ModelBo.annex_info.bt_amt(gtt_bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.bt_amt_fc(item_amt_tot_fc.toFixed(2));

        if (coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.annex_info.tax_amt(0.00);
            coreWebApp.ModelBo.annex_info.tax_amt_fc(0.00);
        } else {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_tax_amt(0.00);
            coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.annex_info.tax_amt_fc(tax_amt_tot_fc.toFixed(2));
        }

        var gross_amt = Number.parseFloat(coreWebApp.ModelBo.annex_info.bt_amt()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt());
        var gross_amt_fc = Number.parseFloat(coreWebApp.ModelBo.annex_info.bt_amt_fc()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt_fc());
        var rf_amt = parseFloat(coreWebApp.ModelBo.round_off_amt());
        var rf_amt_fc = parseFloat(coreWebApp.ModelBo.round_off_amt_fc());

        coreWebApp.ModelBo.annex_info.bill_total((gross_amt + rf_amt).toFixed(2));
        coreWebApp.ModelBo.annex_info.bill_total_fc((gross_amt_fc + rf_amt_fc).toFixed(2));

        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.credit_amt());
            adv_settle_fc += Number.parseFloat(row.credit_amt_fc());
        });

        coreWebApp.ModelBo.annex_info.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.annex_info.advance_amt_fc(adv_settle_fc.toFixed(2));

        coreWebApp.ModelBo.net_bill_amt((Number.parseFloat(coreWebApp.ModelBo.annex_info.bill_total()) - Number.parseFloat(coreWebApp.ModelBo.bill_amt())).toFixed(2));
        coreWebApp.ModelBo.net_bill_amt_fc((Number.parseFloat(coreWebApp.ModelBo.annex_info.bill_total_fc()) - Number.parseFloat(coreWebApp.ModelBo.bill_amt_fc())).toFixed(2));
    }
    gst_bill.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.bill_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    gst_bill.redo_item_calc = redo_item_calc;

    function visible_tds(dataItem) {
        if(coreWebApp.ModelBo.annex_info.is_tds_applied()){
            return true;
        }
        return false;
    }
    gst_bill.visible_tds = visible_tds;

    function exch_rate_changed(dataItem) {
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_tran(), function (a) {
            item_calc(a);
        });
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (a) {
            if (coreWebApp.ModelBo.fc_type_id() == 0) {
                a.credit_amt_fc(0);
            } else {
                a.credit_amt((parseFloat(a.credit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
            }
        });
        bill_fc_changed(dataItem);
        total_calc();
    }
    gst_bill.exch_rate_changed = exch_rate_changed;

    function bill_fc_changed(dataItem) {
        dataItem.bill_amt(parseFloat(dataItem.bill_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate()));
        total_calc();
    }
    gst_bill.bill_fc_changed = bill_fc_changed;

    function bill_fc_tran_changed(dataItem) {
        dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
    }
    gst_bill.bill_fc_tran_changed = bill_fc_tran_changed;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    gst_bill.enable_visible_fc = enable_visible_fc;

    function adv_alloc_click() {
        if (coreWebApp.ModelBo.supplier_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.bill_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.supplier_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.bill_amt(),
                credit_amt_total_fc: coreWebApp.ModelBo.bill_amt_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    gst_bill.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function bill_view_gl_init() {
        core_ac.gl_distribution('ap.bill_control', coreWebApp.ModelBo.bill_id());
    }
    gst_bill.bill_view_gl_init = bill_view_gl_init;


    function bill_view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_ap.gst_bill.bill_view_gl_init');
    }
    gst_bill.bill_view_gl = bill_view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.bill_id() != '' && coreWebApp.ModelBo.bill_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    gst_bill.visible_gl_distribution = visible_gl_distribution;
    
    function visible_po_select(dataItem) {
        return coreWebApp.ModelBo.select_po_visible();
    }    
    gst_bill.visible_po_select = visible_po_select;
    
    function po_sel() {
        if (coreWebApp.ModelBo.supplier_id() == -1) {
            coreWebApp.toastmsg('warning', 'PO Click Error', 'Select Supplier to view Purchase Orders.', false);
            return;
        }
        var opts = {
            voucher_id: coreWebApp.ModelBo.bill_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            supplier_id: coreWebApp.ModelBo.supplier_id(),
            bill_tran: coreWebApp.ModelBo.bill_tran,
            tran_item_calc_callback: item_calc,
            tran_add_callback: bill_tran_add,
            after_update: redo_item_calc
        };
        core_sm.po_for_gstbill.po_sel_ui(opts);
    }
    gst_bill.po_sel = po_sel;
    
    function apply_rc() {
        return coreWebApp.ModelBo.annex_info.gst_rc_info.apply_rc();
    }
    gst_bill.apply_rc = apply_rc;

    function apply_rc_update(dataItem) {
        if (!dataItem()) {
            coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id(-1);
        }
        total_calc();
    }
    gst_bill.apply_rc_update = apply_rc_update;

    function rc_sec_filter(fltr) {
        if (coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_SGST_CGST) {
            fltr += ' rc_sec_id in (93,94)';
        } else if (coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_IGST
                || coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_IMPORT
                || coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_SEZ) {
            fltr += ' rc_sec_id in (53,54)';
        } else if (coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_COMPOS) {
            fltr += ' rc_sec_id = -1';
        }
        return fltr;
    }
    gst_bill.rc_sec_filter = rc_sec_filter;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    gst_bill.tax_amt_ov = tax_amt_ov;

    function bill_tran_add(row) {
        gst_bill.sl_no += 1;
        row.sl_no(gst_bill.sl_no);
        row.gtt_apply_itc(true);
    }
    gst_bill.bill_tran_add = bill_tran_add;

    function fetch_hsn_info(row) {
        var hsn_sc_id = row.hsn_sc_id();
        $.ajax({
            url: '?r=core/ap/form/get-hsn-gst-info',
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
                        is_ctp: coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_COMPOS,
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
    gst_bill.fetch_hsn_info = fetch_hsn_info;

    function sub_head_alloc_click(row) {
        if (row['account_id']() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.bill_id(),
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
    gst_bill.sub_head_alloc_click = sub_head_alloc_click;

    function sub_head_alloc_after_update() {
        total_calc();
    }

    function adv_alloc_clear_click() {
        coreWebApp.ModelBo.payable_ledger_alloc_tran.removeAll();
        total_calc();
    }
    gst_bill.adv_alloc_clear_click = adv_alloc_clear_click;

    function tds_base_desc(row) {
        return "TDS @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_base_rate_perc()), 0) + "%";
    }
    gst_bill.tds_base_desc = tds_base_desc;

    function tds_ecess_desc(row) {
        return "E-cess @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_ecess_perc()), 0) + "%";
    }
    gst_bill.tds_ecess_desc = tds_ecess_desc;

    function tds_surch_desc(row) {
        return "Surch. @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_surcharge_perc()), 0) + "%";
    }
    gst_bill.tds_surch_desc = tds_surch_desc;

    function tds_total(row) {
        var tds = parseFloat(row.btt_tds_base_rate_amt()) + parseFloat(row.btt_tds_ecess_amt()) + parseFloat(row.btt_tds_surcharge_amt());
        return coreWebApp.formatNumber(tds, 2);
    }
    gst_bill.tds_total = tds_total;

    function select_sec_info(row) {
        opts = {
            doc_date: coreWebApp.ModelBo.doc_date(),
            person_type_id: coreWebApp.ModelBo.btt_person_type_id(),
            row: row,
            after_update: reset_tds_rate
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tds.tds_sec.select_tds_sec(opts);
    }
    gst_bill.select_sec_info = select_sec_info;

    function reset_tds_rate() {
    }
    
    function select_hsn(row) {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select GST Rate', 'Select Supplier State before selecting GST rate.</br>For unregistered supplier, enter the state code only');
            return;
        }
        opts = {
            txn_type: core_tx.gst.TXN_PURCH,
            origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
            target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            is_ctp: coreWebApp.ModelBo.vat_type_id() == core_tx.gst.PURCH_COMPOS,
            //gst_hsn_info: gst_hsn_info,
            row: row,
            after_update: redo_item_calc
        };
        core_tx.hsn.select_hsn(opts);
    }
    gst_bill.select_hsn = select_hsn;
    
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
    gst_bill.gst_rate_select = gst_rate_select;
    

    function ib_branch_filter(fltr) {
        fltr += " gst_state_id = " + coreWebApp.branch_gst_info.gst_state_id;
        return fltr;
    }
    gst_bill.ib_branch_filter = ib_branch_filter;

    function enable_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.annex_info.is_inter_branch() == true) {
            return true;
        } else {
            return false;
        }
    }
    gst_bill.enable_branch = enable_branch;    

    function visible_gtt_ovrd(dataItem) {
        return coreWebApp.ModelBo.allow_gtt_ovrd();
    }
    gst_bill.visible_gtt_ovrd = visible_gtt_ovrd;
  

    function disable_gtt_ovrd(dataItem) {
        return !coreWebApp.ModelBo.allow_gtt_ovrd();
    }
    gst_bill.disable_gtt_ovrd = disable_gtt_ovrd;
  
}(window.core_ap.gst_bill));
