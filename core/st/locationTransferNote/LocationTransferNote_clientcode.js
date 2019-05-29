/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.core_locationtransfer = {};
(function (core_locationtransfer) {
    
    function after_load() {
        core_locationtransfer.sl_no = coreWebApp.ModelBo.stock_tran().length;
        if (coreWebApp.ModelBo.status() != 5 && coreWebApp.ModelBo.stock_id() !== '') {
            ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
                core_locationtransfer.fetch_avl_qty(row);
            });
        }
    }
    core_locationtransfer.after_load = after_load;
    
    function st_tran_add(row) {
        core_locationtransfer.sl_no += 1;
        row.sl_no(core_locationtransfer.sl_no);
    }
    core_locationtransfer.st_tran_add = st_tran_add;
    
    function st_tran_delete() {
         core_locationtransfer.sl_no = 0;
         ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_locationtransfer.sl_no += 1;
            row.sl_no(core_locationtransfer.sl_no);
        });
    }
    core_locationtransfer.st_tran_delete = st_tran_delete;
    
  
    function material_filter(fltr, dataItem) {
        if(parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_locationtransfer.material_filter = material_filter;
    
          
    function uom_combo_filter(fltr, datacontext){
       fltr=' material_id = ' + datacontext.material_id();
       return fltr;
    };
    
    core_locationtransfer.uom_combo_filter=uom_combo_filter;
    
    function stock_loc_combo_filter(fltr){
        fltr=' branch_id= ' + coreWebApp.ModelBo.branch_id() + ' And sl_type_id = 1';
        return fltr;
    }
    
    core_locationtransfer.stock_loc_combo_filter=stock_loc_combo_filter;
    
    function fetch_mat_info(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.stock_location_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-info',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code, mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                stop_calc = true;
                if (typeof result.mat_id !== 'undefined') {
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
    core_locationtransfer.fetch_mat_info = fetch_mat_info;
    
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
    core_locationtransfer.fetch_avl_qty = fetch_avl_qty;
    
    
}(window.core_locationtransfer));