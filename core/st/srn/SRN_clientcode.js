/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_srn = {};
(function (core_srn) {
    var stop_calc = false;
    var skip_ts_fetch = false;
    core_srn.sl_no = 0;
    
    function after_load() {
        core_srn.sl_no = coreWebApp.ModelBo.stock_tran().length;
    }
    core_srn.after_load = after_load;
    
    function fetch_mat_info(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var vat_type_id = parseInt(coreWebApp.ModelBo.vat_type_id());
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-info',
            type: 'GET',
            data: {bar_code: bar_code, mat_id: mat_id, vat_type_id: vat_type_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function(resultdata) {
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
//                    row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
//                    if (parseFloat(result.bal_qty) > 0) {
//                        row.has_bal(true);
//                    } else {
//                        row.has_bal(false);
//                    }
                    row.issued_qty(1);
                    row.rate(result.sale_rate);
                    row.disc_percent(result.disc_pcnt);
                    skip_ts_fetch = true;
                    row.tax_schedule_id(result.tax_schedule_id);
                    coreWebApp.trigger_change('tax_schedule_id', result.tax_schedule_id, result.tax_schedule_desc);
                    skip_ts_fetch = false;
                    row.en_tax_type(result.en_tax_type);
                    row.tax_pcnt(result.tax_pcnt);
                    stop_calc = false;
                    item_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function(data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_srn.fetch_mat_info = fetch_mat_info;

    function item_calc(row) {
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var sale_rate = Number.parseFloat(row.rate());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        
        var bt_amt = (received_qty * sale_rate);
        row.bt_amt(bt_amt.toFixed(2));
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
        if(en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = bt_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
        }
        // Always read the tax_amt to avoid float/rounding diff
        tax_amt = parseFloat(row.tax_amt());
        row.item_amt(Number.parseFloat((received_qty * sale_rate) + tax_amt).toFixed(2));
        core_srn.total_calc();
        stop_calc = false;
    }
    core_srn.item_calc = item_calc;

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var bt_amt_tot = new Number(0.00);
        var adv_settle = new Number(0.00);
        var rof_amt =  new Number(0.00);
        // Total each stock item
        core_srn.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_srn.sl_no += 1;
            row.sl_no(core_srn.sl_no);
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            item_amt_tot += Number.parseFloat(row.item_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.debit_amt());
        });
        var rof_amt = Number.parseFloat((item_amt_tot).toFixed(0)) - (item_amt_tot);
        coreWebApp.ModelBo.round_off_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.gross_amt(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.total_amt((item_amt_tot + rof_amt).toFixed(2));
        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.net_amt((item_amt_tot + rof_amt - adv_settle).toFixed(2));
    }
    core_srn.total_calc = total_calc;

    function st_tran_delete() {
        total_calc();
    }
    core_srn.st_tran_delete = st_tran_delete;

    function st_tran_add(row) {
        core_srn.sl_no += 1;
        row.sl_no(core_srn.sl_no);
        set_default_sl(row);
    }
    core_srn.st_tran_add = st_tran_add;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_srn.material_filter = material_filter;
    
    function tax_schedule_change(dataItem) {
        if (skip_ts_fetch)
            return;
        debugger;
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
    core_srn.tax_schedule_change = tax_schedule_change;

    function tax_pcnt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false;
    }
    core_srn.tax_pcnt_enable = tax_pcnt_enable;

    function tax_amt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false;
    }
    core_srn.tax_amt_enable = tax_amt_enable;
    
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
                debit_amt_total: coreWebApp.ModelBo.total_amt(),
                dc : 'D',
                debit_amt_total_fc: coreWebApp.ModelBo.total_amt_fc(),
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_srn.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

}(window.core_srn));
