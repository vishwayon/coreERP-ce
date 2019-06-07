// Declare core_st Namespace
window.core_st == undefined ? window.core_st = {} : '';
window.core_st.san = {};

(function (san) {
    var sl_no = 1;

    function after_load() {
        sl_no = coreWebApp.ModelBo.stock_tran().length + 1;
        if (coreWebApp.ModelBo.status() != 5 && coreWebApp.ModelBo.stock_id() != '') {
            fetch_avl_qty_many();
        }
        // for san, we get issue value after posting. Hence call on load
        total_calc(); 
    }
    san.after_load = after_load;

    function st_tran_add(row) {
        row.sl_no(sl_no++);
        row.ir('I');
        set_default_sl(row);
    }
    san.st_tran_add = st_tran_add;
    
    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }

    function st_tran_delete() {
        sl_no = 1;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            row.sl_no(sl_no++);
        });
    }
    san.st_tran_delete = st_tran_delete;
    
    function issued_qty_enable(row) {
        return row.ir().toUpperCase() == 'I';
    }
    san.issued_qty_enable = issued_qty_enable;
    
    function received_qty_enable(row) {
        return row.ir().toUpperCase() == 'R';
    }
    san.received_qty_enable = received_qty_enable;
    
    function alloc_button_enable(row) {
        return (row.ir().toUpperCase() == 'I' && row.has_qc());
    }
    san.alloc_button_enable = alloc_button_enable;
    
    function ir_update(row) {
        if(row.ir().toUpperCase() == 'R') {
            parseFloat(row.issued_qty()) != 0 ? row.issued_qty(0.00) : '';
        } else {
            parseFloat(row.received_qty()) != 0 ? row.received_qty(0.00) : '';
        }
    }
    san.ir_update = ir_update;

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
                    if (typeof row.has_ts !== 'undefined') {
                        row.has_ts(result.has_ts);
                    }
                    if (typeof row.has_qc !== 'undefined') {
                        row.has_qc(result.has_qc);
                    }
                    stop_calc = false;
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    san.fetch_mat_info = fetch_mat_info;

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    san.material_filter = material_filter;
    
    function item_calc(row) {
        if (row.issued_qty() > 0) {
            row.item_amt(parseFloat(row.issued_qty()) * parseFloat(row.rate()));
        } else {
            row.item_amt(parseFloat(row.received_qty()) * parseFloat(row.rate()));
        }
        total_calc();
    }
    san.item_calc = item_calc;
    
    function total_calc() {
        var total_rcpts = 0.00;
        var total_issues = 0.00;
        $.each(coreWebApp.ModelBo.stock_tran(), function(idx, itm) {
            if(itm.ir().toUpperCase() == 'R') {
                total_rcpts += parseFloat(itm.item_amt());
            } else {
                total_issues += parseFloat(itm.item_amt());
            }
        });
        coreWebApp.ModelBo.total_receipts(total_rcpts.toFixed(2));
        coreWebApp.ModelBo.total_issues(total_issues.toFixed(2));
        coreWebApp.ModelBo.total_amt((total_rcpts - total_issues).toFixed(2));
    }
    san.total_calc = total_calc;
    
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
    san.fetch_avl_qty = fetch_avl_qty
    
    function fetch_avl_qty_many() {
        if (coreWebApp.ModelBo.stock_tran().length != 0) {
            var mat_data = [];
            ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (r) {
                var cobj = new Object();
                cobj.material_id = r.material_id();
                cobj.stock_location_id = r.stock_location_id();
                mat_data.push(cobj);
            });
            $.ajax({
                url: '?r=core/st/form/get-mat-bal-many-sl',
                type: 'POST',
                dataType: 'json',
                data: {
                    mat_data: mat_data,
                    doc_date: coreWebApp.ModelBo.doc_date()
                },
                success: function (result) {
                    if (typeof result.length !== 'undefined') {
                        stop_calc = true;
                        $.each(coreWebApp.ModelBo.stock_tran(), function (idx, row) {
                            $.each(result, function (midx, mdata) {
                                if (row.material_id() == mdata.material_id) {
                                    row.bal_qty("Avl: " + parseFloat(mdata.bal_qty).toFixed());
                                    if (parseFloat(mdata.bal_qty) > 0) {
                                        row.has_bal(true);
                                    } else {
                                        row.has_bal(false);
                                    }
                                }
                            });
                        });
                        stop_calc = false;
                    } else {
                        coreWebApp.toastmsg('warning', 'Missing data', 'Failed to fetch material balance', false);
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                }
            });
        }
    }
    san.fetch_avl_qty_many = fetch_avl_qty_many;  

    function bal_qty_visible() {
        return coreWebApp.ModelBo.status() != 5;
    }
    san.bal_qty_visible = bal_qty_visible;
}(window.core_st.san));
