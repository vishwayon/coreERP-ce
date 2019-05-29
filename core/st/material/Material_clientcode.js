// Declare core_ar Namespace
window.core_material = {};
(function (core_material) {

    function after_load() {
//        $('#cmd_addnew_uom').hide();
    }
    core_material.after_load = after_load;
    
    function calculate_stock_ledger_item_amt(dataItem) {
        dataItem.item_amt((parseFloat(dataItem.received_qty()) * parseFloat(dataItem.unit_rate_lc())).toFixed(2));
    }
    core_material.calculate_stock_ledger_item_amt = calculate_stock_ledger_item_amt;


    //function for fetch uom in popup view
    function material_uom_alloc() {
        $.ajax({
            url: '?r=core%2Fst%2Fform%2Fuomschedulealloc',
            type: 'GET',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.material_uom_schedule_temp.removeAll();

                    // Fetch UoM Schedule
                    for (var p = 0; p < jsonResult['uom_schedule'].length; ++p)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('material_uom_schedule_temp', coreWebApp.ModelBo);
                        r1.uom_sch_id(jsonResult['uom_schedule'][p]['uom_sch_id']);
                        r1.uom_sch_desc(jsonResult['uom_schedule'][p]['uom_sch_desc']);
                        r1.is_select(jsonResult['uom_schedule'][p]['is_select']);
                    }
                    coreWebApp.ModelBo.material_uom_schedule_temp.valueHasMutated();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }

    function material_uom_alloc_init() {
        material_uom_alloc();
    }

    core_material.material_uom_alloc_init = material_uom_alloc_init;

    //function to update uom of material
    function material_uom_alloc_update() {

        //select UoM Schedule
        var checkCount = 0;
        var selectScheduleId = -1;
        for (var d = 0; d < coreWebApp.ModelBo.material_uom_schedule_temp().length; d++) {
            var r1 = coreWebApp.ModelBo.material_uom_schedule_temp()[d];
            if (r1.is_select()) {
                checkCount++;
                selectScheduleId = r1.uom_sch_id();
            }
        }
        if (checkCount === 0) {
            return 'UoM schedule is required.';
        } else if (checkCount > 1) {
            return 'Only one UoM schedule is allowed for each Material.';
        } else {
            $.ajax({
                url: '?r=core%2Fst%2Fform%2Fselectuom',
                type: 'GET',
                data: {'uom_sch_id': selectScheduleId},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {

                        //remove all uom 
                        coreWebApp.ModelBo.uom.removeAll();

                        //update uom for the material 
                        for (var p = 0; p < jsonResult['uom'].length; ++p)
                        {
                            var r1 = coreWebApp.ModelBo.addNewRow('uom', coreWebApp.ModelBo);
                            r1.uom_id(-1);
                            r1.uom_desc(jsonResult['uom'][p]['uom_desc']);
                            r1.is_base(jsonResult['uom'][p]['is_base']);
                            r1.material_id(coreWebApp.ModelBo.material_id);
                            r1.uom_qty(jsonResult['uom'][p]['uom_qty']);
                            r1.is_discontinued(false);
                            r1.is_su(false);
                        }
                        coreWebApp.ModelBo.uom.valueHasMutated();
//                        applysmartcontrols();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                }
            });
            return 'OK';
        }
    }
    core_material.material_uom_alloc_update = material_uom_alloc_update;

    //function for material uom
    function MaterialUomAlloc() {
        coreWebApp.showAlloc('core/st', '/material/UoMScheduleAlloc', 'core_material.material_uom_alloc_init', 'core_material.material_uom_alloc_update', 'core_material.CancelAllocUpdate');
    }
    core_material.MaterialUomAlloc = MaterialUomAlloc;

    function CancelAllocUpdate() {
    }
    core_material.CancelAllocUpdate = CancelAllocUpdate;


    function stock_loc_combo_filter(fltr, datacontext) {
        fltr = ' branch_id= ' + datacontext.branch_id();
        return fltr;
    }
    core_material.stock_loc_combo_filter = stock_loc_combo_filter;

    function enable_wac_price() {
        if (coreWebApp.ModelBo.annex_info.sale_price.price_type() == "WAC") {
            coreWebApp.ModelBo.annex_info.sale_price.lp_calc.markup_pcnt(0);
            coreWebApp.ModelBo.annex_info.sale_price.lp_calc.markup_pu(0);
            coreWebApp.ModelBo.annex_info.sale_price.sp_calc.fixed_pu(0);
            return true;
        }
        return false;
    }
    core_material.enable_wac_price = enable_wac_price;

    function enable_lp_price() {
        if (coreWebApp.ModelBo.annex_info.sale_price.price_type() == "LP") {
            coreWebApp.ModelBo.annex_info.sale_price.wac_calc.markup_pcnt(0);
            coreWebApp.ModelBo.annex_info.sale_price.wac_calc.markup_pu(0);
            coreWebApp.ModelBo.annex_info.sale_price.sp_calc.fixed_pu(0);
            return true;
        }
        return false;
    }
    core_material.enable_lp_price = enable_lp_price;

    function enable_fp_price() {
        if (coreWebApp.ModelBo.annex_info.sale_price.price_type() == "FP") {
            coreWebApp.ModelBo.annex_info.sale_price.wac_calc.markup_pcnt(0);
            coreWebApp.ModelBo.annex_info.sale_price.wac_calc.markup_pu(0);
            coreWebApp.ModelBo.annex_info.sale_price.lp_calc.markup_pcnt(0);
            coreWebApp.ModelBo.annex_info.sale_price.lp_calc.markup_pu(0);
            return true;
        }
        return false;
    }
    core_material.enable_fp_price = enable_fp_price;

    function coll_extra_info_action(row, tr) {
        rowdata = row.data();
    
        $.ajax({
            url: '?r=core/st/form/get-mat-detail',
            type: 'GET',
            data: {'material_id': rowdata.material_id},
            complete: function () {
                //coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                //if(jsonResult['status'] === 'ok'){
                var dtbl = '<div class="row" style="margin-left:0;">';
                dtbl += '<div class="row col-md-4"><table cellspacing=\'0\' border=\'0\' style=\'margin-left:50px;\'>';
                dtbl += '<tr><td style=\"font-size: 10px;\"><strong>Sale Rate</strong></td><td style=\"font-size: 16px;\">' + parseFloat(jsonResult['price']).toLocaleString('en-IN', {minimumFractionDigits: 2}) + '</td></tr>';
                dtbl += '<tr><td style=\"font-size: 10px;\"><strong>GST</strong></td><td>' + (isNaN(parseFloat(jsonResult['gst_pcnt'])) ? 'undefined ' : coreWebApp.formatNumber(jsonResult['gst_pcnt'], 2)) + '%</td></tr>';
                dtbl += '<tr><td style=\"font-size: 10px;\"><strong>Mfg</strong></td><td>' + jsonResult['mfg'] + '</td></tr>';
                dtbl += '<tr><td style=\"font-size: 10px;\"><strong>Mfg part no</strong></td><td>' + jsonResult['mfg_part_no'] + '</td></tr>';
                dtbl += '</table></div>';
                dtbl += '<div class="row col-md-6"><table cellspacing=\'0\' border=\'0\'>';
                dtbl += '<tr><td style=\"font-size: 10px;\"><strong style=\"margin-right:30px;\">UoM</strong>'+ jsonResult['uom_desc']
                        + '<strong style=\"margin-left:40px;\">Balance</strong></td><td style="text-align: right; font-size: 16px;">'+ parseFloat(jsonResult['bal']).toLocaleString('en-IN', {minimumFractionDigits: 3})
                        + '</td></tr>';
                for (var p = 0; p < jsonResult['balinfo'].length; ++p) {
                    dtbl += '<tr><td style=\"font-size: 10px;\">' + jsonResult['balinfo'][p]['stock_location_name']
                            + '</td><td  style="text-align: right;">' + parseFloat(jsonResult['balinfo'][p]['balance_qty_base']).toLocaleString('en-IN', {minimumFractionDigits: 3}) + '</td></tr>';
                }
                dtbl += '</table></div>';
                dtbl += '</div>';
                row.child(dtbl).show();
                tr.addClass('shown');
                //}
            },
            error: function (data) {
                //coreWebApp.stoploading();
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_material.coll_extra_info_action = coll_extra_info_action;


    //function for material uom
    function mat_cat_info() {
//        coreWebApp.showAlloc('core/st', '/material/MatCatInfo', 'core_material.mat_cat_info_init', 'core_material.mat_cat_info_update');
        mat_cat_info_init();
    }
    core_material.mat_cat_info = mat_cat_info;

    //function to fetch mat cat key and attr
    function mat_cat_info_init() {
        if (coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys().length > 0 || coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys().length > 0) {
            var res = coreWebApp.customprompt('error', 'Changing Stock Item will result in loss of Category details. Are you sure you want to change?', function () {
                $.ajax({
                    url: '?r=core%2Fst%2Fform%2Fgetmatcatinfo',
                    type: 'GET',
                    data: {'mat_cat_id': coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_id()},
                    complete: function () {
                        coreWebApp.stoploading();
                    },
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        if (jsonResult['status'] === 'ok') {
                            coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys.removeAll();
                            for (var p = 0; p < jsonResult['mat_cat_key'].length; ++p)
                            {
                                var r1 = coreWebApp.ModelBo.addNewRow('annex_info.mat_cat_info.mat_cat_keys', coreWebApp.ModelBo);
                                r1.mat_cat_key_id(jsonResult['mat_cat_key'][p]['mat_cat_key_id']);
                                r1.mat_cat_key(jsonResult['mat_cat_key'][p]['mat_cat_key']);
                                r1.mat_cat_key_value(false);
                            }

                            coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_attrs.removeAll();
                            for (var p = 0; p < jsonResult['mat_cat_attr'].length; ++p)
                            {
                                var r1 = coreWebApp.ModelBo.addNewRow('annex_info.mat_cat_info.mat_cat_attrs', coreWebApp.ModelBo);
                                r1.mat_cat_attr_id(jsonResult['mat_cat_attr'][p]['mat_cat_attr_id']);
                                r1.mat_cat_attr(jsonResult['mat_cat_attr'][p]['mat_cat_attr']);
                                r1.mat_cat_attr_value('');
                            }
                            coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys.valueHasMutated();
                            coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_attrs.valueHasMutated();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                    }
                });
            });
        } else {
            $.ajax({
                url: '?r=core%2Fst%2Fform%2Fgetmatcatinfo',
                type: 'GET',
                data: {'mat_cat_id': coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_id()},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys.removeAll();
                        for (var p = 0; p < jsonResult['mat_cat_key'].length; ++p)
                        {
                            var r1 = coreWebApp.ModelBo.addNewRow('annex_info.mat_cat_info.mat_cat_keys', coreWebApp.ModelBo);
                            r1.mat_cat_key_id(jsonResult['mat_cat_key'][p]['mat_cat_key_id']);
                            r1.mat_cat_key(jsonResult['mat_cat_key'][p]['mat_cat_key']);
                            r1.mat_cat_key_value(false);
                        }

                        coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_attrs.removeAll();
                        for (var p = 0; p < jsonResult['mat_cat_attr'].length; ++p)
                        {
                            var r1 = coreWebApp.ModelBo.addNewRow('annex_info.mat_cat_info.mat_cat_attrs', coreWebApp.ModelBo);
                            r1.mat_cat_attr_id(jsonResult['mat_cat_attr'][p]['mat_cat_attr_id']);
                            r1.mat_cat_attr(jsonResult['mat_cat_attr'][p]['mat_cat_attr']);
                            r1.mat_cat_attr_value('');
                        }
                        coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_keys.valueHasMutated();
                        coreWebApp.ModelBo.annex_info.mat_cat_info.mat_cat_attrs.valueHasMutated();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                }
            });
        }
    }

    core_material.mat_cat_info_init = mat_cat_info_init;

    //function to update uom of material
    function mat_cat_info_update() {
        return 'OK';
    }
    core_material.mat_cat_info_update = mat_cat_info_update;


    function selectBU(data) {
        if (data.is_base()) {
            ko.utils.arrayForEach(coreWebApp.ModelBo.uom(), function (item) {
                if (data.uom_desc() != item.uom_desc()) {
                    item.is_base(false);
                }
            });
        }
        //return true;
    }
    core_material.SelectBU = selectBU;

    function selectSU(data) {
        if (data.is_su()) {
            ko.utils.arrayForEach(coreWebApp.ModelBo.uom(), function (item) {
                if (data.uom_desc() != item.uom_desc()) {
                    item.is_su(false);
                }
            });
        }
        //return true;
    }
    core_material.SelectSU = selectSU;
    
    function war_info_enabled() {
        return coreWebApp.ModelBo.annex_info.war_info.has_war();
    }
    core_material.war_info_enabled = war_info_enabled;
    
    function uom_qty_enabled(row) {
        return !row.is_base();
    }
    core_material.uom_qty_enabled = uom_qty_enabled;
    
    function is_su_enabled(row) {
        return (row.uom_type_id() == 104);
    }
    core_material.is_su_enabled = is_su_enabled;
    
    function st_excess_pcnt_enabled() {
        if(!coreWebApp.ModelBo.annex_info.st_allow_excess()){
            coreWebApp.ModelBo.annex_info.st_excess_pcnt(0);
        }
        return coreWebApp.ModelBo.annex_info.st_allow_excess();
    }
    core_material.st_excess_pcnt_enabled = st_excess_pcnt_enabled;
    
    function uom_row_added(row) {
        row.is_base(false);
        row.is_su(false);
        row.uom_qty(1);
        row.uom_type_id(104); 
        var el = coreWebApp.latestElement;
        $(el[0]).find('[type=SmartCombo]').each(function () {
            $(this).trigger("change");
        });
    }
    core_material.uom_row_added = uom_row_added;
    
    function allow_delete(pr, prop, rw) {
        return (rw.uom_id()==-1);
    }
    core_material.allow_delete = allow_delete;
}(window.core_material));
