// Declare core_ap Namespace
typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
typeof window.core_ac.jv == 'undefined' ? window.core_ac.jv = {} : '';

(function (jv) {
    function sub_head_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } 
        else if (coreWebApp.ModelBo.debit_amt() == 0 && coreWebApp.ModelBo.credit_amt() == 0){
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Enter Debits or credits to add details.', false);
            return;
        }
        else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                sl_no: 0,
                ref_no: coreWebApp.ModelBo.ref_no(),
                ref_desc: coreWebApp.ModelBo.ref_desc(),
                row: coreWebApp.ModelBo,
                shl_tran_name: 'shl_head_tran',
                rla_tran_name: 'rla_head_tran',
                after_update: sub_head_alloc_after_update
            };
            if (coreWebApp.ModelBo.debit_amt() > 0){
                opts.dc = 'D';                
                opts.debit_amt_total = coreWebApp.ModelBo.debit_amt();
            }
            else{
                opts.dc = 'C';           
                opts.debit_amt_total = coreWebApp.ModelBo.credit_amt();
            }
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    jv.sub_head_alloc_click = sub_head_alloc_click;

    function sub_head_alloc_after_update() {
    }
}(window.core_ac.jv));