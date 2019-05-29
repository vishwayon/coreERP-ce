typeof window.core_pos === 'undefined' ? window.core_pos={} : '';
window.core_pos.gir = {};

(function (gir) {
    var stop_calc = false;
    gir.sl_no = 1;
    
    function after_load() {
        gir.sl_no = coreWebApp.ModelBo.inv_tran().length + 1;
        if(coreWebApp.ModelBo.inv_id() == '' || coreWebApp.ModelBo.inv_id() == -1) {
            gir.total_calc();
        }
    }
    gir.after_load = after_load;
    
    function item_calc(row) {
        if(stop_calc) {
            return;
        }
        stop_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var sale_rate = Number.parseFloat(row.rate());
        var bt_amt = (received_qty * sale_rate);
        row.bt_amt(bt_amt.toFixed(2));
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt()) 
                        + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));       
        tax_amt = parseFloat(row.tax_amt()); // Tax is rounded off for preceision
        row.item_amt(Number.parseFloat((received_qty * sale_rate) + tax_amt).toFixed(2));
        gir.total_calc();
        stop_calc = false;
    }
    gir.item_calc = item_calc;
    
    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        gir.sl_no = 1;
        ko.utils.arrayForEach(coreWebApp.ModelBo.inv_tran(), function(row) {
            row.sl_no(gir.sl_no++);
            item_amt_tot += Number.parseFloat(row.item_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        coreWebApp.ModelBo.item_amt_tot(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt_tot(tax_amt_tot.toFixed(2));
        var nt_amt = 0.00;
        var rof_amt = Number.parseFloat((item_amt_tot + nt_amt).toFixed(0)) - (item_amt_tot + nt_amt);
        coreWebApp.ModelBo.nt_amt(nt_amt.toFixed(2));
        coreWebApp.ModelBo.rof_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.inv_amt((item_amt_tot + nt_amt + rof_amt).toFixed(2));
    }
    gir.total_calc = total_calc;
    
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
    gir.calc_settle_totals = calc_settle_totals;
    
    function save_settle() {
        // This saves the Invoice as is
        coreWebApp.DocSave({
            formName: 'gir/GstInvRetEditForm',
            afterPost: '',
            afterUnpost: ''
            });
    }
    gir.save_settle = save_settle;
    
    function post_print() {
        // This will save invoice with settlement information
        // and toggle status to send for approval/auth
        var opts = { 
            formName: 'gir/GstInvRetEditForm', 
            afterPost: '', 
            afterUnpost: '',
            afterSave: core_pos.gir.after_settle_print,
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
    gir.post_print = post_print;
    
    function after_settle_print() {
        coreWebApp.ModelBo.docPrint();
    }
    gir.after_settle_print = after_settle_print;
    
    function show_settle_print() {
        return coreWebApp.ModelBo.inv_id() !== "" && coreWebApp.ModelBo.status() >= 1 ? true : false;
    }
    gir.show_settle_print = show_settle_print;
        
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
    gir.settle_cash = settle_cash;
    
    function settle_cheque(type) {
        if(type.is_cheque() === false) {
            type.cheque_amt(0);
        } else if (type.is_cheque()) {
            if(Number.parseFloat($('#settle_total').val().replace(/,/g, "")) > 0) {
                type.cheque_amt(Number.parseFloat($('#settle_total').val().replace(/,/g, "")));
            }
        }
    }
    gir.settle_cheque = settle_cheque;
    
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
    gir.settle_card = settle_card;
    
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
    gir.settle_cust = settle_cust;
    
    function for_settle() {
        return coreWebApp.ModelBo.inv_id() !== "" ? false : true;
    }
    gir.for_settle = for_settle;    
    
    function is_cash(row) {
        return row.is_cash();
    }
    gir.is_cash = is_cash;
    
    function is_cheque(row) {
        return row.is_cheque();
    }
    gir.is_cheque = is_cheque;
    
    function is_card(row) {
        return row.is_card();
    }
    gir.is_card = is_card;
    
    function is_customer(row) {
        return row.is_customer();
    }
    gir.is_customer = is_customer;
    
    function allowSave() {
        return coreWebApp.ModelBo.docSecurity.allowSave() 
                && coreWebApp.ModelBo.inv_amt() == Number.parseFloat($('#settle_total').val().replace(/,/g, "")) 
                && coreWebApp.ModelBo.inv_amt() > 0 ? true : false;
    }
    gir.allowSave = allowSave;
    
    function allowPost() {
        return coreWebApp.ModelBo.docSecurity.allowPost(); 
               // && coreWebApp.ModelBo.inv_amt() == Number.parseFloat($('#settle_total').val().replace(/,/g, "")) 
               // && coreWebApp.ModelBo.inv_amt() > 0 ? true : false;
    }
    gir.allowPost = allowPost;
    
    function inv_tran_add(row) {
        row.bal_qty("");
        row.sl_no(gir.sl_no++);
        set_default_sl(row);
    }
    gir.inv_tran_add = inv_tran_add;
    
    function inv_tran_delete() {
        total_calc();
    }
    gir.inv_tran_delete = inv_tran_delete;
    
    function tax_pcnt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false; 
    }
    gir.tax_pcnt_enable = tax_pcnt_enable;
    
    function tax_amt_enable(dataItem) {
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false; 
    }
    gir.tax_amt_enable = tax_amt_enable;
    
}(window.core_pos.gir));

