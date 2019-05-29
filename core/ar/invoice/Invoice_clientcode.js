// Declare core_ap Namespace
window.core_invoice = {};
(function (core_invoice) {

    function customer_changed(dataItem) {
        core_ar.cust_salesman(coreWebApp.ModelBo.customer_id());
        var opts = {                
            customer_id: coreWebApp.ModelBo.customer_id(),
            cust_billing_addr: coreWebApp.ModelBo.invoice_address,    // The observable array is sent 
            addr_type_id: (coreWebApp.ModelBo.company_id()*1000000) + 3 // Billing Address
        };
        core_ar.get_cust_billing_addr(opts);
    }

    core_invoice.customer_changed = customer_changed;
    
    function sel_addr_click() {
        if(parseInt(coreWebApp.ModelBo.customer_id()) === -1){
            coreWebApp.toastmsg('warning','Select Address Click Error','Select Customer to select address.',false);
            return;
        }
        else{
            var opts = {                
                customer_id: coreWebApp.ModelBo.customer_id(),
                cust_billing_addr: coreWebApp.ModelBo.invoice_address    // The observable array is sent 
            };
            core_ar.cust_addr_ui(opts);
        }
    }
    core_invoice.sel_addr_click = sel_addr_click;

    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }

    core_invoice.enable_visible_fc = enable_visible_fc

    function inv_fc_changed(dataItem) {
        ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (a) {
            item_calc(a);
        });
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (a) {
            if (coreWebApp.ModelBo.fc_type_id() == 0) {
                a.debit_amt_fc(0);
            } else {
                a.debit_amt((parseFloat(a.debit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
            }
        });
        total_calc();
    }
    core_invoice.inv_fc_changed = inv_fc_changed;


    function income_type_account_combo_filter(fltr) {
        var income_type_id = coreWebApp.ModelBo.income_type_id();
        fltr = " account_id in (Select account_id from ar.income_type_tran where income_type_id=" + income_type_id + ")";
        return fltr;
    }

    core_invoice.income_type_account_combo_filter = income_type_account_combo_filter;


    function item_calc(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {// Foreign Currency            
            dataItem.credit_amt((parseFloat(dataItem.credit_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            dataItem.credit_amt_fc(0);
        }
        total_calc();
    }
    core_invoice.item_calc = item_calc;


    function adv_alloc_click() {
        if (coreWebApp.ModelBo.customer_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Customer to view advance.', false);
            return;
        } else {
            var debit_total = new Number();
            var debit_total_fc = new Number();

            ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (item) {
                debit_total += new Number(item.credit_amt());
                debit_total_fc += new Number(item.credit_amt_fc());
            });

            var opts = {
                voucher_id: coreWebApp.ModelBo.invoice_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                account_id: coreWebApp.ModelBo.customer_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                dc : 'C',
                debit_amt_total: debit_total,
                debit_amt_total_fc: debit_total_fc,
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_invoice.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var tax_amt_tot = new Number(0.00);
        var tax_amt_tot_fc = new Number(0.00);
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);

        // Total each stock item
        ko.utils.arrayForEach(coreWebApp.ModelBo.invoice_tran(), function (row) {
            item_amt_tot += Number.parseFloat(row.credit_amt());
            item_amt_tot_fc += Number.parseFloat(row.credit_amt_fc());
        });
        // Total Tax
        ko.utils.arrayForEach(coreWebApp.ModelBo.tax_tran(), function (row) {
            tax_amt_tot += Number.parseFloat(row.tax_amt());
            tax_amt_tot_fc += Number.parseFloat(row.tax_amt_fc());
        });

        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.debit_amt());
            adv_settle_fc += Number.parseFloat(row.debit_amt_fc());
        });

        coreWebApp.ModelBo.tax_amt(tax_amt_tot.toFixed(2));
        coreWebApp.ModelBo.invoice_amt((item_amt_tot + tax_amt_tot).toFixed(2));
        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.net_total((item_amt_tot + tax_amt_tot - adv_settle).toFixed(2));

        coreWebApp.ModelBo.tax_amt_fc(tax_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.invoice_amt_fc((item_amt_tot_fc + tax_amt_tot_fc).toFixed(2));
        coreWebApp.ModelBo.advance_amt_fc(adv_settle_fc.toFixed(2));
        coreWebApp.ModelBo.net_total_fc((item_amt_tot_fc + tax_amt_tot_fc - adv_settle_fc).toFixed(2));

    }
    core_invoice.total_calc = total_calc;

    function inv_view_gl_init() {
        core_ac.gl_distribution('ar.invoice_control', coreWebApp.ModelBo.invoice_id());
    }
    core_invoice.inv_view_gl_init = inv_view_gl_init;


    function inv_view_gl() {
        coreWebApp.showAlloc('core/ac', '/glDistribution/GLDistribution', 'core_invoice.inv_view_gl_init');
    }

    core_invoice.inv_view_gl = inv_view_gl;


    function cancelAllocUpdate() {
    }
    core_invoice.cancelAllocUpdate = cancelAllocUpdate;

    function visible_gl_distribution(dataItem) {
        if (coreWebApp.ModelBo.invoice_id() != '' && coreWebApp.ModelBo.invoice_id() != '-1') {
            return true;
        } else {
            return false;
        }
    }
    ;

    core_invoice.visible_gl_distribution = visible_gl_distribution

}(window.core_invoice));