// Declare core_st Namespace
window.core_ptn = {};
(function (core_ptn) {

    function after_load() {
        core_ptn.sl_no = coreWebApp.ModelBo.stock_tran().length;
        if (coreWebApp.ModelBo.status() != 5 && coreWebApp.ModelBo.stock_id() !== '') {
            ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
                core_ptn.fetch_avl_qty(row);
            });
        }
        if (coreWebApp.ModelBo.for_receipt()) {
            $('#btn_receipt').css('background-color', 'green');
            $('#btn_receipt').css('color', 'white');
            $('#btn_receipt').css('font-size', 'medium');
            $('#btn-action').hide();
            if (coreWebApp.ModelBo.receipt_posted()) {
                $('#btn_receipt').hide();
            }
            coreWebApp.toggleEdit();
            $('#cmdsave').hide();
        }
    }
    core_ptn.after_load = after_load;

    function st_tran_add(row) {
        core_ptn.sl_no += 1;
        row.sl_no(core_ptn.sl_no);
    }
    core_ptn.st_tran_add = st_tran_add;

    function st_tran_delete() {
        core_ptn.sl_no = 0;
        ko.utils.arrayForEach(coreWebApp.ModelBo.stock_tran(), function (row) {
            core_ptn.sl_no += 1;
            row.sl_no(core_ptn.sl_no);
        });
    }
    core_ptn.st_tran_delete = st_tran_delete;


    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.material_type_id();
        }
        return fltr;
    }
    core_ptn.material_filter = material_filter;


    function uom_combo_filter(fltr, datacontext) {
        fltr = ' material_id = ' + datacontext.material_id();
        return fltr;
    }
    ;

    core_ptn.uom_combo_filter = uom_combo_filter;

    function source_stock_loc_combo_filter(fltr) {
        fltr = ' dept_id = ' + coreWebApp.ModelBo.annex_info.source_dept_id() + ' And branch_id= ' + coreWebApp.ModelBo.branch_id() + ' And sl_type_id in (2, 4)';
        return fltr;
    }
    core_ptn.source_stock_loc_combo_filter = source_stock_loc_combo_filter;

    function target_stock_loc_combo_filter(fltr) {
        fltr = ' dept_id = ' + coreWebApp.ModelBo.annex_info.target_dept_id() + ' And  branch_id= ' + coreWebApp.ModelBo.target_branch_id() + ' And sl_type_id in (1, 2, 4)';
        return fltr;
    }
    core_ptn.target_stock_loc_combo_filter = target_stock_loc_combo_filter;

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
    core_ptn.fetch_mat_info = fetch_mat_info;

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
    core_ptn.fetch_avl_qty = fetch_avl_qty;

    function enable_sl_lot(row) {
        return row.has_qc();
    }
    core_ptn.enable_sl_lot = enable_sl_lot;

    function tb_enable(dataItem) {
        if (!coreWebApp.ModelBo.annex_info.is_ib()) {
            coreWebApp.ModelBo.target_branch_id(coreWebApp.ModelBo.branch_id());
        }
        return (coreWebApp.ModelBo.annex_info.is_ib() && coreWebApp.ModelBo.stock_tran().length == 0);
    }
    core_ptn.tb_enable = tb_enable;

    function mat_info_enable(row) {
        return coreWebApp.ModelBo.doc_stage_id() == 'pick-list';
    }
    core_ptn.mat_info_enable = mat_info_enable;

    function source_dept_filter(fltr, datacontext) {
        fltr = coreWebApp.ModelBo.branch_id() + ' = Any(branch_ids) ';
        return fltr;
    }
    core_ptn.source_dept_filter = source_dept_filter;

    function target_dept_filter(fltr, datacontext) {
        fltr = coreWebApp.ModelBo.target_branch_id() + ' = Any(branch_ids)';
        return fltr;
    }
    core_ptn.target_dept_filter = target_dept_filter;

    function br_enable() {
        if (coreWebApp.ModelBo.stock_tran().length > 0) {
            return false;
        }
        return true;
    }
    core_ptn.br_enable = br_enable;
}(window.core_ptn));