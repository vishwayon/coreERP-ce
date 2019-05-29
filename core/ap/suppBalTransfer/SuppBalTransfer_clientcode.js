// Declare core_ap Namespace
window.core_sbt = {};
(function (core_sbt) {
    
    function afterload() {
        $('#cmd_addnew_payable_ledger_alloc_tran').hide();
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleBill').hide();
        }
        total_calc();
    }
    core_sbt.afterload = afterload;
    
    function total_calc() {
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);

        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            net_credit_amt_tot += Number.parseFloat(row.net_credit_amt());
            net_credit_amt_tot_fc += Number.parseFloat(row.net_credit_amt_fc());
        });

        coreWebApp.ModelBo.credit_amt(net_credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_fc(net_credit_amt_tot_fc.toFixed(2));

        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            coreWebApp.ModelBo.net_settled_fc(0);
        } else {
            coreWebApp.ModelBo.net_settled((parseFloat(coreWebApp.ModelBo.net_settled_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }
    }
    core_sbt.total_calc = total_calc;


    function pl_tran_delete() {
        total_calc();
    }
    core_sbt.pl_tran_delete = pl_tran_delete;
    
    function fc_changed(dataItem) {
        console.log('fc_changed');
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        
        ko.utils.arrayForEach(dataItem.payable_ledger_alloc_tran(), function (a) {
            if (fc_type_id == 0) {
                a.credit_amt_fc(0);
            } else {
                a.credit_amt((parseFloat(a.credit_amt_fc()) * exch_rate).toFixed(2));
                a.net_credit_amt(parseFloat(a.credit_amt()).toFixed(2));
            }
        });
        
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            coreWebApp.ModelBo.net_settled_fc(0);
        } else {
            coreWebApp.ModelBo.net_settled_fc((parseFloat(dataItem.net_settled()) / coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }
        
        total_calc();
    }
    core_sbt.fc_changed = fc_changed;
    
    function adv_alloc_click() {
        if (coreWebApp.ModelBo.supplier_account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.supplier_account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.net_settled(),
                credit_amt_total_fc: coreWebApp.ModelBo.net_settled_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    core_sbt.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

    function target_branch_filter(fltr) {
        fltr = "branch_id != " + coreWebApp.ModelBo.branch_id();
        return fltr;
    }
    core_sbt.target_branch_filter = target_branch_filter;

}(window.core_sbt));