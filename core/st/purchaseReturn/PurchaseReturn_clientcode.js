/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_purchasereturn = {};
(function (core_purchasereturn) {

    stop_calc = false;
    skip_ts_fetch = false;
    function pr_afterload(row) {
        total_calc();
    }
    core_purchasereturn.pr_afterload = pr_afterload;    
    
    function item_calc(row) {
        console.log('item_calc');
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var pur_rate = Number.parseFloat(row.rate());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
        var bt_amt = (issued_qty * pur_rate);
        row.bt_amt(bt_amt.toFixed(2));
        if (en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = bt_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
        }
        // Always pickup the tax amt to avoid rounding off diff
        tax_amt = parseFloat(row.tax_amt());
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        core_purchasereturn.total_calc();
        stop_calc = false;
    }
    core_purchasereturn.item_calc = item_calc;
    
    function st_tran_delete() {
        total_calc();
    }
    core_purchasereturn.st_tran_delete = st_tran_delete;
    
    function total_calc() {
        console.log('total_calc');
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var supp_lc_amt_tot = new Number(0.00); 
        // Total each stock item
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function(row) {
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            if(row.apply_itc()) {
                // Tax Credit claimed
                tax_amt_tot += Number.parseFloat(row.tax_amt());
            } else {
                // Tax Credit not claimed, hence added to misc.
                supp_lc_amt_tot += Number.parseFloat(row.tax_amt());
            }
        });
        //Total Landed Costs
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_lc_tran(), function(row) {
            if(row.supplier_paid()) {
                if(row.apply_itc()) {
                    supp_lc_amt_tot += parseFloat(row.debit_amt());
                    tax_amt_tot += parseFloat(row.tax_amt());
                } else {
                    supp_lc_amt_tot += parseFloat(row.debit_amt()) + parseFloat(row.tax_amt());
                }
            }
        });
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
        coreWebApp.ModelBo.misc_taxable_amt(supp_lc_amt_tot.toFixed(2));
        var rof_amt = parseFloat(coreWebApp.ModelBo.round_off_amt());
        coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot  + supp_lc_amt_tot + rof_amt).toFixed(2));
        
        coreWebApp.ModelBo.net_amt(parseFloat(coreWebApp.ModelBo.total_amt()).toFixed(2));
    }
    core_purchasereturn.total_calc = total_calc;


    function tax_amt_enable(dataItem) {
        console.log('tax_amt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false;
    }
    core_purchasereturn.tax_amt_enable = tax_amt_enable;

    function tax_pcnt_enable(dataItem) {
        console.log('tax_pcnt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false;
    }
    core_purchasereturn.tax_pcnt_enable = tax_pcnt_enable;

    function return_qty_enable(dataItem) {
        if (dataItem.selected()) {
            return true;
        } else {
            return false;
        }
    }
    core_purchasereturn.return_qty_enable = return_qty_enable;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_purchasereturn.enable_visible_fc = enable_visible_fc

    function fc_changed(dataItem) {
        console.log('fc_changed');
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (a) {
            if (fc_type_id == 0) {
                a.rate_fc(0);
                a.item_amt_fc(0);
            } else {
                a.rate((parseFloat(a.rate_fc()) * exch_rate).toFixed(3));
                a.item_amt((parseFloat(a.item_amt_fc()) * exch_rate).toFixed(2));
            }
        });
    }
    core_purchasereturn.fc_changed = fc_changed;
    
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
    core_purchasereturn.tax_schedule_change = tax_schedule_change;
    
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
        core_purchasereturn.total_calc();
        stop_calc = false;
    }
    core_purchasereturn.lc_item_calc = lc_item_calc;

    
    function lc_tran_delete() {
        total_calc();
    }
    core_purchasereturn.lc_tran_delete = lc_tran_delete;
    
    
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
    core_purchasereturn.liability_acc_enable=liability_acc_enable;
    
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
    core_purchasereturn.stock_lc_tax_enable = stock_lc_tax_enable;
    
    function apply_itc(data) {
        total_calc();
    }
    core_purchasereturn.apply_itc = apply_itc;
}(window.core_purchasereturn));
