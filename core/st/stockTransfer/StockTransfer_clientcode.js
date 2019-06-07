/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_stocktransfer = {};
(function (core_stocktransfer) {
    stop_calc = false;
    core_stocktransfer.sl_no = 0;

    function after_load() {
        core_stocktransfer.sl_no = coreWebApp.ModelBo.stock_tran().length;
        if (coreWebApp.ModelBo.for_receipt()) {
            $('#btn_receipt').css('background-color', 'green');
            $('#btn_receipt').css('color', 'white');
            $('#btn_receipt').css('font-size', 'medium');
            $('#btn-action').hide();
            if (coreWebApp.ModelBo.receipt_posted()) {
                $('#btn_receipt').hide();
                $('#btn_req_qc').hide();
                $('#btn_apply_tsl').hide();
            }
            coreWebApp.toggleEdit();
            $('#cmdsave').hide();
        }
    }
    core_stocktransfer.after_load = after_load;

    function fetch_trg_branch_info() {
        opts = {
            branch_id: coreWebApp.ModelBo.target_branch_id(),
            after_update: fetch_trg_branch_info_after_update
        };
        core_sys.get_branch_address(opts);
    }
    core_stocktransfer.fetch_trg_branch_info = fetch_trg_branch_info;

    function fetch_trg_branch_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.target_branch_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_addr(opts.result.addr);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_state_id()
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
    core_stocktransfer.fetch_trg_branch_info_after_update = fetch_trg_branch_info_after_update;

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
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        tax_amt = parseFloat(row.tax_amt()); // always pickup tax_amt to avoid float errors
        row.item_amt((bt_amt + tax_amt).toFixed(2));
        core_stocktransfer.total_calc();
        stop_calc = false;
    }
    core_stocktransfer.item_calc = item_calc;

    function total_calc() {
        var bt_amt_tot = 0.00;
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var bb_amt_tot = new Number(0.00);
        var unit_cnt = new Number(0.00);
        // Total each stock item
        core_stocktransfer.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_stocktransfer.sl_no += 1;
            row.sl_no(core_stocktransfer.sl_no);
            bt_amt_tot += parseFloat(row.gtt_bt_amt());
            item_amt_tot += Number.parseFloat(row.item_amt());
            tax_amt_tot += Number.parseFloat(row.tax_amt());
            unit_cnt += Number.parseFloat(row.issued_qty());
        });
        coreWebApp.ModelBo.annex_info.item_cnt(core_stocktransfer.sl_no);
        coreWebApp.ModelBo.annex_info.unit_cnt(unit_cnt.toFixed(0));
        coreWebApp.ModelBo.vbt_amt_tot(bt_amt_tot.toFixed(2));
        // set fetch tax_amt to avoid float error
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        tax_amt_tot = parseFloat(coreWebApp.ModelBo.tax_amt());
        // set fetch gross_amt to avoid float error
        coreWebApp.ModelBo.gross_amt(item_amt_tot.toFixed(2));
        item_amt_tot = parseFloat(coreWebApp.ModelBo.gross_amt());
        // set fetch round off to avoid float error
        var rof_amt = Number.parseFloat((item_amt_tot).toFixed(0)) - (item_amt_tot);
        coreWebApp.ModelBo.round_off_amt(rof_amt.toFixed(2));
        rof_amt = parseFloat(coreWebApp.ModelBo.round_off_amt());
        // set total_amt
        coreWebApp.ModelBo.total_amt((item_amt_tot + rof_amt).toFixed(2));
    }
    core_stocktransfer.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.stock_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    core_stocktransfer.redo_item_calc = redo_item_calc;

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_stocktransfer.material_filter = material_filter;

    function fetch_mat_info(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-cc',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code, mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                var gst_hsn_info = $.parseJSON(result.gst_hsn_info);
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
                    if (row.issued_qty() == 0) {
                        row.issued_qty(1);
                    }
                    row.rate(result.wac_rate);
                    row.disc_percent(0.00);
                    row.has_ts(result.has_ts);
                    // Get Gst info
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.target_branch_state_id(),
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
    core_stocktransfer.fetch_mat_info = fetch_mat_info;

    function target_branch_filter(fltr) {
        fltr = ' branch_id != ' + coreWebApp.ModelBo.branch_id();
        return fltr;
    }
    core_stocktransfer.target_branch_filter = target_branch_filter;

    function target_sl_filter(fltr) {
        fltr = ' branch_id= ' + coreWebApp.ModelBo.target_branch_id();
        return fltr;
    }
    core_stocktransfer.target_sl_filter = target_sl_filter;

    function sl_filter(fltr) {
//        fltr = ' sl_type_id = 1';
        return fltr;
    }
    core_stocktransfer.sl_filter = sl_filter;

    function st_tran_add(row) {
        core_stocktransfer.sl_no += 1;
        row.sl_no(core_stocktransfer.sl_no);
        set_default_sl(row);
        if (coreWebApp.ModelBo.stock_tran().length > 1) {
            var pr = coreWebApp.ModelBo.stock_tran()[coreWebApp.ModelBo.stock_tran().length - 2];
            row.material_type_id(pr.material_type_id());
            var el = coreWebApp.latestElement;
            $(el[0]).find('[type=SmartCombo]').each(function () {
                $(this).trigger("change");
            });
        }
    }
    core_stocktransfer.st_tran_add = st_tran_add;

    function st_tran_delete() {
        total_calc();
    }
    core_stocktransfer.st_tran_delete = st_tran_delete;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }

    function disc_is_value_change(dataItem) {
        if (dataItem.disc_is_value()) {
            dataItem.disc_percent(0);
        }
        core_stocktransfer.item_calc(dataItem);
    }
    core_stocktransfer.disc_is_value_change = disc_is_value_change;

    function disc_pcnt_enable(dataItem) {
        return !dataItem.disc_is_value();
    }
    core_stocktransfer.disc_pcnt_enable = disc_pcnt_enable;

    function disc_amt_enable(dataItem) {
        return dataItem.disc_is_value();
    }
    core_stocktransfer.disc_amt_enable = disc_amt_enable;

    function add_mat() {
        var opts = {
            stock_tran: coreWebApp.ModelBo.stock_tran,
            tran_add_callback: st_tran_add
        };
        opts.module = 'core/st';
        opts.alloc_view = 'stockTransfer/AddStockItem';
        opts.call_init = add_mat_init;
        opts.call_update = add_mat_update;
        coreWebApp.showAllocV2(opts);
    }
    core_stocktransfer.add_mat = add_mat;

    function add_mat_init(opts, after_init) {
        var sel_stock = new function () {
            self = this;
        };
        sel_stock.mat_temp = {};
        sel_stock.material_type_id = ko.observable(-1);
        sel_stock.updated = ko.observable(false);
        sel_stock.item_cnt = ko.observable(0);
        sel_stock.unit_cnt = ko.observable(0);
        opts.model = sel_stock;
        $('#mat-loading').hide();
    }

    function get_detail() {

        $('#mat-loading').show();
        $.ajax({
            url: '?r=core/st/utils/stock-item-for-st',
            method: 'GET',
            dataType: 'json',
            data: {
                material_type_id: self.material_type_id()
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {
                    self.mat_temp = jsonResult.mat_dt;

                    self.mat_temp.forEach(itm => {
                        itm.issued_qty = ko.observable(0);
                        itm.issued_qty.subscribe(function () {
                            var cnt = 0;
                            var units = 0;
                            self.mat_temp.forEach(itm => {
                                if (parseFloat(itm.issued_qty()) > 0) {
                                    cnt += 1;
                                    units += parseFloat(itm.issued_qty());
                                }
                            });
                            self.item_cnt(cnt);
                            self.unit_cnt(units);
                        });
                        itm.updated = ko.observable(false);
                    });
//                    var stran = find_mat_ref(coreWebApp.ModelBo.stock_tran, self.customer_id());
//                    if (stran) {
                    self.mat_temp.forEach(itm => {
                        coreWebApp.ModelBo.stock_tran().forEach(st_tran => {
                            if (st_tran.material_id() == itm.material_id) {
                                itm.issued_qty(st_tran.issued_qty());
                            }
                        });
                    });
//                    }

                    $('#mat-loading').hide();
                    $('#mat_temp').DataTable({
                        data: self.mat_temp,
                        order: [],
                        columns: [
                            {data: "material_name", title: "Stock item", width: "40%"},
                            {data: "issued_qty", title: "Order Qty", width: "15%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="textbox" data-bind="numericValue: issued_qty" class="textbox form-control">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }},
                            {data: "uom_desc", title: "UoM", width: "10%"}
                        ],
                        deferRender: true,
                        scrollY: $('#content-root').height() * .75,
                        scrollCollapse: true,
                        scroller: true
                    });
                }
            }
        });
    }
    core_stocktransfer.get_detail = get_detail;

    function add_mat_update(opts) {

        // Validate line items for excess allocation
        var is_valid = true;
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }

        // Update the tran
        // Step 1: For batch line items previously selected, and currently also selected update the rows
        // To unselect or remove a item, the user can always use the delete tran available in the document ui.
        opts.model.mat_temp.forEach(poc => {
            if (parseFloat(poc.issued_qty()) > 0) {
                var stran = find_mat_ref(opts.stock_tran, poc.material_id);
                if (stran) {
                    stran.issued_qty(parseFloat(poc.issued_qty()).toFixed(2));
                    poc.updated(true);
                    typeof opts.tran_item_calc_callback != 'undefined' ? opts.tran_item_calc_callback(stran) : '';
                }
            }
        });

        // Step 2: Insert newly added customers
        // Step 2: Insert newly added items
        opts.model.mat_temp.forEach(poc => {
            if (!poc.updated() && parseFloat(poc.issued_qty()) > 0) {
                var newStran = coreWebApp.ModelBo.addNewRow('stock_tran', coreWebApp.ModelBo, true, false);
                newStran.material_id(poc.material_id);
                coreWebApp.trigger_change('material_id', poc.material_id, poc.material_name);
                newStran.issued_qty(poc.issued_qty());
                typeof opts.tran_add_callback != 'undefined' ? opts.tran_add_callback(newStran) : '';
                coreWebApp.afterNewRowAdded(newStran);
                fetch_mat_info(newStran);
            }
        });
        typeof opts.tran_after_add_callback != 'undefined' ? opts.tran_after_add_callback() : '';

        opts.stock_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
//        
    }

    function find_mat_ref(stock_tran, material_id) {
        for (var p = 0; p < stock_tran().length; ++p) {
            if (stock_tran()[p].material_id() == material_id) {
                return stock_tran()[p];
            }
        }
        return false;
    }

    function lot_alloc_short(row) {
        if (row.has_qc()) {
            var alloc_sum = 0.00;
            $.each(row.sl_lot_alloc(), function (idx, idt) {
                alloc_sum += parseFloat(idt.lot_issue_qty());
            });
            if (parseFloat(row.issued_qty()) != alloc_sum) {
                return 'lightcoral';
            } else {
                return 'lightseagreen';
            }
        }
        return 'default';
    }
    core_stocktransfer.lot_alloc_short = lot_alloc_short;

    function fetch_avl_qty(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-cc',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code, mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                if (typeof result.mat_id !== 'undefined') {                 
                    row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                    if (parseFloat(result.bal_qty) > 0) {
                        row.has_bal(true);
                    } else {
                        row.has_bal(false);
                    }
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_stocktransfer.fetch_avl_qty = fetch_avl_qty;
}(window.core_stocktransfer));