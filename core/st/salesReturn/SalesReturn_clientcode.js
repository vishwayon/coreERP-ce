/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_salesreturn = {};
(function (core_salesreturn) {

    stop_calc = false;
    skip_ts_fetch = false;
    
    function return_qty_enable(dataItem) {
        if (dataItem.selected()) {
            return true;
        } else {
            return false;
        }
    }
    core_salesreturn.return_qty_enable = return_qty_enable;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_salesreturn.enable_visible_fc = enable_visible_fc


    function fc_changed(dataItem) {
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
//        //dataItem.invoice_amt(parseFloat(dataItem.invoice_amt_fc())*exch_rate);
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (a) {
            if (fc_type_id == 0) {
                a.rate_fc(0);
                a.item_amt_fc(0);
            } else {
                a.rate((parseFloat(a.rate_fc()) * exch_rate).toFixed(3));
                a.item_amt((parseFloat(a.item_amt_fc()) * exch_rate).toFixed(3));
            }
        });
    }
    core_salesreturn.fc_changed = fc_changed;

    function tax_amt_enable(dataItem) {
        console.log('tax_amt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 2 ? true : false;
    }
    core_salesreturn.tax_amt_enable = tax_amt_enable;

    function tax_pcnt_enable(dataItem) {
        console.log('tax_pcnt_enable');
        // This is based on en_tax_type:Calculation_type as defined in core/tx/taxSchedule/TaxScheduleNew
        // 0 -> Percent Of Amount; 1 -> Custom Percent Of Amount; 2 -> Custom Absolute Amount
        return parseInt(dataItem.en_tax_type()) === 1 ? true : false;
    }
    core_salesreturn.tax_pcnt_enable = tax_pcnt_enable;

    function tax_schedule_change(dataItem) {
        if (skip_ts_fetch)
            return;
        console.log('tax_schedule_change');

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
                    // decide which tax item is changing
                    if (typeof dataItem.material_id !== 'undefined') {
                        item_calc(dataItem);
                    }
                }
            },
            error: function () {
                coreWebApp.toastmsg('warning', 'Failed to fetch selected tax information');
            }
        });
    }
    core_salesreturn.tax_schedule_change = tax_schedule_change;
    
    function sr_afterload(row) {
        $('#cmd_addnew_stock_tran').hide();
        total_calc();
    }
    core_salesreturn.sr_afterload = sr_afterload;

    function item_calc(row) {
        console.log('item_calc');
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var received_qty = Number.parseFloat(row.received_qty());
        var pur_rate = Number.parseFloat(row.rate());
        var tax_pcnt = Number.parseFloat(row.tax_pcnt());
        var en_tax_type = parseInt(row.en_tax_type());
        var tax_amt = new Number(0.00);
        var bt_amt = (received_qty * pur_rate);
        row.bt_amt(bt_amt.toFixed(2));
        if (en_tax_type === 0 || en_tax_type === 1) {
            tax_amt = bt_amt * tax_pcnt / 100;
            row.tax_amt(tax_amt.toFixed(2));
        } else {
            row.tax_pcnt(0.00);
        }
        // pick up the tax amt to ensure proper roundoff
        tax_amt = parseFloat(row.tax_amt());
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        total_calc();
        stop_calc = false;
    }
    core_salesreturn.item_calc = item_calc;

    function st_tran_delete() {
        total_calc();
    }
    core_salesreturn.st_tran_delete = st_tran_delete;

    function total_calc() {
        console.log('total_calc');
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        // Total each stock item
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            bt_amt_tot += Number.parseFloat(row.bt_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
        });
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
        var rof_amt = Number.parseFloat((bt_amt_tot + tax_amt_tot).toFixed(0)) - (bt_amt_tot + tax_amt_tot);
        coreWebApp.ModelBo.round_off_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.total_amt((bt_amt_tot + tax_amt_tot + rof_amt).toFixed(2));

        coreWebApp.ModelBo.net_amt(parseFloat(coreWebApp.ModelBo.total_amt()).toFixed(2));
    }
    core_salesreturn.total_calc = total_calc;

}(window.core_salesreturn));
