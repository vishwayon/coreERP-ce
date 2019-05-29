// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.mcn = {};

(function (mcn) {   
    mcn.sl_no = 0;
    stop_calc = false;
    
    function after_load() {
        mcn.sl_no = coreWebApp.ModelBo.stock_tran().length;
        if (coreWebApp.ModelBo.status() != 5) {
            fetch_avl_qty_many();
        }
    }
    mcn.after_load = after_load;
    
    function st_tran_add(row) {
        mcn.sl_no += 1;
        row.sl_no(mcn.sl_no);
        set_default_sl(row);
    }
    mcn.st_tran_add = st_tran_add;
    
    function set_default_sl(row) {
        if (typeof coreWebApp.ModelBo.default_sl === 'undefined')
            return;
        var sl = coreWebApp.ModelBo.default_sl;
        row.stock_location_id(sl.stock_location_id());
        coreWebApp.trigger_change('stock_location_id', sl.stock_location_id(), sl.stock_location_name());
    }
    
    function st_tran_delete() {
        total_calc();
    }
    mcn.st_tran_delete = st_tran_delete;     
    
    function uom_combo_filter(fltr, datacontext){
       fltr=' material_id = ' + datacontext.material_id();
       return fltr;
    };
    
    mcn.uom_combo_filter=uom_combo_filter;
    
    function stock_loc_combo_filter(fltr){
        fltr=' branch_id= ' + coreWebApp.ModelBo.branch_id();
        return fltr;
    }
    
    mcn.stock_loc_combo_filter=stock_loc_combo_filter;

    function output_mat_filter(fltr, dataItem) {
        if (parseInt(dataItem.annex_info.output_mat_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.annex_info.output_mat_type_id();
        }
        return fltr;
    }
    mcn.output_mat_filter = output_mat_filter;        
    
    function output_uom_filter(fltr, dataItem){
       fltr=' material_id = ' + dataItem.annex_info.output_mat_id();
       return fltr;
    };
    mcn.output_uom_filter=output_uom_filter;

    function mat_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    mcn.mat_filter = mat_filter;        

    function fetch_output_mat_info(row) {
        var mat_id = coreWebApp.ModelBo.annex_info.output_mat_id();
        var sl_id = parseInt(coreWebApp.ModelBo.annex_info.output_sl_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-info',
            type: 'GET',
            dataType: 'json',
            data: {mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                if (typeof result.mat_id !== 'undefined') {
                    stop_calc = true;
                    coreWebApp.ModelBo.annex_info.output_mat_type_id(result.material_type_id);
                    $('[id="annex_info.output_mat_type_id"]').trigger('change');
                    coreWebApp.ModelBo.annex_info.output_uom_id(result.uom_id);
                    $('[id="annex_info.output_uom_id"]').trigger('change');
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
    mcn.fetch_output_mat_info = fetch_output_mat_info;

    function fetch_mat_info(row) {
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-cc',
            type: 'GET',
            dataType: 'json',
            data: {mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
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
                    row.has_qc(result.has_qc);  
                    row.sl_mat_bal(result.sl_mat_bal);
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
    mcn.fetch_mat_info = fetch_mat_info;
    
    function total_calc(row) {
        var issue_qty_tot = new Number(0.00);
        mcn.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            mcn.sl_no += 1;
            row.sl_no(mcn.sl_no);
            issue_qty_tot += Number.parseFloat(row.issued_qty());
        });
        coreWebApp.ModelBo.annex_info.input_tot_qty(issue_qty_tot.toFixed(3));
    }
    mcn.total_calc = total_calc;
    
     function fetch_avl_qty(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-info',
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
    mcn.fetch_avl_qty = fetch_avl_qty;
    
    function enable_sl_lot(row) {
        return row.has_qc();
    }
    mcn.enable_sl_lot = enable_sl_lot;

    function sl_bal_qty_visible(row) {
        return coreWebApp.ModelBo.status() != 5;
    }
    mcn.sl_bal_qty_visible = sl_bal_qty_visible;

    function get_sl_mat_bal(row) {
        if (row.sl_mat_bal() == '') {
            core_st.set_mat_sl_bal_tooltip({
                mat_id: row.material_id(),
                as_on: coreWebApp.ModelBo.doc_date()
            }, function (result) {
                row.sl_mat_bal(result);
            });
        }
    }
    mcn.get_sl_mat_bal = get_sl_mat_bal;

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
    mcn.fetch_avl_qty_many = fetch_avl_qty_many;
}(window.core_st.mcn));
