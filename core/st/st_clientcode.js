// Declare core_st Namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
(function (core_st) {

    function stock_invoice_edit_method() {
        console.log('editing stock invoice stock items row');
    }
    core_st.stock_invoice_edit_method = stock_invoice_edit_method;

    function sp_uom_combo_filter(fltr, datacontext) {
        fltr = ' material_id = ' + datacontext.material_id();
        return fltr;
    }
    core_st.sp_uom_combo_filter = sp_uom_combo_filter;

    function view_gl_init() {
        core_ac.gl_distribution('st.stock_control', coreWebApp.ModelBo.stock_id());
    }
    core_st.view_gl_init = view_gl_init;


    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_st.view_gl_init');
    }
    core_st.view_gl = view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.stock_id() != '' && coreWebApp.ModelBo.stock_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    core_st.visible_gl_distribution = visible_gl_distribution;


    function CancelAllocUpdate() {
        // do nothing
    }
    core_st.CancelAllocUpdate = CancelAllocUpdate;

    function get_qc_status(sdata, tbl) {
        var sids = [];
        if (sdata.length == 0) {
            return;
        }
        var dc = $('#docstatus').val();
        if (dc == 1 || dc == 2 || dc == 3 || dc == 4) {
            $.each(sdata, function (idx, itm) {
                sids.push(itm.stock_id);
            });
            $.ajax({
                url: '?r=core/st/utils/grn-qc-status',
                data: {sids: sids},
                method: 'POST',
                dataType: 'json',
                success: function (result) {
                    $.each(result.qc_info, function (idqc, itm_qc) {
                        $.each(sdata, function (idx, itm) {
                            if (itm.stock_id == itm_qc.stock_id) {
                                itm.qc_status = itm_qc.qc_status;
                            }
                        });
                    });
                    tbl.clear();
                    tbl.rows.add(sdata);
                    tbl.draw();
                }
            });
        }
    }
    core_st.get_qc_status = get_qc_status;

    function material_filter(fltr, dataItem) {
        if (coreWebApp.ModelBo.material_type_id() != -1) {
            fltr = ' material_type_id = ' + coreWebApp.ModelBo.material_type_id();
        }
        return fltr;
    }
    core_st.material_filter = material_filter;

    function rpt_sl_lot_stmt_material_filter(fltr, dataItem) {
        if (parseInt($('#pmaterial_type_id').val()) !== -1 && parseInt($('#pmaterial_type_id').val()) !== 0) {
            fltr = ' (material_type_id = ' + $('#pmaterial_type_id').val() + ' Or material_id = -2)';
        }
        return fltr;
    }
    core_st.rpt_sl_lot_stmt_material_filter = rpt_sl_lot_stmt_material_filter;

    function rpt_material_type_material_filter(fltr, dataItem) {
        if (parseInt($('#pmaterial_type_id').val()) !== -1 && parseInt($('#pmaterial_type_id').val()) !== 0) {
            fltr = ' material_type_id = ' + $('#pmaterial_type_id').val();
        }
        return fltr;
    }
    core_st.rpt_material_type_material_filter = rpt_material_type_material_filter;

    function stock_location_branch_filter(fltr, dataItem) {
        if (parseInt($('#pbranch_id').val()) !== -1 && parseInt($('#pbranch_id').val()) !== 0) {
            fltr = ' branch_id = ' + $('#pbranch_id').val();
        }
        return fltr;
    }
    core_st.stock_location_branch_filter = stock_location_branch_filter;

    function show_sl_lot_alloc() {
        return false;
    }
    core_st.show_sl_lot_alloc = show_sl_lot_alloc;

    function  sl_enable_info() {
        if (coreWebApp.ModelBo.stock_location_id() == -1) {
            return true;
        }
        return false;
    }
    core_st.sl_enable_info = sl_enable_info;

    function rpt_stock_location_branch_filter(dataItem) {
        if (parseInt($('#pbranch_id').val()) !== -1 && parseInt($('#pbranch_id').val()) !== 0) {
            fltr = ' branch_id = ' + $('#pbranch_id').val();
        }
        if (parseInt($('#pbranch_id').val()) !== -1 && parseInt($('#pbranch_id').val()) == 0) {
            fltr = ' all_sl_branch_id = 0';
        }
        return fltr;
    }
    core_st.rpt_stock_location_branch_filter = rpt_stock_location_branch_filter;
    
    function afterPageRefresh(newval) {
        $('#pstock_location_id').val(0);
        $('#pstock_location_id').trigger('change.select2');
    }
    core_st.afterPageRefresh = afterPageRefresh;
    
    function rpt_st_bal_after_page_refresh(newval) {
        $('#psl_id').val(0);
        $('#psl_id').trigger('change.select2');
    }
    core_st.rpt_st_bal_after_page_refresh = rpt_st_bal_after_page_refresh;    
    
    function rpt_sa_material_filter(fltr, dataItem) {
        if (parseInt($('#pmt_ids').val()) > 0) {
            fltr = ' (material_type_id = ' + $('#pmt_ids').val() + ' Or material_id = -2)';
        }
        return fltr;
    }
    core_st.rpt_sa_material_filter = rpt_sa_material_filter;   
        
  function rpt_sl_slt_branch_filter(fltr, dataItem) { 
        if (parseInt($('#pbranch_ids').val()) > 0 || parseInt($('#pslt_ids').val()) > 0) {
            fltr = " stock_location_id = 0 or ((branch_id = any('{" + $('#pbranch_ids').val() + 
                    "}') or 0 = any('{" + $('#pbranch_ids').val() + "}')) and (sl_type_id = any('{" + $('#pslt_ids').val() + 
                    "}') or 0 = any('{" + $('#pslt_ids').val() + "}')))";
        } 
        return fltr;
    }
    core_st.rpt_sl_slt_branch_filter = rpt_sl_slt_branch_filter;
    
    function rpt_material_filter(fltr, dataItem) {
        if (parseInt($('#pmat_type_id').val()) > 0) {
            fltr = ' (material_type_id = ' + $('#pmat_type_id').val() + ' Or material_id = -2)';
        }
        return fltr;
    }
    core_st.rpt_material_filter = rpt_material_filter;
    
    function rpt_sc_material_filter(fltr, dataItem) {
        if (parseInt($('#pmaterial_type_id').val()) > 0) {
            fltr = ' (material_type_id = ' + $('#pmaterial_type_id').val() + ' Or material_id = -2)';
        }
        return fltr;
    }
    core_st.rpt_sc_material_filter = rpt_sc_material_filter;
    
    function set_mat_sl_bal_tooltip(opts, callback) {
        opts = $.merge(opts, {
            mat_id: -1,
            as_on: ""
        });
        $.ajax({
            url: '?r=/core/st/form/get-mat-sl-bal',
            type: 'GET',
            data: opts,
            success: function(result) {
                callback(result);
                //$(el).attr('title', "");
                //$(el).tooltip({html: true, content: result});
            }
        });
    }
    core_st.set_mat_sl_bal_tooltip = set_mat_sl_bal_tooltip;

}(window.core_st));
