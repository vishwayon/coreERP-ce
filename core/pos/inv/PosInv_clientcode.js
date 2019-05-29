typeof window.core_pos === 'undefined' ? window.core_pos={} : '';
window.core_pos.inv = {};

(function (inv) {
    var stop_calc = false;
    var skip_ts_fetch = false;
    inv.sl_no = 1;
    inv.bb_sl_no = 1;
    
    function after_load() {
        inv.sl_no = coreWebApp.ModelBo.inv_tran().length + 1;
        inv.bb_sl_no = coreWebApp.ModelBo.inv_bb().length + 1
    }
    inv.after_load = after_load;
    
    function fetch_mat_info(row, el) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var vat_type_id = parseInt(coreWebApp.ModelBo.vat_type_id());
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/pos/form/get-mat-info',
            type: 'GET',
            data: { bar_code: bar_code, mat_id: mat_id, vat_type_id: vat_type_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date() },
            success: function(resultdata) {
                var result = $.parseJSON(resultdata);
                if (typeof result.mat_id !== 'undefined') {
                    stop_calc = true;
                    row.bar_code(result.bar_code);
                    row.material_type_id(result.material_type_id);
                    inv.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    if(parseInt(row.material_id()) !== parseInt(result.mat_id)) {
                        row.material_id(result.mat_id);
                        inv.trigger_change('material_id', result.material_id, result.mat_name);
                    }
                    row.uom_id(result.uom_id);
                    inv.trigger_change('uom_id', result.uom_id, result.uom);
                    row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                    if (parseFloat(result.bal_qty) > 0) {
                        row.has_bal(true);
                    } else {
                        row.has_bal(false);
                    }
                    row.issued_qty(1);
                    row.rate(result.sale_rate);
                    row.disc_pcnt(result.disc_pcnt);
                    skip_ts_fetch = true;
                    row.tax_schedule_id(result.tax_schedule_id);
                    inv.trigger_change('tax_schedule_id', result.tax_schedule_id, result.tax_schedule_desc);
                    skip_ts_fetch = false;
                    row.en_tax_type(result.en_tax_type);
                    row.tax_pcnt(result.tax_pcnt);
                    stop_calc = false;
                    item_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected materail', false);
                }
            },
            error: function(data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    inv.fetch_mat_info = fetch_mat_info;
    
    function trigger_change(elid, valueid, dispText) {
        if(typeof dispText !== 'undefined') {
            var el = $('#'+elid);
            var lookupid = $(el).attr('data-NamedLookup') + '|' + $(el).attr('data-DisplayMember') + '|'
                            + $(el).attr('data-ValueMember') + '|' + $(el).attr('id');
            var pld = {
                lookupid: lookupid,
                valueid: valueid,
                dispText: dispText
            }; 
            coreWebApp.ModelBo.preLookupData.push(pld);
        }
        var items = $('[id="' + elid + '"]');
        $.each(items, function() {
            $(this).trigger('change');
        });
    }
    inv.trigger_change = trigger_change;
    
    function item_calc(row) {
        if(stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var sale_rate = Number.parseFloat(row.rate());
        var disc_pcnt = Number.parseFloat(row.disc_pcnt());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        var disc_amt = new Number(0.00);
        if(row.disc_is_value()) {
            disc_amt = parseFloat(row.disc_amt());
        } else {
            disc_amt = parseFloat((issued_qty * sale_rate * disc_pcnt / 100).toFixed(2));
            row.disc_amt(Number.parseFloat(disc_amt).toFixed(2));
        }
        var bt_amt = (issued_qty * sale_rate) - disc_amt;
        row.bt_amt(bt_amt.toFixed(2));
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
        if(en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = bt_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
            tax_amt = parseFloat(row.tax_amt());
        }
        tax_amt = parseFloat(tax_amt.toFixed(2)); // Tax is rounded off for preceision
        row.item_amt(Number.parseFloat((issued_qty * sale_rate) - disc_amt + tax_amt).toFixed(2));
        inv.total_calc();
        stop_calc = false;
    }
    inv.item_calc = item_calc;
    
    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var bb_amt_tot = new Number(0.00);
        inv.sl_no = 1;
        ko.utils.arrayForEach(coreWebApp.ModelBo.inv_tran(), function(row) {
            row.sl_no(inv.sl_no++);
            item_amt_tot += Number.parseFloat(row.item_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        coreWebApp.ModelBo.item_amt_tot(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt_tot(tax_amt_tot.toFixed(2));
        inv.bb_sl_no = 1;
        ko.utils.arrayForEach(coreWebApp.ModelBo.inv_bb(), function(row) {
            row.sl_no(inv.bb_sl_no++);
            bb_amt_tot += Number.parseFloat(row.bt_amt());
        });
        var nt_amt = -bb_amt_tot;
        var rof_amt = Number.parseFloat((item_amt_tot + nt_amt).toFixed(0)) - (item_amt_tot + nt_amt);
        coreWebApp.ModelBo.nt_amt(nt_amt.toFixed(2));
        coreWebApp.ModelBo.rof_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.inv_amt((item_amt_tot + nt_amt + rof_amt).toFixed(2));
    }
    inv.total_calc = total_calc;
    
    function calc_settle_totals() {
        var inv_tot = new Number(coreWebApp.ModelBo.inv_amt());
        var settle_tot = new Number(0.00);
        if(coreWebApp.ModelBo.inv_settle().length > 0) {
            var is = coreWebApp.ModelBo.inv_settle()[0];
            settle_tot = Number.parseFloat(is.cash_amt()) + Number.parseFloat(is.cheque_amt()) + Number.parseFloat(is.card_amt()) + Number.parseFloat(is.customer_amt());
            if(inv_tot === settle_tot) {
                 $('#btn_settle_print').removeAttr('disabled');
                 $('#btn_settle_print').addClass('btn-success');
            } else {
                 $('#btn_settle_print').attr('disabled', true);
            }
        } else {
            $('#btn_settle_print').attr('disabled', true);
        }
        return (inv_tot - settle_tot).toFixed(2);
    }
    inv.calc_settle_totals = calc_settle_totals;
    
    function save_settle() {
        // This saves the Invoice as is
        coreWebApp.DocSave({
            formName: 'inv/InvEditForm',
            afterPost: '',
            afterUnpost: ''
            });
    }
    inv.save_settle = save_settle;
    
    function post_print() {
        // This will save invoice with settlement information
        // and toggle status to send for approval/auth
        var opts = { 
            formName: 'inv/InvEditForm', 
            afterPost: '', 
            afterUnpost: '',
            afterSave: core_pos.inv.after_settle_print,
            action: 'P', 
            next_stage_id: coreWebApp.ModelBo.docSecurity.next_stage_id(),
            wfOption: { 
                user_id_to: coreWebApp.ModelBo.docSecurity.next_user_id(), 
                doc_sender_comment: 'Posted' 
            }
        };
        debugger;
        coreWebApp.ModelBo.Submit(opts);
        
    }
    inv.post_print = post_print;
    
    function after_settle_print() {
        coreWebApp.ModelBo.docPrint();
    }
    inv.after_settle_print = after_settle_print;
    
    function show_settle_print() {
        return coreWebApp.ModelBo.inv_id() !== "" && coreWebApp.ModelBo.status() >= 1 ? true : false;
    }
    inv.show_settle_print = show_settle_print;
        
    function settle_cash(type) {
        debugger;
        if(type.is_cash() === false && (type.cash_account_id() != -1 || type.cash_amt() != 0)) {
            type.cash_amt(0);
        } else if (type.is_cash()) {
            if(Number.parseFloat($('#settle_total').val().replace(/,/g, "")) > 0) {
                type.cash_amt(Number.parseFloat($('#settle_total').val().replace(/,/g, "")));
            }
        }
    }
    inv.settle_cash = settle_cash;
    
    function settle_cheque(type) {
        if(type.is_cheque() === false) {
            type.cheque_amt(0);
        } else if (type.is_cheque()) {
            if(Number.parseFloat($('#settle_total').val().replace(/,/g, "")) > 0) {
                type.cheque_amt(Number.parseFloat($('#settle_total').val().replace(/,/g, "")));
            }
        }
    }
    inv.settle_cheque = settle_cheque;
    
    function settle_card(type) {
        if (type.is_card() === false && (type.cc_mac_id() != -1 || type.card_amt() != 0)) {
            type.cc_mac_id(-1);
            type.card_amt(0);
            $('#cc_mac_id').trigger('change'); 
        } else if(type.is_card()) {
            if(Number.parseFloat($('#settle_total').val().replace(/,/g, "")) > 0) {
                type.card_amt(Number.parseFloat($('#settle_total').val().replace(/,/g, "")));
            }
        }
    }
    inv.settle_card = settle_card;
    
    function settle_cust(type) {
        if (type.is_customer() === false && (type.customer_id() != -1 || type.customer_amt() != 0)) {
            type.customer_id(-1);
            type.customer_amt(0);
            $('#customer_id').trigger('change');
        } else if(type.is_customer()) {
            if(Number.parseFloat($('#settle_total').val().replace(/,/g, "")) > 0) {
                type.customer_amt(Number.parseFloat($('#settle_total').val().replace(/,/g, "")));
            }
        }
    }
    inv.settle_cust = settle_cust;
    
    function for_settle() {
        return coreWebApp.ModelBo.inv_id() !== "" ? false : true;
    }
    inv.for_settle = for_settle;    
    
    function is_cash(row) {
        return row.is_cash();
    }
    inv.is_cash = is_cash;
    
    function is_cheque(row) {
        return row.is_cheque();
    }
    inv.is_cheque = is_cheque;
    
    function is_card(row) {
        return row.is_card();
    }
    inv.is_card = is_card;
    
    function is_customer(row) {
        return row.is_customer();
    }
    inv.is_customer = is_customer;
    
    function disc_is_value_change(dataItem) {
        if(dataItem.disc_is_value()) {
            dataItem.disc_pcnt(0);
        }
        inv.item_calc(dataItem);
    }
    inv.disc_is_value_change = disc_is_value_change;
    
    function disc_pcnt_enable(dataItem) {
        return !dataItem.disc_is_value();
    }
    inv.disc_pcnt_enable = disc_pcnt_enable;
    
    function disc_amt_enable(dataItem) {
        return dataItem.disc_is_value();
    }
    inv.disc_amt_enable = disc_amt_enable;
    
    function allowSave() {
        return coreWebApp.ModelBo.docSecurity.allowSave() 
                && coreWebApp.ModelBo.inv_amt() == Number.parseFloat($('#settle_total').val().replace(/,/g, "")) 
                && coreWebApp.ModelBo.inv_amt() > 0 ? true : false;
    }
    inv.allowSave = allowSave;
    
    function allowPost() {
        return coreWebApp.ModelBo.docSecurity.allowPost(); 
               // && coreWebApp.ModelBo.inv_amt() == Number.parseFloat($('#settle_total').val().replace(/,/g, "")) 
               // && coreWebApp.ModelBo.inv_amt() > 0 ? true : false;
    }
    inv.allowPost = allowPost;
    
    function material_filter(fltr, dataItem) {
        if(parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    inv.material_filter = material_filter;
    
    function inv_tran_add(row) {
        row.bal_qty("");
        row.sl_no(inv.sl_no++);
        set_default_sl(row);
    }
    inv.inv_tran_add = inv_tran_add;
    
    function inv_tran_delete() {
        total_calc();
    }
    inv.inv_tran_delete = inv_tran_delete;
    
    function set_default_sl(row) {
        if(typeof coreWebApp.ModelBo.default_sl === 'undefined') return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }
    
    function tax_schedule_change(dataItem) {
        if(skip_ts_fetch) return;
        
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
                    item_calc(dataItem);
                }
            },
            error: function () {
                coreWebApp.toastmsg('warning', 'Failed to fetch selected tax information');
            }
        });
    }
    inv.tax_schedule_change = tax_schedule_change;
    
    function tax_pcnt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false; 
    }
    inv.tax_pcnt_enable = tax_pcnt_enable;
    
    function tax_amt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false; 
    }
    inv.tax_amt_enable = tax_amt_enable;
    
    function view_war_info(row) {
        core_stockinvoice.view_war_info(row);
    }
    inv.view_war_info = view_war_info;
    
    function stock_tran_war_hide() {
        return false;
    }
    inv.stock_tran_war_hide = stock_tran_war_hide;
    
    function fetch_bb_mat_info(row, el) {
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
                    item_bb_calc(row);
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function(data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    inv.fetch_bb_mat_info = fetch_bb_mat_info;
    
    stop_bb_calc = false;
    function item_bb_calc(row) {
        if(stop_bb_calc) {
            return;
        }
        stop_bb_calc = true;
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
            tax_amt = parseFloat(row.tax_amt());
        }
        row.tax_amt(tax_amt.toFixed(2));
        row.item_amt(Number.parseFloat((received_qty * sale_rate) + tax_amt).toFixed(2));
        inv.total_calc();
        stop_bb_calc = false;
    }
    core_pos.inv.item_bb_calc = item_bb_calc;
    
    function inv_bb_add(row) {
        row.sl_no(inv.bb_sl_no++);
        set_default_sl(row);
    }
    inv.inv_bb_add = inv_bb_add;
    
}(window.core_pos.inv));

