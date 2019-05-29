// Declare core_ap Namespace
window.core_bill = {};
(function (core_bill) {

    function item_calc(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {// Foreign Currency            
            dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            dataItem.debit_amt_fc(0);
        }
        total_calc();
    }
    core_bill.item_calc = item_calc;

    function lc_tran_delete() {
        total_calc();
    }
    core_bill.lc_tran_delete = lc_tran_delete;

    function bill_tran_delete() {
        total_calc();
    }
    core_bill.bill_tran_delete = bill_tran_delete;

    function total_calc(dataItem) {
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var tax_amt_tot_fc = new Number(0.00);
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);
        var misc_amt_tot = new Number(0.00);
        var misc_amt_tot_fc = new Number(0.00);

        // Total each bill item
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_tran(), function (row) {
            item_amt_tot += Number.parseFloat(row.debit_amt());
            item_amt_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });
        // Total Tax
        ko.utils.arrayForEach(coreWebApp.ModelBo.tax_tran(), function (row) {
            tax_amt_tot += Number.parseFloat(row.tax_amt());
            tax_amt_tot_fc += Number.parseFloat(row.tax_amt_fc());
        });

        // Total each lc item
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_lc_tran(), function (row) {
            misc_amt_tot += Number.parseFloat(row.debit_amt());
            misc_amt_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });

        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.credit_amt());
            adv_settle_fc += Number.parseFloat(row.credit_amt_fc());
        });

        coreWebApp.ModelBo.before_tax_amt(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.misc_amt(misc_amt_tot.toFixed(2));
        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.net_bill_amt((item_amt_tot + tax_amt_tot + misc_amt_tot + Number.parseFloat(coreWebApp.ModelBo.round_off_amt()) - Number.parseFloat(coreWebApp.ModelBo.bill_amt())).toFixed(2));

        coreWebApp.ModelBo.before_tax_amt_fc(item_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.misc_amt_fc(misc_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.tax_amt_fc(tax_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.advance_amt_fc(adv_settle_fc.toFixed(2));
        coreWebApp.ModelBo.net_bill_amt_fc((item_amt_tot_fc + tax_amt_tot_fc + misc_amt_tot_fc + Number.parseFloat(coreWebApp.ModelBo.round_off_amt_fc()) - Number.parseFloat(coreWebApp.ModelBo.bill_amt_fc())).toFixed(2));
    }
    core_bill.total_calc = total_calc;

    function visible_tds(dataItem) {
        var cnt = coreWebApp.ModelBo.bill_tds_tran().length;
        if (cnt != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_bill.visible_tds = visible_tds;

    function exch_rate_changed(dataItem) {
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_tran(), function (a) {
            item_calc(a);
        });
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (a) {
            if (coreWebApp.ModelBo.fc_type_id() == 0) {
                a.credit_amt_fc(0);
            } else {
                a.credit_amt((parseFloat(a.credit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
            }
        });
        bill_fc_changed(dataItem);
        total_calc();
    }
    core_bill.exch_rate_changed = exch_rate_changed;

    function bill_fc_changed(dataItem) {
        dataItem.bill_amt(parseFloat(dataItem.bill_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate()));
        total_calc();
    }
    core_bill.bill_fc_changed = bill_fc_changed;

    function bill_fc_tran_changed(dataItem) {
        dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
    }
    core_bill.bill_fc_tran_changed = bill_fc_tran_changed;

    function calculate_net_bill_amt(dataItem) {

        var tax_amt_total = 0;
        var debit_total = 0;

        var tax_amt_fc_total = 0;
        var debit_fc_total = 0;

        ko.utils.arrayForEach(dataItem.bill_tran(), function (item) {
            debit_total += parseFloat(item.debit_amt());
            debit_fc_total += parseFloat(item.debit_amt_fc());
        });

        ko.utils.arrayForEach(dataItem.tax_tran(), function (item) {
            tax_amt_total += parseFloat(item.tax_amt());
            tax_amt_fc_total += parseFloat(item.tax_amt_fc());
        });

        dataItem.net_bill_amt((parseFloat(dataItem.bill_amt()) - debit_total - tax_amt_total + parseFloat(dataItem.round_off_amt())).toFixed(2));

        if (dataItem.fc_type_id() != 0) {
            dataItem.net_bill_amt_fc((parseFloat(dataItem.bill_amt_fc()) - debit_fc_total - tax_amt_fc_total + parseFloat(dataItem.round_off_amt_fc())).toFixed(2));
        }
    }
    core_bill.calculate_net_bill_amt = calculate_net_bill_amt;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_bill.enable_visible_fc = enable_visible_fc

    function adv_alloc_click() {
        if (coreWebApp.ModelBo.supplier_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.bill_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.supplier_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.bill_amt(),
                credit_amt_total_fc: coreWebApp.ModelBo.bill_amt_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    core_bill.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function bill_view_gl_init() {
        core_ac.gl_distribution('ap.bill_control', coreWebApp.ModelBo.bill_id());
    }
    core_bill.bill_view_gl_init = bill_view_gl_init;


    function bill_view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_bill.bill_view_gl_init');
    }
    core_bill.bill_view_gl = bill_view_gl;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.bill_id() != '' && coreWebApp.ModelBo.bill_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    core_bill.visible_gl_distribution = visible_gl_distribution

}(window.core_bill));