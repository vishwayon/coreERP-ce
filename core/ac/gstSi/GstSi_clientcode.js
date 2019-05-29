// Declare core_ap Namespace
typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
window.core_ac.gst_si = {};

(function (gst_si) {
    gst_si.sl_no = 0;

    function afterload() {
        gst_si.sl_no = coreWebApp.ModelBo.si_tran().length;
    }
    gst_si.afterload = afterload;

    function supplier_state_update() {
        // This txn would be in the nature of purchase as a self invoice is created for receipt of supply
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_PURCH,
                origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                is_ctp: false
            };
            core_tx.gst.get_vat_type(gstOpts);
            var old_vat_type_id = coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id();
            coreWebApp.ModelBo.annex_info.gst_input_info.vat_type_id(gstOpts.vat_type_id);
            if (coreWebApp.ModelBo.si_tran().length > 0 && old_vat_type_id != gstOpts.vat_type_id) {
                gstOpts.tran = coreWebApp.ModelBo.si_tran;
                gstOpts.call_back = redo_item_calc;
                core_tx.gst.reapply_gtt(gstOpts);
            }
        }
    }
    gst_si.supplier_state_update = supplier_state_update;

    function fetch_hsn_info(row) {
        var hsn_sc_id = row.hsn_sc_id();
        $.ajax({
            url: '?r=core/ap/form/get-hsn-gst-info',
            type: 'GET',
            dataType: 'json',
            data: {hsn_sc_id: hsn_sc_id},
            success: function (gst_hsn_info) {
                if (typeof gst_hsn_info.hsn_sc_code !== 'undefined') {
                    stop_calc = true;
                    // This is GST
                    core_tx.gst.item_gtt_reset({
                        txn_type: core_tx.gst.TXN_PURCH,
                        origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                        target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        gst_hsn_info: gst_hsn_info,
                        is_ctp: false,
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
    gst_si.fetch_hsn_info = fetch_hsn_info;

    function si_tran_add(row) {
        gst_si.sl_no += 1;
        row.sl_no(gst_si.sl_no);
        row.gtt_apply_itc(true);
        row.branch_id(coreWebApp.ModelBo.branch_id());
    }
    gst_si.si_tran_add = si_tran_add;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    gst_si.tax_amt_ov = tax_amt_ov;

    function item_calc(row) {
        var bt_amt = parseFloat(row.gtt_bt_amt());
        // This is GST
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        total_calc();
    }
    gst_si.item_calc = item_calc;

    function total_calc() {
        var tax_amt_tot = new Number(0.00);
        var gtt_bt_amt_tot = new Number(0.00);

        gst_si.sl_no = 0;
        // Total each bill item
        ko.utils.arrayForEach(coreWebApp.ModelBo.si_tran(), function (row) {
            gst_si.sl_no += 1;
            row.sl_no(gst_si.sl_no);
            gtt_bt_amt_tot += parseFloat(row.gtt_bt_amt());
            tax_amt_tot += parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                    + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        });
        coreWebApp.ModelBo.annex_info.bt_amt(gtt_bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt((parseFloat(coreWebApp.ModelBo.annex_info.bt_amt()) + parseFloat(coreWebApp.ModelBo.annex_info.tax_amt())).toFixed(2));
    }
    gst_si.total_calc = total_calc;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.si_tran().forEach(function (x) {
            item_calc(x);
        });
    }
    gst_si.redo_item_calc = redo_item_calc;

    function view_gl_init() {
        core_ac.gl_distribution('ac.vch_control', coreWebApp.ModelBo.voucher_id());
    }
    gst_si.view_gl_init = view_gl_init;

    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_ac.gst_si.view_gl_init');
    }
    gst_si.view_gl = view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.voucher_id() != '' && coreWebApp.ModelBo.voucher_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    gst_si.visible_gl_distribution = visible_gl_distribution;

    function si_tran_delete() {
        total_calc();
    }
    gst_si.si_tran_delete = si_tran_delete;

    function select_rc_item_click() {
        if (coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id() === -1) {
            coreWebApp.toastmsg('warning', 'Select Item Click Error', 'Select GST State to add Items.', false);
            return;
        } else if (coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id() === -1) {
            coreWebApp.toastmsg('warning', 'Select Item Click Error', 'Select Unser Sec. to add Items.', false);
            return;
        } else if (coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id() === "94") {
            var opts = {
                gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                si_tran: coreWebApp.ModelBo.si_tran, // The observable array is sent 
                after_update: select_rc_item_after_update
            };

            opts.module = 'core/ac';
            opts.alloc_view = 'gstSi/SelectRCItem';
            opts.call_init = select_rc_item_init;
            opts.call_update = select_rc_item_update;
            coreWebApp.showAllocV2(opts);
        } else if (coreWebApp.ModelBo.annex_info.gst_rc_info.rc_sec_id() === "93") {
            var opts = {
                gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                si_tran: coreWebApp.ModelBo.si_tran, // The observable array is sent 
                after_update: select_rc_item_after_update
            };

            opts.module = 'core/ac';
            opts.alloc_view = 'gstSi/SelectRCItem';
            opts.call_init = select_rc_93_item_init;
            opts.call_update = select_rc_item_update;
            coreWebApp.showAllocV2(opts);
        }
    }
    gst_si.select_rc_item_click = select_rc_item_click;

    function select_rc_item_after_update() {
        total_calc();
    }

    function select_rc_93_item_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ac/form/get-rc93',
            type: 'GET',
            data: {
                gst_state_id: opts.gst_state_id, doc_date: opts.doc_date, voucher_id: opts.voucher_id,
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var rc_alloc = new function () {
                        self = this;
                    };
                    rc_alloc.rc_temp = build_rc_item_temp();
                    for (var p = 0; p < jsonResult['b2c_94'].length; p++) {
                        var bal_row = jsonResult['b2c_94'][p];
                        var nr = rc_alloc.rc_temp.addNewRow();
                        nr.doc(bal_row['doc']);
                        nr.doc_date(bal_row['doc_date']);
                        nr.voucher_id(bal_row['voucher_id']);
                        nr.gst_tax_tran_id(bal_row['gst_tax_tran_id']);
                        nr.task_ref_no(bal_row['task_ref_no']);
                        nr.bt_amt(bal_row['bt_amt']);
                        nr.sgst_amt(bal_row['sgst_amt']);
                        nr.cgst_amt(bal_row['cgst_amt']);
                        nr.igst_amt(bal_row['igst_amt']);
                        nr.sgst_itc_amt(bal_row['sgst_itc_amt']);
                        nr.cgst_itc_amt(bal_row['cgst_itc_amt']);
                        nr.igst_itc_amt(bal_row['igst_itc_amt']);
                        nr.hsn_sc_id(bal_row['hsn_sc_id']);
                        nr.account_id(bal_row['account_id']);
                        nr.branch_id(bal_row['branch_id']);
                        nr.account_head(bal_row['account_head']);
                        nr.hsn_sc_desc(bal_row['hsn_sc_desc']);
                        nr.branch_name(bal_row['branch_name']);
                        nr.apply_itc(bal_row['apply_itc']);
                        for (var a = 0; a < opts.si_tran().length; ++a) {
                            var rlt = opts.si_tran()[a];
                            if (rlt.ref_tran_id() === bal_row['gst_tax_tran_id']) {
                                nr.is_select(true);
                            }
                        }
                        rc_alloc.rc_temp.push(nr);
                    }
                    opts.model = rc_alloc;
                    $('#sele-rc-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    gst_si.select_rc_93_item_init = select_rc_93_item_init;

    function select_rc_item_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ac/form/get-rc94',
            type: 'GET',
            data: {
                gst_state_id: opts.gst_state_id, doc_date: opts.doc_date, voucher_id: opts.voucher_id,
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var rc_alloc = new function () {
                        self = this;
                    };
                    rc_alloc.rc_temp = build_rc_item_temp();
                    for (var p = 0; p < jsonResult['b2c_94'].length; p++) {
                        var bal_row = jsonResult['b2c_94'][p];
                        var nr = rc_alloc.rc_temp.addNewRow();
                        nr.doc(bal_row['doc']);
                        nr.doc_date(bal_row['doc_date']);
                        nr.voucher_id(bal_row['voucher_id']);
                        nr.gst_tax_tran_id(bal_row['gst_tax_tran_id']);
                        nr.task_ref_no(bal_row['task_ref_no']);
                        nr.bt_amt(bal_row['bt_amt']);
                        nr.sgst_amt(bal_row['sgst_amt']);
                        nr.cgst_amt(bal_row['cgst_amt']);
                        nr.igst_amt(bal_row['igst_amt']);
                        nr.sgst_itc_amt(bal_row['sgst_itc_amt']);
                        nr.cgst_itc_amt(bal_row['cgst_itc_amt']);
                        nr.igst_itc_amt(bal_row['igst_itc_amt']);
                        nr.hsn_sc_id(bal_row['hsn_sc_id']);
                        nr.account_id(bal_row['account_id']);
                        nr.branch_id(bal_row['branch_id']);
                        nr.account_head(bal_row['account_head']);
                        nr.hsn_sc_desc(bal_row['hsn_sc_desc']);
                        nr.branch_name(bal_row['branch_name']);
                        nr.apply_itc(bal_row['apply_itc']);
                        for (var a = 0; a < opts.si_tran().length; ++a) {
                            var rlt = opts.si_tran()[a];
                            if (rlt.ref_tran_id() === bal_row['gst_tax_tran_id']) {
                                nr.is_select(true);
                            }
                        }
                        rc_alloc.rc_temp.push(nr);
                    }
                    opts.model = rc_alloc;
                    $('#sele-rc-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    gst_si.select_rc_item_init = select_rc_item_init;

    function build_rc_item_temp() {
        var rc_temp = ko.observableArray();
        rc_temp.addNewRow = function () {
            var cobj = new Object();

            cobj.doc = ko.observable('');
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.gst_tax_tran_id = ko.observable('');
            cobj.voucher_id = ko.observable('');
            cobj.task_ref_no = ko.observable('');
            cobj.bt_amt = ko.observable(0);
            cobj.sgst_amt = ko.observable(0);
            cobj.cgst_amt = ko.observable(0);
            cobj.igst_amt = ko.observable(0);
            cobj.sgst_itc_amt = ko.observable(0);
            cobj.cgst_itc_amt = ko.observable(0);
            cobj.igst_itc_amt = ko.observable(0);
            cobj.hsn_sc_id = ko.observable(-1);
            cobj.account_id = ko.observable(-1);
            cobj.branch_id = ko.observable(-1);
            cobj.account_head = ko.observable('');
            cobj.hsn_sc_desc = ko.observable('');
            cobj.branch_name = ko.observable('');
            cobj.is_select = ko.observable(false);
            cobj.apply_itc = ko.observable(false);
            return cobj;
        };
        return rc_temp;
    }
    gst_si.build_rc_item_temp = build_rc_item_temp;

    function select_rc_item_update(opts) {

        coreWebApp.startloading();
        // clear existing alloc
        for (var p = 0; p < opts.model.rc_temp().length; ++p) {
            var rlt = opts.model.rc_temp()[p];
            if (rlt.is_select() == true) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.si_tran().length; q++) {
                    if (rlt['gst_tax_tran_id']() == coreWebApp.ModelBo.si_tran()[q]['ref_tran_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                gst_si.sl_no += 1;

                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('si_tran', coreWebApp.ModelBo, true);
                    nr.voucher_id('');
                    nr.si_tran_id('');
                    nr.sl_no(gst_si.sl_no);
                    nr.ref_id(rlt['voucher_id']());
                    nr.ref_tran_id(rlt['gst_tax_tran_id']());
                    nr.ref_date(rlt['doc_date']());
                    nr.gtt_bt_amt(rlt['bt_amt']());
                    coreWebApp.lookupCache.add('branch_id', rlt['branch_id'](), rlt['branch_name']());
                    nr.branch_id(rlt['branch_id']());
                    nr.gtt_apply_itc(rlt['apply_itc']());
                    coreWebApp.lookupCache.add('account_id', rlt['account_id'](), rlt['account_head']());
                    nr.account_id(rlt['account_id']());
                    coreWebApp.lookupCache.add('hsn_sc_id', rlt['hsn_sc_id'](), rlt['hsn_sc_desc']());
                    nr.hsn_sc_id(rlt['hsn_sc_id']());
                    nr.gtt_tran_group('ac.si_tran');

                    fetch_hsn_info(nr);
                    coreWebApp.afterNewRowAdded(false);
                }
            }
        }
        opts.si_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created

        coreWebApp.stoploading();
        return true;
    }
    gst_si.select_rc_item_update = select_rc_item_update;

    function enable_si_tran(row) {
        if (row.ref_id() == '') {
            return true;
        } else {
            return false;
        }
    }
    gst_si.enable_si_tran = enable_si_tran;

    function enable_state(row) {
        var row_exists = false;
        for (var q = 0; q < coreWebApp.ModelBo.si_tran().length; q++) {
            if (coreWebApp.ModelBo.si_tran()[q]['ref_tran_id']() != '') {
                row_exists = true;
                break;
            }
        }

        if (row_exists) {
            return false;
        } else {
            return true;
        }
    }
    gst_si.enable_state = enable_state;

}(window.core_ac.gst_si));


