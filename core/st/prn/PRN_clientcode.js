/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_prn = {};
(function (core_prn) {
    stop_calc = false;
    skip_ts_fetch = false;   
    core_prn.sl_no = 0;
    
    
    function after_load() {
        core_prn.sl_no = coreWebApp.ModelBo.stock_tran().length;
    }
    core_prn.after_load = after_load;
    
    function material_filter(fltr, dataItem) {
        if(parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_prn.material_filter = material_filter;
    
    function fetch_mat_info(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var vat_type_id = coreWebApp.ModelBo.vat_type_id();
        $.ajax({
            url: '?r=core/st/form/get-mat-info-purchase',
            type: 'GET',
            data: { bar_code: bar_code, mat_id: mat_id, vat_type_id: vat_type_id },
            success: function(resultdata) {
                var result = $.parseJSON(resultdata);
                if (typeof result.mat_id !== 'undefined') {
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    row.material_type_id(result.material_type_id);
                    coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    if(parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                        row.material_id(result.mat_id);
                        coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                    }
                    row.uom_id(result.uom_id);
                    coreWebApp.trigger_change('uom_id', result.uom_id, result.uom);
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
    core_prn.fetch_mat_info = fetch_mat_info;
    
    function item_calc(row) {
        console.log('item_calc')
        if(stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var pur_rate = Number.parseFloat(row.rate());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        var disc_amt = Number.parseFloat(row.disc_amt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_pcnt = parseFloat(row.tax_pcnt());
        var tax_amt = new Number(0.00);
        var bt_amt = (issued_qty * pur_rate) - disc_amt;
        row.bt_amt(bt_amt.toFixed(2));        
        if(en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = bt_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
            tax_amt = parseFloat(row.tax_amt());
        }
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        core_prn.total_calc();
        stop_calc = false; 
    }
    core_prn.item_calc = item_calc;    
    
    function total_calc() {
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        // Total each stock item
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function(row) {
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
    }
    core_prn.total_calc = total_calc;
    
    function tax_schedule_change(dataItem) {
        if(skip_ts_fetch) return;
        console.log('tax_schedule_change');
        
        if(parseInt(dataItem.tax_schedule_id()) === -1) {
            dataItem.tax_pcnt(0);
            dataItem.tax_amt(0);
            return;
        }

        var url = '?r=core/st/form/get-item-tax-info';
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            data: { tax_schedule_id: dataItem.tax_schedule_id() },
            success: function (result) {
                if(typeof result.en_tax_type !== 'undefined') {
                    dataItem.en_tax_type(result.en_tax_type);
                    dataItem.tax_pcnt(result.tax_perc);
                    // decide which tax item is changing
                    if(typeof dataItem.material_id !== 'undefined') {
                        item_calc(dataItem);
                    } 
                }
            },
            error: function () {
                coreWebApp.toastmsg('warning', 'Failed to fetch selected tax information');
            }
        });
    }
    core_prn.tax_schedule_change = tax_schedule_change;

    function tax_amt_enable(dataItem) {
        console.log('tax_amt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false;
    }
    core_prn.tax_amt_enable = tax_amt_enable;

    function tax_pcnt_enable(dataItem) {
        console.log('tax_pcnt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false;
    }
    core_prn.tax_pcnt_enable = tax_pcnt_enable;
    
    
    function st_tran_delete() {
        total_calc();
    }
    core_prn.st_tran_delete = st_tran_delete;
    
    function lc_tran_delete() {
        total_calc();
    }
    core_prn.lc_tran_delete = lc_tran_delete;
    
    
    function liability_acc_enable(dataItem) {   
       if(typeof dataItem.supplier_paid === 'undefined') return;
       if(dataItem.supplier_paid() === false){
           return true;            
       }
       else {
           dataItem.account_affected_id(-1);
           return false;
       }
    };    
    core_prn.liability_acc_enable=liability_acc_enable;
    
    function lc_item_calc(row) {
        if(stop_calc) return;
        stop_calc = true;
        var debit_amt = parseFloat(row.debit_amt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_pcnt = parseFloat(row.tax_pcnt());
        var tax_amt = new Number(0.00);
        if(en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = debit_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
        }
        total_calc();
        stop_calc = false;
    }
    core_prn.lc_item_calc = lc_item_calc;
    
    function stock_lc_tax_enable(dataItem) {   
       if(typeof dataItem.supplier_paid === 'undefined') return;
       if(dataItem.supplier_paid() === true) {
           return true;
       }
       else {
           dataItem.tax_schedule_id(-1);
           dataItem.en_tax_type(-1);
           dataItem.tax_pcnt(0);
           dataItem.tax_amt(0.00);
           return false;
       }
    };    
    core_prn.stock_lc_tax_enable = stock_lc_tax_enable;
    
    function apply_itc(data) {
        total_calc();
    }
    core_prn.apply_itc = apply_itc;
    
    function adv_alloc_click(){
       if(coreWebApp.ModelBo.account_id() === -1){
            coreWebApp.toastmsg('warning','Advance Click Error','Select Supplier to view advance.',false);
            return;
        }
        else{
            var opts = {                
                voucher_id: coreWebApp.ModelBo.stock_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.gross_amt(),
                credit_amt_total_fc: 0,                
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran,    // The observable array is sent   
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }    
    core_prn.adv_alloc_click = adv_alloc_click;
    
    function adv_alloc_after_update() {
        total_calc();
    }
    
    function adv_alloc_clear_click() {
        coreWebApp.ModelBo.payable_ledger_alloc_tran.removeAll();
        total_calc();
    }
    core_prn.adv_alloc_clear_click = adv_alloc_clear_click;    
    
    function enable_visible_fc(dataItem) { 
        if(parseFloat(coreWebApp.ModelBo.fc_type_id()) !=0){
            return true;            
        }
        else {
            return false;
        }
    }
    core_prn.enable_visible_fc=enable_visible_fc
    

    function st_tran_add(row) {
        core_prn.sl_no += 1;
        row.sl_no(core_prn.sl_no);
        set_default_sl(row);
    }
    core_prn.st_tran_add = st_tran_add;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }
    
    function fetch_mat_info(row) {
        debugger;
         var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var vat_type_id = coreWebApp.ModelBo.vat_type_id();
        $.ajax({
            url: '?r=core/st/form/get-mat-info-purchase',
            type: 'GET',
            data: { bar_code: bar_code, mat_id: mat_id, vat_type_id: vat_type_id },
            success: function(resultdata) {
                var result = $.parseJSON(resultdata);
                if (typeof result.mat_id !== 'undefined') {
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    row.material_type_id(result.material_type_id);
                    coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    if(parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                        row.material_id(result.mat_id);
                        coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                    }
                    row.uom_id(result.uom_id);
                    coreWebApp.trigger_change('uom_id', result.uom_id, result.uom);
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
    core_prn.fetch_mat_info = fetch_mat_info;
    
}(window.core_prn));
