// Declare core_st Namespace
//typeof window.core_ar == 'undefined' ? window.core_ar = {} : '';
window.cn = {};
(function (cn) {
    // dcn Types
    cn.SALE_RETURN = 0;
    cn.RATE_ADJUST = 1;
    cn.POST_INV_DISC = 2;

    cn.sl_no = 0;
    stop_calc = false;
    function cn_afterload() {
        $('#cmd_addnew_rcpt_tran').hide();
        cn.sl_no = coreWebApp.ModelBo.rcpt_tran().length;
    }
    cn.cn_afterload = cn_afterload;

    function amt_desc() {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == cn.SALE_RETURN
                || coreWebApp.ModelBo.annex_info.dcn_type() == cn.POST_INV_DISC) {
            return "Credit Amt";
        } else {
            return "Debit Amt";
        }
    }
    cn.amt_desc = amt_desc;

    function item_amt_enable(dataItem) {
        if (coreWebApp.ModelBo.annex_info.dcn_type() == cn.RATE_ADJUST
                || coreWebApp.ModelBo.annex_info.dcn_type() == cn.POST_INV_DISC) {
            return true;
        } else {
            return false;
        }
    }
    cn.item_amt_enable = item_amt_enable;

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
        row.debit_amt((bt_amt).toFixed(2));
        total_calc();
        stop_calc = false;
    }
    cn.item_calc = item_calc;

    function total_calc() {
        console.log('total_calc');
        cn.sl_no = 0;
        var bt_amt_tot = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        // Total each invoice item
        ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_tran(), function (row) {
            cn.sl_no += 1;
            bt_amt_tot += Number.parseFloat(row.gtt_bt_amt());
            tax_amt_tot += Number.parseFloat(row.gtt_sgst_amt()) + Number.parseFloat(row.gtt_cgst_amt()) + Number.parseFloat(row.gtt_igst_amt());
            row.sl_no(cn.sl_no);
        });
        coreWebApp.ModelBo.annex_info.items_total_amt(bt_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.gross_amt((bt_amt_tot + tax_amt_tot).toFixed(2));
        var rof_amt = Number.parseFloat(coreWebApp.ModelBo.annex_info.round_off_amt());
//        var rof_amt = Number.parseFloat((bt_amt_tot + tax_amt_tot).toFixed(0)) - (bt_amt_tot + tax_amt_tot);
//        coreWebApp.ModelBo.annex_info.round_off_amt(rof_amt.toFixed(2));
        coreWebApp.ModelBo.debit_amt((bt_amt_tot + tax_amt_tot + rof_amt).toFixed(2));
    }
    cn.total_calc = total_calc;

    function rcpt_tran_delete() {
        total_calc();
    }
    cn.rcpt_tran_delete = rcpt_tran_delete;

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
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        gst_hsn_info: gst_hsn_info,
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
    cn.fetch_hsn_info = fetch_hsn_info;

    function select_inv() {
        var opts = {
            origin_inv_id: coreWebApp.ModelBo.annex_info.origin_inv_id(),
            account_id: coreWebApp.ModelBo.customer_account_id(),
            rcpt_tran: coreWebApp.ModelBo.rcpt_tran, // The observable array is sent   
            after_update: select_inv_after_update
        };

        opts.module = 'core/ar';
        opts.alloc_view = 'gstCreditNote/SelectInvItem';
        opts.call_init = select_inv_init;
        opts.call_update = select_inv_update;
        coreWebApp.showAllocV2(opts);
    }
    cn.select_inv = select_inv;

    function select_inv_after_update() {
        total_calc();
    }

    function select_inv_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ar/form/get-inv-for-cn',
            type: 'GET',
            data: {
                origin_inv_id: opts.origin_inv_id, customer_id: opts.account_id,
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var inv_alloc = new function () {
                        self = this;
                    };
                    inv_alloc.inv_temp = build_inv_temp();
                    for (var p = 0; p < jsonResult['inv_tran'].length; p++) {
                        var bal_row = jsonResult['inv_tran'][p];
                        var nr = inv_alloc.inv_temp.addNewRow();
                        nr.invoice_id(bal_row['invoice_id']);
                        nr.invoice_tran_id(bal_row['invoice_tran_id']);
                        nr.doc_date(bal_row['doc_date']);
                        nr.description(bal_row['description']);
                        nr.account_head(bal_row['account_head']);
                        nr.account_id(bal_row['account_id']);
                        nr.invoice_amt(bal_row['invoice_amt']);
                        nr.tax_amt(bal_row['tax_amt']);
                        nr.hsn_sc_id(bal_row['hsn_sc_id']);
                        nr.hsn_sc_desc(bal_row['hsn_sc_desc']);
                        nr.gst_hsn_info = bal_row['gst_hsn_info'];
                        for (var a = 0; a < opts.rcpt_tran().length; ++a) {
                            var rlt = opts.rcpt_tran()[a];
                            if (rlt.reference_tran_id() === bal_row['invoice_tran_id']) {
                                nr.is_select(true);
                            }
                        }
                        inv_alloc.inv_temp.push(nr);
                    }
                    opts.model = inv_alloc;
                    $('#sele-inv-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    cn.select_inv_init = select_inv_init;

    function build_inv_temp() {
        var inv_temp = ko.observableArray();
        inv_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.account_id = ko.observable(-1);
            cobj.invoice_amt = ko.observable(0);
            cobj.tax_amt = ko.observable(0);
            cobj.invoice_id = ko.observable('');
            cobj.invoice_tran_id = ko.observable('');
            cobj.description = ko.observable('');
            cobj.account_head = ko.observable('');
            cobj.is_select = ko.observable(false);
            cobj.hsn_sc_id = ko.observable(-1);
            cobj.hsn_sc_desc = ko.observable('');
            cobj.gst_hsn_info = '';
            return cobj;
        };
        return inv_temp;
    }
    cn.build_inv_temp = build_inv_temp;

    function select_inv_update(opts) {
        // clear existing alloc
        for (var p = 0; p < opts.model.inv_temp().length; ++p) {
            var rlt = opts.model.inv_temp()[p];
            if (rlt.is_select() == true) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.rcpt_tran().length; q++) {
                    if (rlt['invoice_tran_id']() == coreWebApp.ModelBo.rcpt_tran()[q]['reference_tran_id']()) {
                        row_exists = true;
                        break;
                    }
                }

                cn.sl_no += 1;

                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('rcpt_tran', coreWebApp.ModelBo, true);
                    nr.voucher_id('');
                    nr.vch_tran_id('');
                    nr.sl_no(cn.sl_no);
                    nr.reference_id(rlt['invoice_id']());
                    nr.reference_tran_id(rlt['invoice_tran_id']());
                    nr.account_id(rlt['account_id']());
                    coreWebApp.trigger_change('account_id', rlt['account_id'](), rlt['account_head']());
                    nr.dc('D');
                    nr.description(rlt['description']());
                    nr.invoice_amt(rlt['invoice_amt']());
                    nr.credit_amt(0);
                    nr.credit_amt_fc(0);
                    nr.debit_amt(0);
                    nr.debit_amt_fc(0);
                    nr.hsn_sc_id(rlt['hsn_sc_id']());
                    coreWebApp.trigger_change('hsn_sc_id', rlt['hsn_sc_id'](), rlt['hsn_sc_desc']());
                    
                    if (coreWebApp.ModelBo.annex_info.dcn_type() == cn.SALE_RETURN) {
                        nr.gtt_bt_amt(rlt['invoice_amt']());
                    }                    
                    gstOpts = {
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        is_ctp: false,
                        gst_hsn_info: JSON.parse(rlt.gst_hsn_info),
                        row: nr
                    };
                    core_tx.gst.item_gtt_reset(gstOpts);
                    
                    coreWebApp.afterNewRowAdded(false);
                    stop_calc = false;
                    cn.item_calc(nr);
                }
            }
        }
        opts.rcpt_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    cn.select_inv_update = select_inv_update;


    function view_gl_init() {
        core_ac.gl_distribution('ar.rcpt_control', coreWebApp.ModelBo.voucher_id());
    }
    cn.view_gl_init = view_gl_init;


    function view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'cn.view_gl_init');
    }

    cn.view_gl = view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.voucher_id() != '' && coreWebApp.ModelBo.voucher_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    cn.visible_gl_distribution = visible_gl_distribution

}(window.cn));
