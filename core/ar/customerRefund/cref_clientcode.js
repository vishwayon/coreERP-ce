/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

window.core_cref = {};
(function (core_cref) {
    function fc_changed(dataItem) {
        console.log('fc_changed');
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());

        ko.utils.arrayForEach(dataItem.receivable_ledger_alloc_tran(), function (a) {
            if (fc_type_id == 0) {
                a.debit_amt_fc(0);
            } else {
                a.debit_amt((parseFloat(a.debit_amt_fc()) * exch_rate).toFixed(2));
                a.net_debit_amt(parseFloat(a.debit_amt()).toFixed(2));
            }
        });
        total_calc();
    }
    core_cref.fc_changed = fc_changed;

    function total_calc() {
        var debit_amt_tot = new Number(0.00);
        var debit_amt_tot_fc = new Number(0.00);

        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            debit_amt_tot += Number.parseFloat(row.debit_amt());
            debit_amt_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });

        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            coreWebApp.ModelBo.net_settled_fc(0);
        } else {
            coreWebApp.ModelBo.net_settled((parseFloat(coreWebApp.ModelBo.net_settled_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }
        
        coreWebApp.ModelBo.debit_amt(debit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.debit_amt_fc(debit_amt_tot_fc.toFixed(2));
    }
    core_cref.total_calc = total_calc;

    function test_afterload_wiz() {
        console.log('test afterload for wizard');
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleInv').hide();
            $('#seleBill').hide();
        }
        $('#bo-form').children().find("[id=cmd_addnew_receivable_ledger_alloc_tran]").each(function (e, i) {
            $(this).hide();
        });
        total_calc();
    }
    core_cref.test_afterload_wiz = test_afterload_wiz;
    
    function rl_tran_delete() {
        total_calc();
    }
    core_cref.rl_tran_delete = rl_tran_delete;
    
    function adv_alloc_click() {
        if (coreWebApp.ModelBo.customer_account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Customer to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                account_id: coreWebApp.ModelBo.customer_account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                dc : 'C',
                debit_amt_total: coreWebApp.ModelBo.net_settled(),
                debit_amt_total_fc: coreWebApp.ModelBo.net_settled_fc(),
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            if(coreWebApp.ModelBo.is_inter_branch() == true){                    
                opts.branch_id = 0;
            }
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_cref.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }
}(window.core_cref));
