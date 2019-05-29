// Declare core_st Namespace
window.core_stock_gst_inv = {};
(function (core_stock_gst_inv) {
    var stop_calc = false;
    var skip_ts_fetch = false;
    core_stock_gst_inv.sl_no = 0;
    core_stock_gst_inv.bb_sl_no = 1;

    function after_load() {
        core_stock_gst_inv.sl_no = coreWebApp.ModelBo.stock_tran().length;
        core_stock_gst_inv.bb_sl_no = coreWebApp.ModelBo.inv_bb().length + 1;
    }
    core_stock_gst_inv.after_load = after_load;

    function fetch_cust_info() {
        core_ar.cust_salesman(coreWebApp.ModelBo.account_id());
        opts = {
            cust_id: coreWebApp.ModelBo.account_id(),
            after_update: fetch_cust_info_after_update
        };
        core_ar.get_address(opts);
    }
    core_stock_gst_inv.fetch_cust_info = fetch_cust_info;

    function fetch_cust_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.customer_address(opts.result.addr);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_city(opts.result.city);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_pin(opts.result.pin);
        } else {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(-1);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin("");
            coreWebApp.ModelBo.customer_address("");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_city("");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_pin("");
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            $('#vat_type_id').trigger('change');
            if (coreWebApp.ModelBo.stock_tran().length > 0) {
                gstOpts.tran = coreWebApp.ModelBo.stock_tran;
                gstOpts.row_update_callback = item_calc;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    core_stock_gst_inv.fetch_cust_info_after_update = fetch_cust_info_after_update;

    function select_cust_addr(opts) {
        var opts = {
            cust_id: coreWebApp.ModelBo.account_id(),
            after_update: select_cust_addr_after_update
        };
        core_ar.select_address(opts);
    }
    core_stock_gst_inv.select_cust_addr = select_cust_addr;

    function select_cust_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.customer_address(opts.result.addr);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_city(opts.result.city);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_pin(opts.result.pin);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.vat_type_id();
            coreWebApp.ModelBo.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.stock_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.stock_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
            $('#vat_type_id').trigger('change');
        }
    }
    core_stock_gst_inv.select_cust_addr_after_update = select_cust_addr_after_update;

    function cust_addr_editable() {
        return coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin().length == 2;
    }
    core_stock_gst_inv.cust_addr_editable = cust_addr_editable;

    function fetch_mat_info(row, el) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-sale',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code,
                mat_id: mat_id,
                stock_loc_id: sl_id,
                doc_date: coreWebApp.ModelBo.doc_date(),
                cust_id: coreWebApp.ModelBo.account_id()
            },
            success: function (result) {
                var gst_hsn_info = $.parseJSON(result.gst_hsn_info);
                if (row.reference_tran_id() == '') {
                    if (typeof result.mat_id !== 'undefined') {
                        stop_calc = true;
                        row.bar_code(result.bar_code);
                        row.material_type_id(result.material_type_id);
                        coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                        if (parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                            row.material_id(result.mat_id);
                            coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                        }
                        row.uom_id(result.uom_id);
                        coreWebApp.trigger_change('uom_id', result.uom_id, result.uom);
                        row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                        if (parseFloat(result.bal_qty) > 0) {
                            row.has_bal(true);
                        } else {
                            row.has_bal(false);
                        }
                        row.issued_qty(1);
                        row.rate(result.sale_rate);
                        row.disc_percent(result.disc_pcnt);
                        if (gst_hsn_info == undefined) {
                            coreWebApp.toastmsg('error', 'Missing HSN Data', 'GST/HSN Data not found for selected material</br>'+result.mat_name, false);
                            stop_calc = false;
                            return;
                        }
                        // Get Gst info
                        gstOpts = {
                            txn_type: core_tx.gst.TXN_SALE,
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
                } else {
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    row.material_type_id(result.material_type_id);
                    coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                    row.uom_id(result.uom_id);
                    coreWebApp.trigger_change('uom_id', result.uom_id, result.uom);
                    row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                    if (parseFloat(result.bal_qty) > 0) {
                        row.has_bal(true);
                    } else {
                        row.has_bal(false);
                    }
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        gst_hsn_info: gst_hsn_info,
                        row: row
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);
                    stop_calc = false;
                    item_calc(row);
                    stop_calc = false;
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_stock_gst_inv.fetch_mat_info = fetch_mat_info;


    function item_calc(row) {
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var sale_rate = Number.parseFloat(row.rate());
        var disc_pcnt = Number.parseFloat(row.disc_percent());
        if (row.disc_is_value() == false) {
            row.disc_amt((issued_qty * sale_rate * disc_pcnt / 100).toFixed(2));
        }
        var disc_amt = Number.parseFloat(row.disc_amt()); // always pickup disc to avoid float errors
        row.bt_amt(((issued_qty * sale_rate) - disc_amt).toFixed(2));
        var bt_amt = Number.parseFloat(row.bt_amt());
        incd_cost_apport();
        var other_amt = parseFloat(row.other_amt());
        if (other_amt > 0) {
            // recacl tax of each row if incd_costs exist
            coreWebApp.ModelBo.stock_tran().forEach((x) => {
                core_tx.gst.item_gtt_calc({
                    bt_amt: parseFloat(x.bt_amt()) + parseFloat(x.other_amt()),
                    row: x
                });
                x.tax_amt((parseFloat(x.gtt_sgst_amt()) + parseFloat(x.gtt_cgst_amt())
                        + parseFloat(x.gtt_igst_amt()) + parseFloat(x.gtt_cess_amt())).toFixed(2));
                x.item_amt((parseFloat(x.bt_amt()) + parseFloat(x.other_amt()) + parseFloat(x.tax_amt())).toFixed(2));
            });
        } else {
            // recalc tax of current row only
            core_tx.gst.item_gtt_calc({
                bt_amt: bt_amt + other_amt,
                row: row
            });
        }
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        tax_amt = parseFloat(row.tax_amt()); // always pickup tax_amt to avoid float errors
        row.item_amt((bt_amt + parseFloat(row.other_amt()) + tax_amt).toFixed(2));
        core_stock_gst_inv.total_calc();
        stop_calc = false;
    }
    core_stock_gst_inv.item_calc = item_calc;

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var misc_non_tax_amt = new Number(0.00);
        var adv_settle = new Number(0.00);
        var bb_amt_tot = new Number(0.00);
        // Total each stock item
        core_stock_gst_inv.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_stock_gst_inv.sl_no += 1;
            row.sl_no(core_stock_gst_inv.sl_no);
            item_amt_tot += Number.parseFloat(row.item_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        // Total Buybacks
        core_stock_gst_inv.bb_sl_no = 1;
        ko.utils.arrayForEach(coreWebApp.ModelBo.inv_bb(), function (row) {
            row.sl_no(core_stock_gst_inv.bb_sl_no++);
            bb_amt_tot += Number.parseFloat(row.bt_amt());
        });
        misc_non_tax_amt -= parseFloat(bb_amt_tot.toFixed(2));
        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.debit_amt());
        });
        // set fetch tax_amt to avoid float error
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        tax_amt_tot = parseFloat(coreWebApp.ModelBo.tax_amt());
        // set fetch gross_amt to avoid float error
        coreWebApp.ModelBo.gross_amt(item_amt_tot.toFixed(2));
        item_amt_tot = parseFloat(coreWebApp.ModelBo.gross_amt());
        // set fetch misc_non_tax to avoid float error
        coreWebApp.ModelBo.misc_non_taxable_amt(misc_non_tax_amt.toFixed(2));
        misc_non_tax_amt = parseFloat(coreWebApp.ModelBo.misc_non_taxable_amt());
        // set fetch round off to avoid float error
        var rof_amt = Number.parseFloat((item_amt_tot + misc_non_tax_amt).toFixed(0)) - (item_amt_tot + misc_non_tax_amt);
        coreWebApp.ModelBo.round_off_amt(rof_amt.toFixed(2));
        rof_amt = parseFloat(coreWebApp.ModelBo.round_off_amt());
        // set total_amt
        coreWebApp.ModelBo.total_amt((item_amt_tot + misc_non_tax_amt + rof_amt).toFixed(2));
        // set fetch adv_amt to avoid float error
        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        adv_settle = parseFloat(coreWebApp.ModelBo.advance_amt());
        // set net_amt
        coreWebApp.ModelBo.net_amt((item_amt_tot + misc_non_tax_amt + rof_amt - adv_settle).toFixed(2));
    }
    core_stock_gst_inv.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.stock_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    core_stock_gst_inv.redo_item_calc = redo_item_calc;

    function st_tran_add(row) {
        core_stock_gst_inv.sl_no += 1;
        row.sl_no(core_stock_gst_inv.sl_no);
        set_default_sl(row);
    }
    core_stock_gst_inv.st_tran_add = st_tran_add;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }

    function incd_cost_apport() {
        var incd_amt_tot = 0.00;
        coreWebApp.ModelBo.annex_info.incd_cost().forEach((x) => {
            incd_amt_tot += parseFloat(x.incd_amt());
        });
        coreWebApp.ModelBo.annex_info.incd_amt_tot(incd_amt_tot.toFixed(2));
        if (incd_amt_tot == 0) { // if incd is zero, reset everything to zero and exit
            coreWebApp.ModelBo.stock_tran().forEach((x) => {
                x.other_amt(0.00);
            });
            return;
        }
        var items_tot = 0.00;
        coreWebApp.ModelBo.stock_tran().forEach((x) => {
            items_tot += parseFloat(x.bt_amt());
        });
        if (items_tot > 0) {
            coreWebApp.ModelBo.stock_tran().forEach((x) => {
                x.other_amt((parseFloat(x.bt_amt()) * incd_amt_tot / items_tot).toFixed(2));
            });
        }
    }
    core_stock_gst_inv.incd_cost_apport = incd_cost_apport;

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_stock_gst_inv.material_filter = material_filter;

    function disc_is_value_change(dataItem) {
        if (dataItem.disc_is_value()) {
            dataItem.disc_percent(0);
        }
        core_stock_gst_inv.item_calc(dataItem);
    }
    core_stock_gst_inv.disc_is_value_change = disc_is_value_change;

    function disc_pcnt_enable(dataItem) {
        return !dataItem.disc_is_value();
    }
    core_stock_gst_inv.disc_pcnt_enable = disc_pcnt_enable;

    function disc_amt_enable(dataItem) {
        return dataItem.disc_is_value();
    }
    core_stock_gst_inv.disc_amt_enable = disc_amt_enable;

    function adv_alloc_click() {
        if (parseInt(coreWebApp.ModelBo.account_id()) === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Customer to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                account_id: coreWebApp.ModelBo.account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                dc: 'C',
                debit_amt_total: coreWebApp.ModelBo.total_amt(),
                debit_amt_total_fc: coreWebApp.ModelBo.total_amt_fc(),
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_stock_gst_inv.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function select_so() {
        coreWebApp.toastmsg('message', 'Feature Activation', 'This feature is currently not available', false);
        return;
    }
    core_stock_gst_inv.select_so = select_so;

    function so_visible(dataItem) {
        return coreWebApp.ModelBo.reference_id() === '' ? false : true;
    }
    core_stock_gst_inv.so_visible = so_visible;

    function so_enable(dataItem) {
        return coreWebApp.ModelBo.reference_id() === '' ? true : false;
    }
    core_stock_gst_inv.so_enable = so_enable;

    function mat_info_editable(row) {
        if (coreWebApp.ModelBo.doc_stage_id() === 'pick-list' && row.reference_id() === '') {
            return true;
        }
        return false;
    }
    core_stock_gst_inv.mat_info_editable = mat_info_editable;

    function war_info_editable(row) {
        if (coreWebApp.ModelBo.doc_stage_id() === 'pick-list') {
            return true;
        }
        return false;
    }
    core_stock_gst_inv.war_info_editable = war_info_editable;

    function allow_delete(dataItem) {
        if (coreWebApp.ModelBo.doc_stage_id() === 'pick-list') {
            return true;
        }
        coreWebApp.toastmsg('message', 'Stock Items', 'Cannot modify stock items after pick-list stage', false);
        return false;
    }
    core_stock_gst_inv.allow_delete = allow_delete;

    function allow_add(dataItem) {
        if (coreWebApp.ModelBo.doc_stage_id() === 'pick-list') {
            if (coreWebApp.ModelBo.reference_id() === '') {
                return true;
            }
        }
        coreWebApp.toastmsg('message', 'Stock Items', 'Cannot modify stock items after pick-list stage', false);
        return false;
    }
    core_stock_gst_inv.allow_add = allow_add;

    function tax_schedule_change(dataItem) {
        if (skip_ts_fetch)
            return;
        if (parseInt(dataItem.tax_schedule_id()) === -1) {
            dataItem.tax_pcnt(0);
            dataItem.tax_amt(0);
            return;
        }

        var url = '?r=core/st/form/get-item-tax-info';
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            data: {tax_schedule_id: dataItem.tax_schedule_id()},
            success: function (result) {
                if (typeof result.en_tax_type !== 'undefined') {
                    dataItem.en_tax_type(result.en_tax_type);
                    dataItem.tax_pcnt(result.tax_perc);
                    item_calc(dataItem);
                }
            },
            error: function () {
                coreWebApp.toastmsg('warning', 'Failed to fetch selected tax information');
            }
        });
    }
    core_stock_gst_inv.tax_schedule_change = tax_schedule_change;

    function tax_pcnt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false;
    }
    core_stock_gst_inv.tax_pcnt_enable = tax_pcnt_enable;

    function tax_amt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false;
    }
    core_stock_gst_inv.tax_amt_enable = tax_amt_enable;

    function view_war_info(row) {
        //Todo: invoke the popup form to edit/modify warranty information
        if (row['material_id']() == -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Material to add War Info.', false);
        } else {
            $.ajax({
                url: '?r=core/st/form/war-info-reqd',
                type: 'GET',
                data: {'material_id': row['material_id']()},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        if (1 == 1) {  //jsonResult['has_war'] === 'true'
                            var opts = {
                                material_id: row['material_id'](),
                                stock_tran_war: row['stock_tran_war'], // The observable array is sent
                                module: 'core/st',
                                alloc_view: 'stockInvoice/WarInfo',
                                stock_tran_row: row,
                                call_init: view_war_info_init,
                                call_update: view_war_info_update
                            };
                            console.log('showAllocV2');
                            coreWebApp.showAllocV2(opts);
                        } else {
                            coreWebApp.toastmsg('warning', 'View War Info', 'War Info is not available for the material.', false);
                        }
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'View War Info', 'Failed with errors on server', true);
                }
            });
        }
    }
    core_stock_gst_inv.view_war_info = view_war_info;

    function view_war_info_init(opts, after_init) {
        var war_info = new function () {
            self = this;
        }
        war_info.stock_war_temp = build_war_temp();
        for (var p = 0; p < opts.stock_tran_war().length; p++)
        {
            var bal_row = opts.stock_tran_war()[p];
            var nr = war_info.stock_war_temp.addNewRow();
            nr.material_id(bal_row['material_id']());
            nr.mfg_serial(bal_row['mfg_serial']());
            nr.mfg_date(bal_row['mfg_date']());
        }
        opts.model = war_info;
    }
    core_stock_gst_inv.view_war_info_init = view_war_info_init;

    function view_war_info_update(opts) {
        if (parseInt(opts.stock_tran_row.issued_qty()) != opts.model.stock_war_temp().length) {
            coreWebApp.toastmsg('warning', 'Qty mismatch', 'Serial count does not match with issued qty.');
            return false;
        }

        for (var p = 0; p < opts.model.stock_war_temp().length; ++p) {
            if (opts.model.stock_war_temp()[p]['mfg_serial']() == '') {
                coreWebApp.toastmsg('warning', 'Missing info', 'Mfg Serial No. is required.');
                return false;
            }
        }

        opts.stock_tran_war.removeAll();
        for (var p = 0; p < opts.model.stock_war_temp().length; ++p) {
            var rlt = opts.model.stock_war_temp()[p];
            var nr = coreWebApp.ModelBo.addNewRow('stock_tran_war', opts.stock_tran_row);
            nr.mfg_date(rlt.mfg_date());
            nr.mfg_serial(rlt.mfg_serial());
            nr.material_id(opts.material_id);
        }
        opts.stock_tran_war.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_stock_gst_inv.view_war_info_update = view_war_info_update;

    function build_war_temp() {
        var stock_war_temp = ko.observableArray();
        stock_war_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.material_id = ko.observable(0);
            cobj.mfg_serial = ko.observable('');
            cobj.mfg_date = ko.observable('1970-01-01');
            stock_war_temp.push(cobj);
            return cobj;
        };
        return stock_war_temp;
    }
    core_stock_gst_inv.build_war_temp = build_war_temp;

    // Select Shipping Address
    function is_ship_addr_change(dataItem) {
//        if (coreWebApp.ModelBo.annex_info !== 'undefined') {
//            if (coreWebApp.ModelBo.annex_info.ship_info.is_ship_addr() == true && coreWebApp.ModelBo.annex_info.ship_info.ship_addr() == '') {
//                var opts = {
//                    customer_id: coreWebApp.ModelBo.account_id(),
//                    cust_billing_addr: coreWebApp.ModelBo.annex_info.ship_info.ship_addr, // The observable array is sent 
//                    addr_type_id: (coreWebApp.ModelBo.company_id() * 1000000) + 2 // Billing Address
//                };
//                core_ar.get_cust_billing_addr(opts);
//            }
//            if (coreWebApp.ModelBo.annex_info.ship_info.is_ship_addr() == false) {
//                coreWebApp.ModelBo.annex_info.ship_info.ship_addr('');
//            }
//        }
    }
    core_stock_gst_inv.is_ship_addr_change = is_ship_addr_change;

    function ship_addr_enable(dataItem) {
        return coreWebApp.ModelBo.annex_info.gst_output_info.is_ship_consign();
    }
    core_stock_gst_inv.ship_addr_enable = ship_addr_enable;

    function stock_tran_war_hide() {
        return false;
    }
    core_stock_gst_inv.stock_tran_war_hide = stock_tran_war_hide;

    function fetch_bb_mat_info(row, el) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-purch',
            type: 'GET',
            data: {bar_code: bar_code, mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (resultdata) {
                var result = $.parseJSON(resultdata);
                if (typeof result.mat_id !== 'undefined') {
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    row.material_type_id(result.material_type_id);
                    coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    if (parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                        row.material_id(result.mat_id);
                        coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                    }
                    row.uom_id(result.uom_id);
                    coreWebApp.trigger_change('uom_id', result.uom_id, result.uom);
                    skip_ts_fetch = true;
//                    row.tax_schedule_id(result.tax_schedule_id);
//                    coreWebApp.trigger_change('tax_schedule_id', result.tax_schedule_id, result.tax_schedule_desc);
//                    skip_ts_fetch = false;
//                    row.en_tax_type(result.en_tax_type);
//                    row.tax_pcnt(result.tax_pcnt);
                    stop_calc = false;
                    item_bb_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_stock_gst_inv.fetch_bb_mat_info = fetch_bb_mat_info;

    stop_bb_calc = false;
    function item_bb_calc(row) {
        if (stop_bb_calc) {
            return;
        }
        stop_bb_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var sale_rate = Number.parseFloat(row.rate());
//        var tax_pcnt = 0;
        var bt_amt = (received_qty * sale_rate);
        row.bt_amt(bt_amt.toFixed(2));
//        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
//        if (en_tax_type === 0 || en_tax_type === 1) {
//            tax_amt = bt_amt * tax_pcnt / 100;
//            row.tax_amt(tax_amt.toFixed(2));
//        } else {
//            row.tax_pcnt(0.00);
//            tax_amt = parseFloat(row.tax_amt());
//        }
        row.tax_amt(tax_amt.toFixed(2));
        row.item_amt(Number.parseFloat((received_qty * sale_rate) + tax_amt).toFixed(2));
        core_stock_gst_inv.total_calc();
        stop_bb_calc = false;
    }
    core_stock_gst_inv.item_bb_calc = item_bb_calc;

    function inv_bb_add(row) {
        row.sl_no(core_stock_gst_inv.bb_sl_no++);
        set_default_sl(row);
    }
    core_stock_gst_inv.inv_bb_add = inv_bb_add;

    function so_sel() {
        if (coreWebApp.ModelBo.account_id() == -1) {
            coreWebApp.toastmsg('warning', 'SO Click Error', 'Select Customer to view Sales Orders.', false);
            return;
        }
        var opts = {
            voucher_id: coreWebApp.ModelBo.stock_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            customer_id: coreWebApp.ModelBo.account_id(),
            stock_tran: coreWebApp.ModelBo.stock_tran,
            tran_item_calc_callback: item_calc,
            tran_add_callback: st_tran_add,
            fetch_mat_callback: fetch_mat_info,
            after_update: total_calc
        };
        crm.so_for_si.so_sel_ui(opts);
    }
    core_stock_gst_inv.so_sel = so_sel;

    function inv_bb_delete() {
        core_stock_gst_inv.bb_sl_no = 0;
        coreWebApp.ModelBo.inv_bb().forEach(function (row) {
            core_stock_gst_inv.bb_sl_no += 1;
            row.sl_no(core_stock_gst_inv.bb_sl_no);
        });
        total_calc();
    }
    core_stock_gst_inv.inv_bb_delete = inv_bb_delete;

    function ewb_visible(dataItem) {
        return coreWebApp.ModelBo.status() != 5 ? false : true;
    }
    core_stock_gst_inv.ewb_visible = ewb_visible;

    function get_ewb_file() {
        if (coreWebApp.ModelBo.status() != 5) { // Allow for Json download only when invoice is posted
            coreWebApp.toastmsg('warning', 'Get JSON file', 'Post the invoice before getting JSON file');
            return;
        }
        var dataParams = {
            doc_id: coreWebApp.ModelBo.__doc_id(),
            doc_type: coreWebApp.ModelBo.doc_type()
        };
        $.ajax({
            url: '?r=core/tx/ewb/get-ewb-json',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                if (jdata.status != 'OK') {
                    coreWebApp.toastmsg('warning', 'EWB JSON error', 'EWB file data issues.', false);
                    var brules = jdata.brules;
                    var litems = '<strong>Broken Rules</strong><div style="margin-top:5px;">';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    litems += '</div>';
                    $('#brokenrules').append(litems);
                    $('#divbrule').show();
                } else {
                    $('#divbrule').hide();
                    // Always download the file
                    var link = document.createElement('a');
                    link.setAttribute("href", jdata.filePath);
                    link.setAttribute("id", "ewb_file_link");
                    link.setAttribute("download", jdata.fileName);
                    var cnt = document.getElementById('content-root');
                    cnt.appendChild(link);
                    link.click();
                }
            }
        });
    }
    core_stock_gst_inv.get_ewb_file = get_ewb_file;

    function update_ewb_no() {
        var patt = /[A-Za-z0-9/-]+$/;
        var res = patt.test(coreWebApp.ModelBo.annex_info.ewb_no());
        if (!res) {
            coreWebApp.toastmsg('warning', 'EWB error', 'EWB no cannot be blank.', false);
            var litems = '<strong>Broken Rules</strong><div style="margin-top:5px;">';
            litems += "<li> EWB no is blank </li>";
            litems += '</div>';
            $('#brokenrules').append(litems);
            $('#divbrule').show();
            return;
        } else {
            $('#brokenrules').html('');
            $('#divbrule').hide();
        }
        var dataParams = {
            ewb_no: coreWebApp.ModelBo.annex_info.ewb_no(),
            doc_id: coreWebApp.ModelBo.stock_id(),
            doc_type: coreWebApp.ModelBo.doc_type()
        };
        $.ajax({
            url: '?r=core/tx/ewb/update-ewb-in-doc',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                if (jdata.status == 'OK') {
                    coreWebApp.toastmsg('success', '', 'EWay Bill No Updated.', false);
                }
            }
        });
    }
    core_stock_gst_inv.update_ewb_no = update_ewb_no;
    
    function fetch_avl_qty(row) {
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-sale',
            type: 'GET',
            dataType: 'json',
            data: {
                mat_id: mat_id,
                stock_loc_id: sl_id,
                doc_date: coreWebApp.ModelBo.doc_date(),
                cust_id: coreWebApp.ModelBo.account_id()
            },
            success: function (result) {               
                    if (typeof result.mat_id !== 'undefined') {
                        row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                        if (parseFloat(result.bal_qty) > 0){
                            row.has_bal(true);
                        } else {
                            row.has_bal(false);
                        }        
                    }
                    else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_stock_gst_inv.fetch_avl_qty = fetch_avl_qty;

}(window.core_stock_gst_inv));
