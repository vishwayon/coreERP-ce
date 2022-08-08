// Declare core_ap Namespace
window.core_gst_inv = {};
(function (core_gst_inv) {
    core_gst_inv.sl_no = 0;
    function after_load() {
        console.log('inv_afterload');

        var start_date = $('#doc_date').attr('start_date');
        if (moment(new Date(start_date)) < moment(new Date('01/07/2017'))) {
            $('#doc_date').datepicker('remove');
            $('#doc_date').attr('start_date', '01/07/2017');
            coreWebApp.applyDatepicker($('#doc_date'));
        }
        core_gst_inv.sl_no = coreWebApp.ModelBo.invoice_tran().length;
    }

    core_gst_inv.after_load = after_load;

    function visible_unstl_adv(dataItem) {
        return coreWebApp.ModelBo.status() != 5;
    }
    core_gst_inv.visible_unstl_adv = visible_unstl_adv;

    function inv_tran_add(row) {
        core_gst_inv.sl_no += 1;
        row.sl_no(core_gst_inv.sl_no);
    }
    core_gst_inv.inv_tran_add = inv_tran_add;

    function fetch_cust_info(dataItem) {
        // Fetch customer unstl adv. amt.
        $.ajax({
            url: '?r=core/ar/form/fetch-cust-adv',
            type: 'GET',
            dataType: 'json',
            data: {
                customer_id: coreWebApp.ModelBo.customer_id(),
                doc_date: coreWebApp.ModelBo.doc_date()
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.unstl_adv_amt(jsonResult['unstl_adv_amt']);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        core_ar.cust_salesman(coreWebApp.ModelBo.customer_id());
        opts = {
            cust_id: coreWebApp.ModelBo.customer_id(),
            after_update: fetch_cust_info_after_update
        };
        core_ar.get_address(opts);
    }

    core_gst_inv.fetch_cust_info = fetch_cust_info;

    function fetch_cust_info_after_update(opts) {
        if(typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.invoice_address(opts.result.addr);
        }else{
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(-1);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin("");
            coreWebApp.ModelBo.invoice_address("");
        }
        // update vat_type
        if(coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            $('#vat_type_id').trigger('change');
        }
        if (coreWebApp.ModelBo.invoice_tran().length > 0) {
                gstOpts.tran = coreWebApp.ModelBo.invoice_tran;
                gstOpts.call_back = total_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
    }
    core_gst_inv.fetch_cust_info_after_update = fetch_cust_info_after_update;
    
    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }

    core_gst_inv.enable_visible_fc = enable_visible_fc

    function inv_fc_changed(dataItem) {
        ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (a) {
            item_calc(a);
        });
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (a) {
            if (coreWebApp.ModelBo.fc_type_id() == 0) {
                a.debit_amt_fc(0);
            } else {
                a.debit_amt((parseFloat(a.debit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
            }
        });
        total_calc();
    }
    core_gst_inv.inv_fc_changed = inv_fc_changed;

    function income_type_account_combo_filter(fltr) {
        var income_type_id = coreWebApp.ModelBo.income_type_id();
        fltr = " account_id in (Select account_id from ar.income_type_tran where income_type_id=" + income_type_id + ")";
        return fltr;
    }

    core_gst_inv.income_type_account_combo_filter = income_type_account_combo_filter;


    function item_calc(row) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {// Foreign Currency            
            row.credit_amt((parseFloat(row.credit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            row.credit_amt_fc(0);
        }
        var bt_amt = parseFloat(row.credit_amt());
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });

        total_calc();
    }
    core_gst_inv.item_calc = item_calc;


    function adv_alloc_click() {
        if (coreWebApp.ModelBo.customer_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Customer to view advance.', false);
            return;
        } else {
//            var debit_total = new Number();
//            var debit_total_fc = new Number();

//            ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (item) {
//                debit_total += new Number(item.credit_amt());
//                debit_total_fc += new Number(item.credit_amt_fc());
//            });

            var opts = {
                voucher_id: coreWebApp.ModelBo.invoice_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                account_id: coreWebApp.ModelBo.customer_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                dc: 'C',
                debit_amt_total: coreWebApp.ModelBo.invoice_amt(),
                debit_amt_total_fc: coreWebApp.ModelBo.invoice_amt_fc(),
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_gst_inv.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var tax_amt_tot_fc = new Number(0.00);
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);
        var invoice_amt = new Number(0.00);
        var invoice_amt_fc = new Number(0.00);

        // Total each stock item
        core_gst_inv.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (row) {
            core_gst_inv.sl_no += 1;
            item_amt_tot += Number.parseFloat(row.credit_amt());
            item_amt_tot_fc += Number.parseFloat(row.credit_amt_fc());

            tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
            row.sl_no(core_gst_inv.sl_no);
            row.tax_amt(parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt()));
        });
        
        coreWebApp.ModelBo.annex_info.bt_amt(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.bt_amt_fc(item_amt_tot_fc.toFixed(2));

        coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.tax_amt_fc(tax_amt_tot_fc.toFixed(2));
 
        invoice_amt = Number.parseFloat(coreWebApp.ModelBo.annex_info.bt_amt()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt());
        invoice_amt_fc = Number.parseFloat(coreWebApp.ModelBo.annex_info.bt_amt_fc()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt_fc());
        var rf_amt = Number.parseFloat(invoice_amt.toFixed(coreWebApp.ModelBo.invoice_rf_to())) - invoice_amt;
        var rf_amt_fc = Number.parseFloat(invoice_amt_fc.toFixed(coreWebApp.ModelBo.invoice_rf_to())) - invoice_amt_fc;
        coreWebApp.ModelBo.annex_info.round_off_amt(rf_amt.toFixed(2));
        coreWebApp.ModelBo.annex_info.round_off_amt_fc(rf_amt_fc.toFixed(2));

        coreWebApp.ModelBo.invoice_amt((invoice_amt + rf_amt).toFixed(2));
        coreWebApp.ModelBo.invoice_amt_fc((invoice_amt_fc + rf_amt_fc).toFixed(2));
        
        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.debit_amt());
            adv_settle_fc += Number.parseFloat(row.debit_amt_fc());
        });

        coreWebApp.ModelBo.annex_info.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.annex_info.advance_amt_fc(adv_settle_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.net_credit_amt((Number.parseFloat(coreWebApp.ModelBo.invoice_amt()) - Number.parseFloat(coreWebApp.ModelBo.annex_info.advance_amt())).toFixed(2));
        coreWebApp.ModelBo.annex_info.net_credit_amt_fc((Number.parseFloat(coreWebApp.ModelBo.invoice_amt_fc()) - Number.parseFloat(coreWebApp.ModelBo.annex_info.advance_amt_fc())).toFixed(2));

    }
    core_gst_inv.total_calc = total_calc;

    function inv_view_gl_init() {
        core_ac.gl_distribution('ar.invoice_control', coreWebApp.ModelBo.invoice_id());
    }
    core_gst_inv.inv_view_gl_init = inv_view_gl_init;


    function inv_view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_gst_inv.inv_view_gl_init');
    }

    core_gst_inv.inv_view_gl = inv_view_gl;


    function cancelAllocUpdate() {
    }
    core_gst_inv.cancelAllocUpdate = cancelAllocUpdate;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.invoice_id() != '' && coreWebApp.ModelBo.invoice_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    core_gst_inv.visible_gl_distribution = visible_gl_distribution

    function select_cust_addr(opts) {
        var opts = {
            cust_id: coreWebApp.ModelBo.customer_id(),
            after_update: select_cust_addr_after_update
        };
        core_ar.select_address(opts);
    }
    core_gst_inv.select_cust_addr = select_cust_addr;

    function select_cust_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.invoice_address(opts.result.addr);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                call_back: total_calc
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.vat_type_id();
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.invoice_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.invoice_tran;
                core_tx.gst.reapply_gtt(gstOpts);
            }
            $('#vat_type_id').trigger('change');
        }
    }
    core_gst_inv.select_cust_addr_after_update = select_cust_addr_after_update;

    function fetch_acc_info(row, el) {
        var account_id = row.account_id();
        var income_type_id = parseInt(coreWebApp.ModelBo.income_type_id());
        var doc_type = coreWebApp.ModelBo.doc_type();
        $.ajax({
            url: '?r=core/ar/form/get-income-type-hsn-gst-info',
            type: 'GET',
            dataType: 'json',
            data: {account_id: account_id, doc_type: doc_type, income_type_id: income_type_id},
            success: function (gst_hsn_info) {
                if (typeof gst_hsn_info.hsn_sc_code !== 'undefined') {
                    stop_calc = true;
                    // Get Gst info
                    gstOpts = {
                        vat_type_id: coreWebApp.ModelBo.vat_type_id(),
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
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
    core_gst_inv.fetch_acc_info = fetch_acc_info;

    function inv_tran_delete() {
        total_calc();
    }
    core_gst_inv.inv_tran_delete = inv_tran_delete;    

    function adv_alloc_clear_click() {
        coreWebApp.ModelBo.receivable_ledger_alloc_tran.removeAll();
        total_calc();
    }
    core_gst_inv.adv_alloc_clear_click = adv_alloc_clear_click;


    function select_hsn(row) {
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select GST Rate', 'Select Customer State before selecting GST rate.</br>For unregistered customer, enter the state code only');
            return;
        }
        opts = {
            txn_type: core_tx.gst.TXN_SALE,
            origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
            row: row,
            after_update: redo_item_calc
        };
        core_tx.hsn.select_hsn(opts);
    }
    core_gst_inv.select_hsn = select_hsn;

    function gst_rate_select(row) {
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select GST Rate', 'Select Customer State before selecting GST rate.</br>For unregistered customer, enter the state code only');
            return;
        }
        gstOpts = {
            txn_type: core_tx.gst.TXN_SALE,
            origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
            row: row,
            after_update: redo_item_calc
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tx.gst_rate.select_gst_rate(gstOpts);
    }
    core_gst_inv.gst_rate_select = gst_rate_select;
    
    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.invoice_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    core_gst_inv.redo_item_calc = redo_item_calc;
}(window.core_gst_inv));