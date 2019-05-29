/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

window.core_cms = {};
(function (core_cms) {   

    function afterload_wiz() {
        $('#bo-form').children().find("[id=cmd_addnew_receivable_ledger_alloc_tran]").each(function (e, i) {
            $(this).hide();
        });
        total_calc();
    }
    core_cms.afterload_wiz = afterload_wiz;

    function adv_alloc_click() {
        if (coreWebApp.ModelBo.customer_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Customer to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: '',
                doc_date: coreWebApp.ModelBo.doc_date(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                account_id: coreWebApp.ModelBo.customer_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                dc : 'C',
                debit_amt_total: coreWebApp.ModelBo.balance(),
                debit_amt_total_fc: coreWebApp.ModelBo.balance_fc(),
                rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran, // The observable array is sent   
                after_update: adv_alloc_after_update
            };
            core_ar.adv_alloc_ui(opts);
        }
    }
    core_cms.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }
    
    function total_calc() {
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);

        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.debit_amt());
            adv_settle_fc += Number.parseFloat(row.debit_amt_fc());
        });

        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.advance_amt_fc(adv_settle_fc.toFixed(2));
    }
    core_cms.total_calc = total_calc;
    
    function rl_tran_delete() {
        total_calc();
    }
    core_cms.rl_tran_delete = rl_tran_delete;
}(window.core_cms));
