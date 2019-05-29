// Declare core_st Namespace
//typeof window.core_ar == 'undefined' ? window.core_ar = {} : '';
window.dn = {};
(function (dn) {
    // dcn Types
    dn.PURCHASE_RETURN = 0;
    dn.RATE_ADJUST = 1;
    dn.POST_BILL_DISC = 2;

    dn.sl_no = 0;
    stop_calc = false;
    function dn_afterload() {
        $('#cmd_addnew_pymt_tran').hide();
        dn.sl_no = coreWebApp.ModelBo.pymt_tran().length;
        coreWebApp.ModelBo.pymt_tran().forEach(function (row) {
            fetch_hsn_info(row);
        });
        total_calc();
    }
    dn.dn_afterload = dn_afterload;

    function amt_desc() {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == dn.PURCHASE_RETURN
                || coreWebApp.ModelBo.annex_info.dcn_type() == dn.POST_BILL_DISC) {
            return "Debit Amt";
        } else {
            return "Credit Amt";
        }
    }
    dn.amt_desc = amt_desc;

    function item_amt_enable(dataItem) {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == dn.RATE_ADJUST
                || coreWebApp.ModelBo.annex_info.dcn_type() == dn.POST_BILL_DISC) {
            return true;
        } else {
            return false;
        }
    }
    dn.item_amt_enable = item_amt_enable;

    function item_calc(row) {
        console.log('item_calc');
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var tax_amt = new Number(0.00);
        var bt_amt = parseFloat(row.gtt_bt_amt());
        row.gtt_bt_amt(bt_amt.toFixed(2));
        core_tx.gst.item_gtt_calc({
            bt_amt: bt_amt,
            row: row
        });
        var tax_amt = parseFloat(row.gtt_sgst_amt()) + parseFloat(row.gtt_cgst_amt())
                + parseFloat(row.gtt_igst_amt()) + parseFloat(row.gtt_cess_amt());
        row.tax_amt(tax_amt.toFixed(2));
        tax_amt = parseFloat(row.tax_amt()); // always pickup tax_amt to avoid float errors
        row.credit_amt((bt_amt).toFixed(2));
        total_calc();
        stop_calc = false;
    }
    dn.item_calc = item_calc;

    function total_calc() {
        console.log('total_calc');
        dn.sl_no = 0;
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var is_rc = false;
        // Total each invoice item
        ko.utils.arrayForEach(coreWebApp.ModelBo.pymt_tran(), function (row) {
            dn.sl_no += 1;
            bt_amt_tot += Number.parseFloat(row.gtt_bt_amt());
            tax_amt_tot += Number.parseFloat(row.gtt_sgst_amt()) + Number.parseFloat(row.gtt_cgst_amt()) + Number.parseFloat(row.gtt_igst_amt());
            row.sl_no(dn.sl_no);
            is_rc = row.gtt_is_rc();
        });
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        if (!is_rc) {
            coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.annex_info.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
            var rof_amt = Number.parseFloat(coreWebApp.ModelBo.annex_info.round_off_amt());
            coreWebApp.ModelBo.credit_amt((bt_amt_tot + tax_amt_tot + rof_amt).toFixed(2));
        } else {
            coreWebApp.ModelBo.annex_info.rc_tax_amt(tax_amt_tot.toFixed(2));
            coreWebApp.ModelBo.annex_info.gross_amt((bt_amt_tot).toFixed(2));
            var rof_amt = Number.parseFloat(coreWebApp.ModelBo.annex_info.round_off_amt());
            coreWebApp.ModelBo.credit_amt((bt_amt_tot + rof_amt).toFixed(2));
        }
    }
    dn.total_calc = total_calc;

    function pymt_tran_delete() {
        total_calc();
    }
    dn.pymt_tran_delete = pymt_tran_delete;

    function fetch_hsn_info(row) {
        var hsn_sc_id = row.hsn_sc_id();
        $.ajax({
            url: '?r=core/tx/form/get-hsn-gst-info',
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
                        is_ctp: coreWebApp.ModelBo.annex_info.gst_input_info.is_ctp(),
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
    dn.fetch_hsn_info = fetch_hsn_info;

    function select_bill() {
        var opts = {
            origin_bill_id: coreWebApp.ModelBo.annex_info.origin_bill_id(),
            account_id: coreWebApp.ModelBo.supplier_account_id(),
            pymt_tran: coreWebApp.ModelBo.pymt_tran, // The observable array is sent   
            after_update: select_bill_after_update
        };

        opts.module = 'core/ap';
        opts.alloc_view = 'gstDebitNote/SelectBillItem';
        opts.call_init = select_bill_init;
        opts.call_update = select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    dn.select_bill = select_bill;

    function select_bill_after_update() {
        total_calc();
    }

    function select_bill_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ap/form/get-bill-for-dn',
            type: 'GET',
            data: {
                origin_bill_id: opts.origin_bill_id, supplier_id: opts.account_id,
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var bill_alloc = new function () {
                        self = this;
                    };
                    bill_alloc.bill_temp = build_bill_temp();
                    for (var p = 0; p < jsonResult['bill_tran'].length; p++) {
                        var bal_row = jsonResult['bill_tran'][p];
                        var nr = bill_alloc.bill_temp.addNewRow();
                        nr.bill_id(bal_row['bill_id']);
                        nr.bill_tran_id(bal_row['bill_tran_id']);
                        nr.doc_date(bal_row['doc_date']);
                        nr.description(bal_row['description']);
                        nr.account_head(bal_row['account_head']);
                        nr.account_id(bal_row['account_id']);
                        nr.bill_amt(bal_row['bill_amt']);
                        nr.tax_amt(bal_row['tax_amt']);
                        nr.hsn_sc_id(bal_row['hsn_sc_id']);
                        nr.hsn_sc_desc(bal_row['hsn_sc_desc']);
                        nr.gst_hsn_info = bal_row['gst_hsn_info'];
                        for (var a = 0; a < opts.pymt_tran().length; ++a) {
                            var rlt = opts.pymt_tran()[a];
                            if (rlt.reference_tran_id() === bal_row['bill_tran_id']) {
                                nr.is_select(true);
                            }
                        }
                        bill_alloc.bill_temp.push(nr);
                    }
                    opts.model = bill_alloc;
                    $('#sele-bill-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    dn.select_bill_init = select_bill_init;

    function build_bill_temp() {
        var bill_temp = ko.observableArray();
        bill_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.account_id = ko.observable(-1);
            cobj.bill_amt = ko.observable(0);
            cobj.tax_amt = ko.observable(0);
            cobj.bill_id = ko.observable('');
            cobj.bill_tran_id = ko.observable('');
            cobj.description = ko.observable('');
            cobj.account_head = ko.observable('');
            cobj.is_select = ko.observable(false);
            cobj.hsn_sc_id = ko.observable(-1);
            cobj.hsn_sc_desc = ko.observable('');
            cobj.gst_hsn_info = '';
            return cobj;
        };
        return bill_temp;
    }
    dn.build_bill_temp = build_bill_temp;

    function select_bill_update(opts) {
        // clear existing alloc
        for (var p = 0; p < opts.model.bill_temp().length; ++p) {
            var rlt = opts.model.bill_temp()[p];
            if (rlt.is_select() == true) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.pymt_tran().length; q++) {
                    if (rlt['bill_tran_id']() == coreWebApp.ModelBo.pymt_tran()[q]['reference_tran_id']()) {
                        row_exists = true;
                        break;
                    }
                }

                dn.sl_no += 1;

                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('pymt_tran', coreWebApp.ModelBo, true);
                    nr.voucher_id('');
                    nr.vch_tran_id('');
                    nr.sl_no(dn.sl_no);
                    nr.reference_id(rlt['bill_id']());
                    nr.reference_tran_id(rlt['bill_tran_id']());
                    coreWebApp.lookupCache.add('account_id', rlt['account_id'](), rlt['account_head']());
                    nr.account_id(rlt['account_id']());
                    nr.dc('C');
                    nr.description(rlt['description']());
                    nr.bill_amt(rlt['bill_amt']());
                    nr.credit_amt(0);
                    nr.credit_amt_fc(0);
                    nr.credit_amt(0);
                    nr.debit_amt_fc(0);
                    coreWebApp.lookupCache.add('hsn_sc_id', rlt['hsn_sc_id'](), rlt['hsn_sc_desc']());
                    nr.hsn_sc_id(rlt['hsn_sc_id']());
                    nr.gtt_apply_itc(JSON.parse(rlt.gst_hsn_info).apply_itc);
                    nr.gtt_is_rc(JSON.parse(rlt.gst_hsn_info).is_rc);
                    nr.gtt_rc_sec_id(JSON.parse(rlt.gst_hsn_info).rc_sec_id);
                    if (coreWebApp.ModelBo.annex_info.dcn_type() == dn.PURCHASE_RETURN) {
                        nr.gtt_bt_amt(rlt['bill_amt']());
                    }
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_PURCH,
                        origin_gst_state_id: coreWebApp.ModelBo.annex_info.gst_input_info.supplier_state_id(),
                        target_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        is_ctp: false,
                        gst_hsn_info: JSON.parse(rlt.gst_hsn_info),
                        row: nr
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);
                    item_calc(nr);

                    coreWebApp.afterNewRowAdded(false);
                    //total_calc();
                }
            }
        }
        opts.pymt_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    dn.select_bill_update = select_bill_update;

    function tax_amt_ov(row) {
        return row.gtt_tax_amt_ov();
    }
    dn.tax_amt_ov = tax_amt_ov;


    function view_gl_init() {
        core_ac.gl_distribution('ap.pymt_control', coreWebApp.ModelBo.voucher_id());
    }
    dn.view_gl_init = view_gl_init;


    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'dn.view_gl_init');
    }

    dn.view_gl = view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.voucher_id() != '' && coreWebApp.ModelBo.voucher_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    dn.visible_gl_distribution = visible_gl_distribution

}(window.dn));
