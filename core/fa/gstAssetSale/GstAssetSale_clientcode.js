// Declare core_ap Namespace
typeof window.core_fa == 'undefined' ? window.core_fa = {} : '';
window.core_fa.as = {};


(function (as) {
    as.sl_no = 0;

    function afterload() {
        as.sl_no = coreWebApp.ModelBo.as_tran().length;
        $('#cmd_addnew_as_tran').hide();
    }
    as.afterload = afterload;

    function enable_sale_amt(dataItem) {
        if (dataItem.selected() == true) {
            return true;
        } else {
            return false;
        }
    }
    as.enable_sale_amt = enable_sale_amt


    function cheque_info_visible(dataItem) {
        if (coreWebApp.ModelBo.en_sales_type() == 1) {
            return true;
        }
        return false;
    }
    as.cheque_info_visible = cheque_info_visible;


    function enable_gst(dataItem) {
        if (dataItem.en_sales_type() == 2) {
            return false;
        } else {
            return true;
        }
    }
    as.enable_gst = enable_gst;
    
    function disable_gst(dataItem) {
        if (dataItem.en_sales_type() == 2) {
            return true;
        } else {
            return false;
        }
    }
    as.disable_gst = disable_gst;
    
    function account_combo_filter(fltr){
        if(coreWebApp.ModelBo.en_sales_type()==0){
            fltr=' account_type_id = 2 ';
        }
        if(coreWebApp.ModelBo.en_sales_type()==1){
            fltr=' account_type_id = 1 ';
        }  
        if(coreWebApp.ModelBo.en_sales_type()==2){
            fltr=' account_type_id = 7';
        }
        if(coreWebApp.ModelBo.en_sales_type()==3){
            fltr=' account_type_id not in (0, 1, 2, 7, 12, 23, 24, 21, 22, 18, 38)';
        }                      
        return fltr;
    }    
    as.account_combo_filter=account_combo_filter;

    function fetch_cust_info(dataItem) {
        // Fetch GST related information only if purchase type id Credit
        if (coreWebApp.ModelBo.en_sales_type() == 2) {
            opts = {
                cust_id: coreWebApp.ModelBo.customer_id(),
                after_update: fetch_cust_info_after_update
            };
            core_ar.get_address(opts);
        }
    }
    as.fetch_cust_info = fetch_cust_info;

    function fetch_cust_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_address(opts.result.addr);
        } else {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(-1);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin("");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_address("");
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id(gstOpts.vat_type_id);
            $('[id="annex_info.gst_output_info.vat_type_id"]').trigger('change');
        }
        if (coreWebApp.ModelBo.as_tran().length > 0) {
            gstOpts.tran = coreWebApp.ModelBo.as_tran;
            gstOpts.call_back = total_calc;
            core_tx.gst.reapply_gtt(gstOpts);
        }
    }
    as.fetch_cust_info_after_update = fetch_cust_info_after_update;


    function customer_state_update() {
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id(gstOpts.vat_type_id);
            $('[id="annex_info.gst_output_info.vat_type_id"]').trigger('change');
            if (coreWebApp.ModelBo.as_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.as_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    as.customer_state_update = customer_state_update;


    function select_cust_addr(opts) {
        var opts = {
            cust_id: coreWebApp.ModelBo.customer_id(),
            after_update: select_cust_addr_after_update
        };
        core_ar.select_address(opts);
    }
    as.select_cust_addr = select_cust_addr;

    function select_cust_addr_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_address(opts.result.addr);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.as_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.as_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
            $('[id="annex_info.gst_output_info.vat_type_id"]').trigger('change');
        }
    }
    as.select_cust_addr_after_update = select_cust_addr_after_update;
    
    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);

        as.sl_no = 0;
        // Total each bill item
        ko.utils.arrayForEach(coreWebApp.ModelBo.as_tran(), function (row) {
            as.sl_no += 1;
            row.sl_no(as.sl_no);
            tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
            item_amt_tot += Number.parseFloat(row.gtt_bt_amt());
            row.credit_amt(parseFloat(row.gtt_bt_amt()));
        });
        coreWebApp.ModelBo.gross_debit_amt(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));

        var sale_amt_tot = Number.parseFloat(coreWebApp.ModelBo.gross_debit_amt()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.tax_amt()) + Number.parseFloat(coreWebApp.ModelBo.annex_info.round_off_amt());

        coreWebApp.ModelBo.debit_amt(sale_amt_tot.toFixed(2));
        coreWebApp.ModelBo.net_debit_amt(sale_amt_tot.toFixed(2));
    }
    as.total_calc = total_calc;

    function fetch_hsn_info(row) {
        var hsn_sc_id = row.hsn_sc_id();
        $.ajax({
            url: '?r=core/tx/form/get-hsn-gst-info',
            type: 'GET',
            dataType: 'json',
            data: {hsn_sc_id: hsn_sc_id},
            success: function (gst_hsn_info) {
                if (typeof gst_hsn_info.hsn_sc_code !== 'undefined') {
                    stop_calc = true;
                    // This is GST
                    core_tx.gst.item_gtt_reset({
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
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
    as.fetch_hsn_info = fetch_hsn_info;

    function item_calc(row) {
        var bt_amt = parseFloat(row.gtt_bt_amt());
        // This is GST
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        as.total_calc();
    }
    as.item_calc = item_calc;

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
    as.select_hsn = select_hsn;

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
    as.gst_rate_select = gst_rate_select;
    
    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.as_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    as.redo_item_calc = redo_item_calc;
    

    function after_delete_method(pr, prop, rw) {
        total_calc();
    }
    as.after_delete_method = after_delete_method;
    
    function asset_item_sel() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.as_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            asset_class_id: coreWebApp.ModelBo.asset_class_id(),
            as_tran: coreWebApp.ModelBo.as_tran,
            tran_add_callback: as_tran_add
        };
        core_fa.ai_for_as.ai_sel_ui(opts);
    }
    as.asset_item_sel = asset_item_sel;

    function as_tran_add(row) {
        as.sl_no += 1;
        row.sl_no(as.sl_no);
    }
    as.as_tran_add = as_tran_add;
    

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.as_id() != '' && coreWebApp.ModelBo.as_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    as.visible_gl_distribution = visible_gl_distribution

    function view_gl_init() {
        core_ac.gl_distribution('fa.as_control', coreWebApp.ModelBo.as_id());
    }
    as.view_gl_init = view_gl_init;


    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_fa.as.view_gl_init');
    }

    as.view_gl = view_gl;
    
}(window.core_fa.as));

// Select Vch wizard method to render datatable
window.core_fa.ai_for_as = {};
(function (ai_for_as) {

    function sale_amt_enable(row) {
        return row.ai_sel();
    }
    ai_for_as.sale_amt_enable = sale_amt_enable;

    function ai_sel_ui(opts) {
        opts.module = 'core/fa';
        opts.alloc_view = 'gstAssetSale/SelectAssetItem';
        opts.call_init = ai_sel_init;
        opts.call_update = ai_sel_update;
        coreWebApp.showAllocV2(opts);
    }
    ai_for_as.ai_sel_ui = ai_sel_ui;

    function ai_sel_init(opts, after_init) {
        var sel_ai_alloc = new function () {
            self = this;
        };
        sel_ai_alloc.ai_temp = {};
        sel_ai_alloc.voucher_id = opts.voucher_id;
        sel_ai_alloc.doc_date = opts.doc_date;
        sel_ai_alloc.as_tran = opts.as_tran;
        sel_ai_alloc.asset_class_id = opts.asset_class_id;
        opts.model = sel_ai_alloc;
        ai_for_as.get_detail();
    }
    ai_for_as.batch_sel_init = ai_for_as;

    function get_detail() {
        $.ajax({
            url: '?r=core/fa/form/asset-item-for-as',
            type: 'GET',
            dataType: 'json',
            data: {
                voucher_id: self.voucher_id,
                doc_date: self.doc_date,
                asset_class_id: self.asset_class_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    // Using a datatable to render data
                    if ($.fn.dataTable.isDataTable('#ai_temp')) {
                        var t = $('#ai_temp').DataTable();
                        t.destroy(true);
                        var p = $('#ai_temp-cont');
                        p.append('<table id="ai_temp" class="table table-hover table-condensed dataTable no-footer"></table>');
                    }

                    self.ai_temp = jsonResult.ai_bal;

                    self.ai_temp.forEach(br => {
                        br.ai_sel = ko.observable(false);
                        br.updated = ko.observable(false);
                        br.ai_sel.subscribe(ai_sel_click, br);
                        br.sale_amt = ko.observable(0);
                        for (var a = 0; a < self.as_tran().length; ++a) {
                            var poc = self.as_tran()[a];
                            if (poc.asset_item_id() == br.asset_item_id) {
                                br.ai_sel(true);
                                br.sale_amt(poc.credit_amt());
                            }
                        }
                    });
                    $('#ai-loading').hide();
                    if ($.fn.dataTable.isDataTable('#ai_temp')) {
                        var t = $('#ai_temp').DataTable();
                        t.destroy();
                    }
                    
                    var tbl = $('#ai_temp').DataTable({
                        data: self.ai_temp,
                        order: [],
                        columns: [
                            {data: "purchase_date", title: "Purchase Dt.",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "voucher_tran_id", title: "Purchase #", width: "20%"},
                            {data: "asset_name", title: "Asset Name", width: "12%"},
                            {data: "purchase_amt", title: "Purchase Amt", className: "dt-right", width: "10%",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "dep_amt", title: "Dep Amt", className: "dt-right", width: "10%",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "asset_qty", title: "Asset Qty", className: "dt-right", width: "10%",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 3);
                                }
                            },
                            {data: "ai_sel", title: "...", width: "5%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: ai_sel">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
                            {data: "sale_amt", title: "Sale Amt", width: "15%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="textbox" data-bind="numericValue: sale_amt, enable: ai_sel" class="textbox form-control">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }
                            }
                        ],
                        deferRender: true,
                        scrollY: '200px',
                        scrollCollapse: true,
                        scroller: true,
                    });
                    var l = $('#ai_temp_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    ai_for_as.get_detail = get_detail;

    function ai_sel_update(opts) {
        // Validate line items for excess allocation
        var is_valid = true;

        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }
        // Update the tran
        // Step 1: For MRQ line items previously selected, and currently also selected update the rows
        // To unselect or remove a item, the user can always use the delete tran available in the document ui.
        opts.model.ai_temp.forEach(poc => {
            if (poc.ai_sel()) {
                if (parseFloat(poc.sale_amt()) > 0) {
                    var stran = find_ai_ref(opts.as_tran, poc.asset_item_id);
                    if (stran) {
                        stran.credit_amt(parseFloat(poc.sale_amt()).toFixed(3));
                        stran.gtt_bt_amt(parseFloat(poc.sale_amt()).toFixed(3));
                        poc.updated(true);
                    }
                }
            }
        });

        // Step 2: Insert newly added items
        opts.model.ai_temp.forEach(poc => {
            if (!poc.updated() && poc.ai_sel() && parseFloat(poc.sale_amt()) > 0) {
                var newStran = coreWebApp.ModelBo.addNewRow('as_tran', coreWebApp.ModelBo, true, false);
                newStran.asset_item_id(poc.asset_item_id);
                newStran.asset_code(poc.asset_code);
                newStran.asset_name(poc.asset_name);
                newStran.purchase_amt(poc.purchase_amt);
                newStran.dep_amt(poc.dep_amt);
                newStran.gtt_bt_amt(poc.sale_amt());
                newStran.credit_amt(poc.sale_amt());
                newStran.purchase_date(poc.purchase_date);
                typeof opts.tran_add_callback != 'undefined' ? opts.tran_add_callback(newStran) : '';

                coreWebApp.afterNewRowAdded(newStran);
            }
        });
        opts.as_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    ai_for_as.ai_sel_update = ai_sel_update;

    function find_ai_ref(as_tran, asset_item_id) {
        for (var p = 0; p < as_tran().length; ++p) {
            if (as_tran()[p].asset_item_id() == asset_item_id) {
                return as_tran()[p];
            }
        }
        return false;
    }

    function ai_sel_click() {
        if (!this.ai_sel()) {
            this.sale_amt(0.00);
        }
    }
    ai_for_as.ai_sel_click = ai_sel_click;
    
}(window.core_fa.ai_for_as));

// Select Vch wizard method to render datatable
window.core_fa.as_wiz = {};
(function (as_wiz) {

    function sel_asset_item_init(args) {
        $('#tbl-SelectAssetItem').DataTable({
            data: args.model.SelectAssetItem(),
            order: [],
            columns: [
                {data: "selected", title: "...",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: selected">');
                        ko.applyBindings(rowData, $(td)[0]);
                        $(td).css('text-align', 'center');
                    }
                },
                {data: "purchase_date", title: "Purchase Date",
                    render: function (cellData) {
                        return coreWebApp.formatDate(cellData());
                    }
                },
                {data: "voucher_tran_id", title: "Purchase #"},
                {data: "asset_name", title: "Asset Name"},
                {data: "purchase_amt", title: "Purchase Amt", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "dep_amt", title: "Dep Amt", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "sale_amt", title: "Sale Amt",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="textbox" data-bind="numericValue: sale_amt, enable: selected" class="textbox form-control">');
                        ko.applyBindings(rowData, $(td)[0]);
                    }
                },
                {data: "asset_qty", title: "Asset Qty", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 3);
                    }
                }
            ],
            deferRender: true,
            scrollY: '400px',
            scrollCollapse: true,
            scroller: true,
        });
    }
    as_wiz.sel_asset_item_init = sel_asset_item_init;
}(window.core_fa.as_wiz));