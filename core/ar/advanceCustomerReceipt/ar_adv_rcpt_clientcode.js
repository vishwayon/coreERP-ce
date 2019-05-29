// Declare core_ar Namespace
window.core_ar_adv_rcpt = {};
(function (core_ar_adv_rcpt) {
    
    function fetch_cust_info() {
        // Fetch customer unstl adv. amt.
        $.ajax({
            url: '?r=core/ar/form/fetch-cust-adv',
            type: 'GET',
            dataType: 'json',
            data: {
                customer_id: coreWebApp.ModelBo.customer_account_id(),
                doc_date: coreWebApp.ModelBo.doc_date()
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.unstl_adv_amt(jsonResult['unstl_adv_amt']);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        opts = {
            cust_id: coreWebApp.ModelBo.customer_account_id(),
            after_update: fetch_cust_info_after_update
        };
        core_ar.get_address(opts);
    }
    core_ar_adv_rcpt.fetch_cust_info = fetch_cust_info;

    function visible_unstl_adv(dataItem) {
        return coreWebApp.ModelBo.status() != 5;
    }
    core_ar_adv_rcpt.visible_unstl_adv = visible_unstl_adv;
    
    function fetch_cust_info_after_update(opts) {
        if(typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', opts.result.gst_state_id, opts.result.gst_state);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin(opts.result.gstin);
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_address(opts.result.addr);
        }else{
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(-1);
            coreWebApp.trigger_change('annex_info.gst_output_info.customer_state_id', -1, "");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_gstin("");
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_address("");
        }
        // update vat_type
        if(coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id(gstOpts.vat_type_id);
            coreWebApp.trigger_change('annex_info.gst_output_info.vat_type_id', gstOpts.vat_type_id);
            
            gstOpts.tran = coreWebApp.ModelBo;
            gstOpts.call_back = total_calc;
            core_tx.gst.reapply_gtt(gstOpts);
        }
    }
    core_ar_adv_rcpt.fetch_cust_info_after_update = fetch_cust_info_after_update;
    
    function select_cust_addr(opts) {
        var opts = {
            cust_id: coreWebApp.ModelBo.customer_account_id(),
            after_update: select_cust_addr_after_update
        };
        core_ar.select_address(opts);
    }
    core_ar_adv_rcpt.select_cust_addr = select_cust_addr;

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
            coreWebApp.trigger_change('annex_info.gst_output_info.vat_type_id', gstOpts.vat_type_id);
            if (old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo;
                gstOpts.call_back = total_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
            $('#vat_type_id').trigger('change');
        }
    }
    core_ar_adv_rcpt.select_cust_addr_after_update = select_cust_addr_after_update;
    
    function mat_info_editable() {
        return coreWebApp.ModelBo.annex_info.gst_ref.is_mat();
    }
    core_ar_adv_rcpt.mat_info_editable = mat_info_editable;
    
    function material_filter(fltr, dataItem) {
        if (parseInt(dataItem.annex_info.gst_ref.material_type_id()) !== -1) {
            fltr = ' material_type_id = ' + dataItem.annex_info.gst_ref.material_type_id();
        }
        return fltr;
    }
    core_ar_adv_rcpt.material_filter = material_filter;
    
    function fetch_mat_info() {
        var mat_id = coreWebApp.ModelBo.annex_info.gst_ref.material_id();
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-sale',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: '', mat_id: mat_id, stock_loc_id: -1, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                var gst_hsn_info = $.parseJSON(result.gst_hsn_info);
                if (typeof result.mat_id !== 'undefined') {
                    var model = coreWebApp.ModelBo;
                    model.annex_info.gst_ref.material_type_id(result.material_type_id);
                    coreWebApp.trigger_change('material_type_id', result.material_type_id, result.mt_name);
                    if (parseInt(model.annex_info.gst_ref.material_id()) !== parseInt(result.mat_id)) {
                        model.annex_info.gst_ref.material_id(result.mat_id);
                        coreWebApp.trigger_change('material_id', result.material_id, result.mat_name);
                    }
                    // Get Gst info
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id, 
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        gst_hsn_info: gst_hsn_info,
                        row: model
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);
                    total_calc();
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_ar_adv_rcpt.fetch_mat_info = fetch_mat_info;
    
    function fetch_income_type_info() {
        var income_acc_id = coreWebApp.ModelBo.annex_info.gst_ref.income_account_id();
        var income_type_id = coreWebApp.ModelBo.annex_info.gst_ref.income_type_id();
        var vat_type_id = parseInt(coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id());
        $.ajax({
            url: '?r=core/ar/form/get-income-type-hsn-gst-info',
            type: 'GET',
            dataType: 'json',
            data: {account_id: income_acc_id, doc_type: '', income_type_id: income_type_id},
            success: function (gst_hsn_info) {
                if (typeof gst_hsn_info.hsn_sc_code !== 'undefined') {
                    var model = coreWebApp.ModelBo;
                    // Get Gst info
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id, 
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        gst_hsn_info: gst_hsn_info,
                        row: model
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);
                    total_calc();
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_ar_adv_rcpt.fetch_income_type_info = fetch_income_type_info;
    
    function income_info_editable() {
        return coreWebApp.ModelBo.annex_info.gst_ref.is_income();
    }
    core_ar_adv_rcpt.income_info_editable = income_info_editable;
    
    function income_account_filter() {
        var income_type_id = coreWebApp.ModelBo.annex_info.gst_ref.income_type_id();
        fltr = " account_id in (Select account_id from ar.income_type_tran where income_type_id=" + income_type_id + ")";
        return fltr;
    }
    core_ar_adv_rcpt.income_account_filter = income_account_filter;
    
    function total_calc() {
        coreWebApp.ModelBo.total_amt((parseFloat(coreWebApp.ModelBo.tds_amt()) + parseFloat(coreWebApp.ModelBo.debit_amt())).toFixed(2));
        
        if((coreWebApp.ModelBo.annex_info.gst_ref.is_mat() || coreWebApp.ModelBo.annex_info.gst_ref.is_income())
            && (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1)) {
            var bt_amt = Number.parseFloat(coreWebApp.ModelBo.total_amt());
            core_tx.gst.item_gtt_calc_inverse({
                bt_amt: bt_amt,
                row: coreWebApp.ModelBo
            });
        }
        var tax_amt = parseFloat(coreWebApp.ModelBo.gtt_sgst_amt()) + parseFloat(coreWebApp.ModelBo.gtt_cgst_amt()) 
                        + parseFloat(coreWebApp.ModelBo.gtt_igst_amt()) + parseFloat(coreWebApp.ModelBo.gtt_cess_amt());
        coreWebApp.ModelBo.annex_info.gst_ref.tax_amt(tax_amt.toFixed(2));
    }
    core_ar_adv_rcpt.total_calc = total_calc;
    
    function sub_head_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total: coreWebApp.ModelBo.debit_amt(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                dc: 'D',
                sl_no: 0,
                ref_no: coreWebApp.ModelBo.ref_no(),
                ref_desc: coreWebApp.ModelBo.ref_desc(),
                row: coreWebApp.ModelBo,
                shl_tran_name: 'shl_head_tran',
                rla_tran_name: 'rla_head_tran',
                after_update: sub_head_alloc_after_update
            };
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    core_ar_adv_rcpt.sub_head_alloc_click = sub_head_alloc_click;


    function sub_head_alloc_after_update() {
        total_calc();
    }

}(window.core_ar_adv_rcpt));

