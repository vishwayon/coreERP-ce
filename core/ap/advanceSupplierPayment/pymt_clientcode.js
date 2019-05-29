// Declare core_st Namespace
typeof window.core_ap == 'undefined' ? window.core_ap = {} : '';
window.core_ap.pymt = {};

(function (pymt) {

    function adv_pymt_fc_changed(dataItem) {
        if (parseFloat(dataItem.fc_type_id()) == 0) {
            dataItem.gross_adv_amt_fc(0);
        } else {
            dataItem.gross_adv_amt((parseFloat(dataItem.gross_adv_amt_fc()) * parseFloat(dataItem.exch_rate())).toFixed(2));
        }
    }
    pymt.adv_pymt_fc_changed = adv_pymt_fc_changed;

    function account_combo_filter(fltr) {
        if (coreWebApp.ModelBo.pymt_type() == 0) {
            fltr = ' account_type_id in(1, 2)';
        }
        if (coreWebApp.ModelBo.pymt_type() == 1) {
            fltr = ' account_type_id not in (0, 1, 2, 7, 12, 45, 46, 47)';
        }
        return fltr;
    }
    pymt.account_combo_filter = account_combo_filter;

    function supplier_changed() {
        $.ajax({
            url: '?r=core/ap/form/fetch-supp-name',
            type: 'GET',
            dataType: 'json',
            data: {'supplier_id': coreWebApp.ModelBo.supplier_account_id(), doc_date: coreWebApp.ModelBo.doc_date()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.supplier_detail(jsonResult['supplier_name']);
                    coreWebApp.ModelBo.unstl_adv_amt(jsonResult['unstl_adv_amt']);
                    coreWebApp.ModelBo.annex_info.is_tds_applied(jsonResult['is_tds_applied']);
                    coreWebApp.lookupCache.add('btt_person_type_id', jsonResult['person_type_id'], jsonResult['person_type']);
                    coreWebApp.ModelBo.btt_person_type_id(jsonResult['person_type_id']);
                    coreWebApp.lookupCache.add('btt_section_id', jsonResult['section_id'], jsonResult['section']);
                    coreWebApp.ModelBo.btt_section_id(jsonResult['section_id']);
                    if (jsonResult['rate_info'].length > 0) {
                        coreWebApp.ModelBo.btt_tds_base_rate_perc(jsonResult['rate_info'][0]['base_rate_perc']);
                        coreWebApp.ModelBo.btt_tds_base_rate_amt(0);
                        coreWebApp.ModelBo.btt_tds_base_rate_amt_fc(0);
                        coreWebApp.ModelBo.btt_tds_ecess_perc(jsonResult['rate_info'][0]['ecess_perc']);
                        coreWebApp.ModelBo.btt_tds_ecess_amt(0);
                        coreWebApp.ModelBo.btt_tds_ecess_amt_fc(0);
                        coreWebApp.ModelBo.btt_tds_surcharge_perc(jsonResult['rate_info'][0]['surcharge_perc']);
                        coreWebApp.ModelBo.btt_tds_surcharge_amt(0);
                        coreWebApp.ModelBo.btt_tds_surcharge_amt_fc(0);
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    pymt.supplier_changed = supplier_changed;

    function target_branch_enable(dataItem) {
        if (dataItem.is_inter_branch() && coreWebApp.ModelBo.annex_info.po_no() == '') {
            return true;
        } else {
            return false;
        }
    }
    pymt.target_branch_enable = target_branch_enable;

    function calculate_adv_pymt_tds(dataItem) {
        if (parseFloat(dataItem.fc_type_id()) == 0) {
                coreWebApp.ModelBo.btt_tds_base_rate_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_tds_base_rate_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_ecess_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_tds_ecess_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_surcharge_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_tds_surcharge_perc()) / 100)));
        } else {
            dataItem.gross_adv_amt((parseFloat(dataItem.gross_adv_amt_fc()) * parseFloat(dataItem.exch_rate())).toFixed(2));
                coreWebApp.ModelBo.btt_tds_base_rate_amt_fc(Math.round(parseFloat(dataItem.gross_adv_amt_fc()) * (parseFloat(coreWebApp.ModelBo.btt_tds_base_rate_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_base_rate_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_tds_base_rate_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_ecess_amt_fc(Math.round(parseFloat(dataItem.gross_adv_amt_fc()) * (parseFloat(coreWebApp.ModelBo.btt_tds_ecess_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_ecess_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_.tds_ecess_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_surcharge_amt_fc(Math.round(parseFloat(dataItem.gross_adv_amt_fc()) * (parseFloat(coreWebApp.ModelBo.btt_tds_surcharge_perc()) / 100)));
                coreWebApp.ModelBo.btt_tds_surcharge_amt(Math.round(parseFloat(dataItem.gross_adv_amt()) * (parseFloat(coreWebApp.ModelBo.btt_tds_surcharge_perc()) / 100)));
        }
    }
    pymt.calculate_adv_pymt_tds = calculate_adv_pymt_tds;

    function visible_tds(dataItem) {
        if(coreWebApp.ModelBo.annex_info.is_tds_applied()){
            return true;
        }
        return false;
    }
    pymt.visible_tds = visible_tds;

    function enable_asp_info(dataItem) {
        if (coreWebApp.ModelBo.annex_info.po_no() == '') {
            return true;
        } else {
            return false;
        }
    }
    pymt.enable_asp_info = enable_asp_info;

    function visible_po_info(dataItem) {
        if (coreWebApp.ModelBo.annex_info.po_no() == '') {
            return false;
        } else {
            return true;
        }
    }
    pymt.visible_po_info = visible_po_info;
    
    function tds_base_desc(row) {
        return "TDS @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_base_rate_perc()), 0) + "%";
    }
    pymt.tds_base_desc = tds_base_desc;

    function tds_ecess_desc(row) {
        return "E-cess @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_ecess_perc()), 0) + "%";
    }
    pymt.tds_ecess_desc = tds_ecess_desc;

    function tds_surch_desc(row) {
        return "Surch. @ " + coreWebApp.formatNumber(parseFloat(row.btt_tds_surcharge_perc()), 0) + "%";
    }
    pymt.tds_surch_desc = tds_surch_desc;

    function tds_total(row) {
        var tds = parseFloat(row.btt_tds_base_rate_amt()) + parseFloat(row.btt_tds_ecess_amt()) + parseFloat(row.btt_tds_surcharge_amt());
        return coreWebApp.formatNumber(tds, 2);
    }
    pymt.tds_total = tds_total;

    function select_sec_info(row) {
        opts = {
            doc_date: coreWebApp.ModelBo.doc_date(),
            person_type_id: coreWebApp.ModelBo.btt_person_type_id(),
            row: row,
            after_update: reset_tds_rate
        };
        //core_tx.gst.item_gtt_reset(gstOpts);
        core_tds.tds_sec.select_tds_sec(opts);
    }
    pymt.select_sec_info = select_sec_info;

    function reset_tds_rate() {        
        calculate_adv_pymt_tds(coreWebApp.ModelBo);
    }
    
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
                debit_amt_total: coreWebApp.ModelBo.gross_adv_amt(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                dc: 'C',
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
    pymt.sub_head_alloc_click = sub_head_alloc_click;

    function sub_head_alloc_after_update() {
    }

}(window.core_ap.pymt));