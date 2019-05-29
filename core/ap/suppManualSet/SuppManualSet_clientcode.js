// Declare core_ap Namespace
window.core_sms = {};
(function (core_sms) {
    function afterload_wiz() {
        console.log('test afterload for wizard');
        $('#cmd_addnew_payable_ledger_alloc_tran').hide();
        total_calc();
    }
    core_sms.afterload_wiz = afterload_wiz;


    function adv_alloc_click() {
        if (coreWebApp.ModelBo.supplier_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: '',
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.supplier_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.balance(),
                credit_amt_total_fc: coreWebApp.ModelBo.balance_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    core_sms.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }
    
    function total_calc() {
        var adv_settle = new Number(0.00);
        var adv_settle_fc = new Number(0.00);

        // Total advances settled
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            adv_settle += Number.parseFloat(row.credit_amt());
            adv_settle_fc += Number.parseFloat(row.credit_amt_fc());
        });

        coreWebApp.ModelBo.advance_amt(adv_settle.toFixed(2));
        coreWebApp.ModelBo.advance_amt_fc(adv_settle_fc.toFixed(2));
    }
    core_sms.total_calc = total_calc;
    
    function pl_tran_delete() {
        total_calc();
    }
    core_sms.pl_tran_delete = pl_tran_delete;
    
}(window.core_sms));