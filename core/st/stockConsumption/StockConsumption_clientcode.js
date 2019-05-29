
// Declare core_st Namespace
window.core_stockconsumption = {};
(function (core_stockconsumption) {
    core_stockconsumption.sl_no = 0;

    function after_load() {
        core_stockconsumption.sl_no = coreWebApp.ModelBo.stock_tran().length;
        if (coreWebApp.ModelBo.status() != 5 && coreWebApp.ModelBo.stock_id() != '') {
            fetch_avl_qty_many();
        }
    }
    core_stockconsumption.after_load = after_load;

    function st_tran_add(row) {
        core_stockconsumption.sl_no += 1;
        row.sl_no(core_stockconsumption.sl_no);
        set_default_sl(row);
    }
    core_stockconsumption.st_tran_add = st_tran_add;

    function st_tran_delete() {
        core_stockconsumption.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_stockconsumption.sl_no += 1;
            row.sl_no(core_stockconsumption.sl_no);
        });
    }
    core_stockconsumption.st_tran_delete = st_tran_delete;

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
                    if (typeof row.has_qc !== 'undefined') {
                        row.has_qc(result.has_qc);
                    }
                    if (row.issued_qty() == 0) {
                        row.issued_qty(1);
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
    core_stockconsumption.fetch_mat_info = fetch_mat_info;

    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_stockconsumption.material_filter = material_filter;

    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }
    
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
    core_stockconsumption.fetch_avl_qty = fetch_avl_qty;
    
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
    core_stockconsumption.fetch_avl_qty_many = fetch_avl_qty_many;  
    
    function enable_sl_lot(row) {
        return row.has_qc();
    }
    core_stockconsumption.enable_sl_lot = enable_sl_lot;
}(window.core_stockconsumption));
