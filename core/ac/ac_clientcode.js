// Declare core_ac Namespace. This is module level script//
typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
(function (core_ac) {
    function enable_reversal_date(dataItem) {
        if (typeof dataItem.is_reversal == 'undefined')
            return;
        if (dataItem.is_reversal() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.enable_reversal_date = enable_reversal_date

    function visible_sub_head_tran() {
        return false;
    }
    core_ac.visible_sub_head_tran = visible_sub_head_tran;

    function vch_afterload() {
        console.log('after loading doc status ' + coreWebApp.ModelBo.status());
    }
    core_ac.vch_afterload = vch_afterload;

    function vch_afterpost() {
        console.log('after posting doc status ' + coreWebApp.ModelBo.status());
    }
    core_ac.vch_afterpost = vch_afterpost;

    function vch_afterunpost() {
        console.log('after unposting doc status ' + coreWebApp.ModelBo.status());
    }
    core_ac.vch_afterunpost = vch_afterunpost;

    function vch_isDebit(tran) {
        tran = vch_isDC(tran, 'D');
        if (tran.dc() === 'D') {
            tran.credit_amt(0);
            return true;
        }
        return false;
    }
    core_ac.vch_isDebit = vch_isDebit;

    function vch_isCredit(tran) {
        tran = vch_isDC(tran, 'C');
        if (tran.dc() === 'C') {
            tran.debit_amt(0);
            return true;
        }
        return false;
    }
    core_ac.vch_isCredit = vch_isCredit;

    function vch_isDC(tran, def) {
        tran.dc(tran.dc().toUpperCase());
        if (tran.dc() !== 'D' && tran.dc() !== 'C') {
            tran.dc(def);
        }
        return tran;
    }
    core_ac.vch_isDC = vch_isDC;

    function vch_tran_add_new_row_credit(newRow) {
        if (coreWebApp.ModelBo.net_effect() >= 0) {
            newRow.dc('D');
            newRow.debit_amt(coreWebApp.ModelBo.net_effect());
        } else {
            newRow.dc('C');
            newRow.credit_amt(-1 * parseFloat(coreWebApp.ModelBo.net_effect()));
        }
        newRow.sl_no(coreWebApp.ModelBo.vch_tran().length);
    }
    core_ac.vch_tran_add_new_row_credit = vch_tran_add_new_row_credit;

    function vch_tran_add_new_row_debit(newRow) {
        if (coreWebApp.ModelBo.net_effect() >= 0) {
            newRow.dc('C');
            newRow.credit_amt(parseFloat(coreWebApp.ModelBo.net_effect()));
        } else {
            newRow.dc('D');
            newRow.debit_amt(-1 * coreWebApp.ModelBo.net_effect());
        }
        newRow.sl_no(coreWebApp.ModelBo.vch_tran().length);
    }
    core_ac.vch_tran_add_new_row_debit = vch_tran_add_new_row_debit;

    function acc_head_hidden_tran_select_all() {
        for (var p = 0; p < coreWebApp.ModelBo.acc_head_hidden_temp().length; ++p)
        {
            coreWebApp.ModelBo.acc_head_hidden_temp()[p].is_hidden(true);
        }
    }
    core_ac.acc_head_hidden_tran_select_all = acc_head_hidden_tran_select_all;

    function acc_head_hidden_tran_deselect_all() {
        for (var p = 0; p < coreWebApp.ModelBo.acc_head_hidden_temp().length; ++p)
        {
            coreWebApp.ModelBo.acc_head_hidden_temp()[p].is_hidden(false);
        }
    }
    core_ac.acc_head_hidden_tran_deselect_all = acc_head_hidden_tran_deselect_all;

    var doc_date, account_id, cheque_date;
    function before_new_vch() {
        doc_date = coreWebApp.ModelBo.doc_date();
        cheque_date = coreWebApp.ModelBo.cheque_date();
        account_id = coreWebApp.ModelBo.account_id();
    }
    core_ac.before_new_vch = before_new_vch;

    function after_new_vch() {
        coreWebApp.ModelBo.doc_date(doc_date);
        coreWebApp.ModelBo.cheque_date(cheque_date);
        doc_date = coreWebApp.ModelBo.doc_date();
        coreWebApp.ModelBo.account_id(account_id);
    }
    core_ac.after_new_vch = after_new_vch;

    function branch_combo_filter(fltr) {
        fltr = ' branch_id != ' + coreWebApp.ModelBo.branch_id();
        return fltr;
    }
    core_ac.branch_combo_filter = branch_combo_filter;

    function enable_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.is_inter_branch() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.enable_branch = enable_branch;

    function enable_inter_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.voucher_id() == "") {
            return true;
        } else {
            return false;
        }
    }
    core_ac.enable_inter_branch = enable_inter_branch;

    function inter_branch_toggle() {
//        $('.td-branch_id').each(function() {
//            if($(this).is(':hidden')==true){
//                $('.td-branch_id').children('.smartcombo').each(function() {
//                    var attr = $(this).attr('notyetsmart');
//                    if (typeof attr == 'undefined' || attr == false) {
//                        $(this).attr('notyetsmart',true);
//                        $(this).attr('smartapplied',false);
//                        $(this).select2('destroy'); 
//                    }                    
//                });
//            } else if($(this).is(':hidden')==false){
//                applySmartCombo2('.td-branch_id');
//            }
//        });
    }
    core_ac.inter_branch_toggle = inter_branch_toggle;

    function bpv_combo_filter(fltr) {
        return fltr;
    }
    core_ac.bpv_combo_filter = bpv_combo_filter;

    function dbd_click_demo(dbdinfo) {
        alert(dbdinfo.plot_id);
    }
    core_ac.dbd_click_demo = dbd_click_demo;

    function sub_head_closed(dataItem) {
        if (dataItem.is_closed()) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.sub_head_closed = sub_head_closed;

    function enable_sub_head_dim(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.sub_head_id() == -1) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.enable_sub_head_dim = enable_sub_head_dim;

    function enable_account_type(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.account_id() == -1) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.enable_account_type = enable_account_type;

    function bpv_before_tran_delete(pr, prop, rw) {
        console.log('deleting.....' + prop);
        return true;
    }
    core_ac.bpv_before_tran_delete = bpv_before_tran_delete;

    function gl_distribution(table_name, voucher_id) {
        $.ajax({
            url: '?r=core%2Fac%2Fform%2Fgldistribution',
            type: 'GET',
            data: {'table_name': table_name, 'voucher_id': voucher_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.gl_temp.removeAll();
                    for (var p = 0; p < jsonResult['gl_distribution'].length; p++)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('gl_temp', coreWebApp.ModelBo);
                        r1.index(jsonResult['gl_distribution'][p]['index']);
                        r1.branch_id(jsonResult['gl_distribution'][p]['branch_id']);
                        r1.branch_code(jsonResult['gl_distribution'][p]['branch_code']);
                        r1.dc(jsonResult['gl_distribution'][p]['dc']);
                        r1.account_id(jsonResult['gl_distribution'][p]['account_id']);
                        r1.account_head(jsonResult['gl_distribution'][p]['account_head']);
                        r1.debit_amt_fc(jsonResult['gl_distribution'][p]['debit_amt_fc']);
                        r1.debit_amt(jsonResult['gl_distribution'][p]['debit_amt']);
                        r1.credit_amt_fc(jsonResult['gl_distribution'][p]['credit_amt_fc']);
                        r1.credit_amt(jsonResult['gl_distribution'][p]['credit_amt']);
                    }
                    coreWebApp.ModelBo.gl_temp.valueHasMutated();
                    $('#gl_distribution-loading').hide();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', true);
            }
        });
    }
    core_ac.gl_distribution = gl_distribution;

    function rpt_gl_account_combo_filter(fltr) {
        if ($('#pcategory').val() == 'Bank') {
            fltr = ' (account_type_id = 1 or account_id = -99)';
        }
        if ($('#pcategory').val() == 'Cash') {
            fltr = ' (account_type_id in (2,32) or account_id = -99)';
        }
        if ($('#pcategory').val() == 'GL') {
            fltr = ' (account_type_id NOT IN (0,1,2,7,12,32, 46, 47) or account_id = -99) ';
        }
        return fltr;
    }
    core_ac.rpt_gl_account_combo_filter = rpt_gl_account_combo_filter;

    function rpt_gl_category_changed(dataItem) {
        console.log('rpt_gl_category_changed');
    }
    core_ac.rpt_gl_category_changed = rpt_gl_category_changed;

    function sub_head_combo_filter(fltr, datacontext) {
        fltr = ' sub_head_dim_id in (select sub_head_dim_id from ac.account_head where account_id = ' + coreWebApp.ModelBo.sub_head_account_id() + ')';
        return fltr;
    }
    core_ac.sub_head_combo_filter = sub_head_combo_filter;

    function sub_head_alloc(rw) {
        if (rw['account_id']() == -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
        } else {
            $.ajax({
                url: '?r=core%2Fac%2Fform%2Fdetailrequired',
                type: 'GET',
                data: {'account_id': rw['account_id']()},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        if (jsonResult['is_detail_reqd'] === 'true') {
                            coreWebApp.ModelBo.sub_head_ledger_temp.removeAll();
                            coreWebApp.ModelBo.ref_ledger_alloc_temp.removeAll();
                            if (jsonResult['sub_head_dim_id'] != -1) { // Sub head ledger
                                for (var p = 0; p < rw['sub_head_ledger_tran']().length; p++)
                                {
                                    var r = coreWebApp.ModelBo.addNewRow('sub_head_ledger_temp', coreWebApp.ModelBo);
                                    r.sub_head_ledger_id(rw['sub_head_ledger_tran']()[p]['sub_head_ledger_id']());
                                    r.company_id(rw['sub_head_ledger_tran']()[p]['company_id']());
                                    r.branch_id(rw['sub_head_ledger_tran']()[p]['branch_id']());
                                    r.finyear(rw['sub_head_ledger_tran']()[p]['finyear']());
                                    r.voucher_id(rw['sub_head_ledger_tran']()[p]['voucher_id']());
                                    r.doc_date(rw['sub_head_ledger_tran']()[p]['doc_date']());
                                    r.account_id(rw['sub_head_ledger_tran']()[p]['account_id']());
                                    r.sub_head_id(rw['sub_head_ledger_tran']()[p]['sub_head_id']());
                                    r.fc_type_id(rw['sub_head_ledger_tran']()[p]['fc_type_id']());
                                    r.exch_rate(rw['sub_head_ledger_tran']()[p]['exch_rate']());
                                    r.debit_amt_fc(rw['sub_head_ledger_tran']()[p]['debit_amt_fc']());
                                    r.credit_amt_fc(rw['sub_head_ledger_tran']()[p]['credit_amt_fc']());
                                    r.credit_amt(rw['sub_head_ledger_tran']()[p]['credit_amt']());
                                    r.debit_amt(rw['sub_head_ledger_tran']()[p]['debit_amt']());
                                    r.narration(rw['sub_head_ledger_tran']()[p]['narration']());
                                    r.status(rw['sub_head_ledger_tran']()[p]['status']());
                                    r.not_by_alloc(rw['sub_head_ledger_tran']()[p]['not_by_alloc']());
                                }
                                if (rw['dc']() == 'C') {
                                    coreWebApp.ModelBo.balance_debit_total(0);
                                    coreWebApp.ModelBo.vch_tran_debit(0);
                                    coreWebApp.ModelBo.sub_head_account_id(rw['account_id']());
                                    coreWebApp.ModelBo.vch_tran_credit(rw['credit_amt']());
                                    coreWebApp.ModelBo.sub_head_branch_id(rw['branch_id']())
                                    coreWebApp.ModelBo.sub_head_sl_no(rw['sl_no']());
                                    coreWebApp.showAlloc('core/ac', '/subHeadAlloc/SubHeadAllocCredit', 'core_ac.sub_head_alloc_init', 'core_ac.sub_head_alloc_update', 'core_ac.cancelAllocUpdate');
                                } else if (rw['dc']() == 'D') {
                                    coreWebApp.ModelBo.balance_credit_total(0);
                                    coreWebApp.ModelBo.vch_tran_credit(0);
                                    coreWebApp.ModelBo.sub_head_account_id(rw['account_id']());
                                    coreWebApp.ModelBo.vch_tran_debit(rw['debit_amt']());
                                    coreWebApp.ModelBo.sub_head_branch_id(rw['branch_id']())
                                    coreWebApp.ModelBo.sub_head_sl_no(rw['sl_no']());
                                    coreWebApp.showAlloc('core/ac', '/subHeadAlloc/SubHeadAllocDebit', 'core_ac.sub_head_alloc_init', 'core_ac.sub_head_alloc_update', 'core_ac.cancelAllocUpdate');
                                }
                            }
                            if (jsonResult['is_ref_ledger'] === 'true') { // Ref ledger
                                for (var p = 0; p < rw['ref_ledger_alloc_tran']().length; p++)
                                {
                                    var r = coreWebApp.ModelBo.addNewRow('ref_ledger_alloc_temp', coreWebApp.ModelBo);
                                    r.ref_ledger_id(rw['ref_ledger_alloc_tran']()[p]['ref_ledger_id']());
                                    r.ref_ledger_alloc_id(rw['ref_ledger_alloc_tran']()[p]['ref_ledger_alloc_id']());
                                    r.branch_id(rw['ref_ledger_alloc_tran']()[p]['branch_id']());
                                    r.affect_voucher_id(rw['ref_ledger_alloc_tran']()[p]['affect_voucher_id']());
                                    r.affect_vch_tran_id(rw['ref_ledger_alloc_tran']()[p]['affect_vch_tran_id']());
                                    r.affect_doc_date(rw['ref_ledger_alloc_tran']()[p]['affect_doc_date']());
                                    r.account_id(rw['ref_ledger_alloc_tran']()[p]['account_id']());
                                    r.net_credit_amt(rw['ref_ledger_alloc_tran']()[p]['net_credit_amt']());
                                    r.net_debit_amt(rw['ref_ledger_alloc_tran']()[p]['net_debit_amt']());
                                    r.status(rw['ref_ledger_alloc_tran']()[p]['status']());
                                }
                                coreWebApp.ModelBo.ref_dc(rw['dc']());
                                coreWebApp.ModelBo.ref_no(rw['ref_no']())
                                coreWebApp.ModelBo.ref_desc(rw['ref_desc']());
                                if (rw['dc']() == 'C') {
                                    coreWebApp.ModelBo.balance_debit_total(0);
                                    coreWebApp.ModelBo.vch_tran_debit(0);
                                    coreWebApp.ModelBo.sub_head_account_id(rw['account_id']());
                                    coreWebApp.ModelBo.vch_tran_credit(rw['credit_amt']());
                                    coreWebApp.ModelBo.sub_head_branch_id(rw['branch_id']())
                                    coreWebApp.ModelBo.sub_head_sl_no(rw['sl_no']());
                                    coreWebApp.ModelBo.tran_branch_id(rw['branch_id']);
                                    if (rw['ref_ledger_alloc_tran']().length > 0) {
                                        core_ac.ref_alloc();
                                    } else {
                                        coreWebApp.ModelBo.is_alloc_ref(false);
                                        coreWebApp.ModelBo.is_create_ref(true);
                                    }
                                    coreWebApp.showAlloc('core/ac', '/subHeadAlloc/RefLedgerAllocCredit', 'core_ac.sub_head_alloc_init', 'core_ac.ref_alloc_update', 'core_ac.cancelAllocUpdate');
                                } else if (rw['dc']() == 'D') {
                                    coreWebApp.ModelBo.balance_credit_total(0);
                                    coreWebApp.ModelBo.vch_tran_credit(0);
                                    coreWebApp.ModelBo.sub_head_account_id(rw['account_id']());
                                    coreWebApp.ModelBo.vch_tran_debit(rw['debit_amt']());
                                    coreWebApp.ModelBo.sub_head_branch_id(rw['branch_id']())
                                    coreWebApp.ModelBo.sub_head_sl_no(rw['sl_no']());
                                    coreWebApp.ModelBo.tran_branch_id(rw['branch_id']);
                                    if (rw['ref_ledger_alloc_tran']().length > 0) {
                                        core_ac.ref_alloc();
                                    } else {
                                        coreWebApp.ModelBo.is_alloc_ref(false);
                                        coreWebApp.ModelBo.is_create_ref(true);
                                    }
                                    coreWebApp.showAlloc('core/ac', '/subHeadAlloc/RefLedgerAllocDebit', 'core_ac.sub_head_alloc_init', 'core_ac.ref_alloc_update', 'core_ac.cancelAllocUpdate');
                                }
                            }
                        } else {
                            coreWebApp.toastmsg('warning', 'Select Details', 'Details are not available for the account.', false);
                        }
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Sub Head', 'Failed with errors on server', true);
                }
            });
        }
    }
    core_ac.sub_head_alloc = sub_head_alloc;

    function sub_head_alloc_init() {
    }
    core_ac.sub_head_alloc_init = sub_head_alloc_init;

    function cancelAllocUpdate() {
        coreWebApp.ModelBo.sub_head_ledger_temp.removeAll();
    }
    core_ac.cancelAllocUpdate = cancelAllocUpdate;

    function sub_head_alloc_update() {
        if (parseFloat(coreWebApp.ModelBo.balance_debit_total()) == 0 && parseFloat(coreWebApp.ModelBo.balance_credit_total()) == 0) {
            for (var a = 0; a < coreWebApp.ModelBo.sub_head_ledger_temp().length; a++)
            {
                var sub_head_cnt = 0;
                for (var b = 0; b < coreWebApp.ModelBo.sub_head_ledger_temp().length; b++)
                {
                    if (coreWebApp.ModelBo.sub_head_ledger_temp()[a]['sub_head_id']() == coreWebApp.ModelBo.sub_head_ledger_temp()[b]['sub_head_id']()) {
                        sub_head_cnt = sub_head_cnt + 1;
                    }
                }

                if (sub_head_cnt > 1) {
                    return 'Duplicate Sub Head not allowed.';
                    break;
                }
            }

            ko.utils.arrayForEach(coreWebApp.ModelBo.vch_tran(), function (a) {
                if (a.sl_no() == coreWebApp.ModelBo.sub_head_sl_no()) {

                    a['sub_head_ledger_tran'].removeAll();
                    for (var b = 0; b < coreWebApp.ModelBo.sub_head_ledger_temp().length; b++)
                    {
                        var newItem = coreWebApp.ModelBo.addNewRow('sub_head_ledger_tran', a);
                        newItem.company_id(coreWebApp.ModelBo.company_id());
                        newItem.branch_id(a['branch_id']());
                        newItem.finyear(coreWebApp.ModelBo.finyear());
                        newItem.voucher_id(a['voucher_id']());
                        newItem.vch_tran_id(a['vch_tran_id']());
                        newItem.doc_date(coreWebApp.ModelBo.doc_date());
                        newItem.account_id(a['account_id']());
                        newItem.sub_head_id(coreWebApp.ModelBo.sub_head_ledger_temp()[b]['sub_head_id']());
                        newItem.fc_type_id(coreWebApp.ModelBo.fc_type_id());
                        newItem.exch_rate(coreWebApp.ModelBo.exch_rate());
                        newItem.debit_amt(coreWebApp.ModelBo.sub_head_ledger_temp()[b]['debit_amt']());
                        newItem.credit_amt(coreWebApp.ModelBo.sub_head_ledger_temp()[b]['credit_amt']());
                        newItem.narration(coreWebApp.ModelBo.sub_head_ledger_temp()[b]['narration']());
                        newItem.status(coreWebApp.ModelBo.status());
                        newItem.not_by_alloc(0);
                    }
                }
            });
            return 'OK';
        } else {
            if (coreWebApp.ModelBo.balance_debit_total() != 0) {
                return 'Please allocate balance ' + coreWebApp.ModelBo.balance_debit_total() + ' to apporpriate Sub Head.';
            } else if (coreWebApp.ModelBo.balance_credit_total() != 0) {
                return 'Please allocate balance ' + coreWebApp.ModelBo.balance_credit_total() + ' to apporpriate Sub Head.';
            }
        }

    }
    core_ac.sub_head_alloc_update = sub_head_alloc_update;

    function create_ref_alloc(rw) {
        console.log('create_ref_alloc');
        coreWebApp.ModelBo.is_alloc_ref(false);
        coreWebApp.ModelBo.is_create_ref(true);
        console.log('create_ref_alloc');
    }
    core_ac.create_ref_alloc = create_ref_alloc;

    function ref_alloc() {
        console.log('ref_alloc');
        coreWebApp.ModelBo.is_alloc_ref(true);
        coreWebApp.ModelBo.is_create_ref(false);
        var start;
        $.ajax({
            url: '?r=core%2Fac%2Fform%2Frefalloc',
            type: 'GET',
            data: {'voucher_id': coreWebApp.ModelBo.voucher_id, 'doc_date': coreWebApp.ModelBo.doc_date,
                'account_id': coreWebApp.ModelBo.sub_head_account_id(), 'dc': coreWebApp.ModelBo.ref_dc,
                'branch_id': coreWebApp.ModelBo.tran_branch_id()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                start = new Date().getTime();
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.ref_ledger_alloc_temp.removeAll();
                    for (var p = 0; p < jsonResult['rl_balance'].length; p++)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('ref_ledger_alloc_temp', coreWebApp.ModelBo, false);
                        r1.ref_ledger_id(jsonResult['rl_balance'][p]['ref_ledger_id']);
                        r1.affect_voucher_id(jsonResult['rl_balance'][p]['voucher_id']);
                        r1.branch_id(jsonResult['rl_balance'][p]['branch_id']);
                        r1.account_id(jsonResult['rl_balance'][p]['account_id']);
                        r1.balance(jsonResult['rl_balance'][p]['balance']);
                        r1.affect_doc_date(jsonResult['rl_balance'][p]['doc_date']);
                        r1.ref_no(jsonResult['rl_balance'][p]['ref_no']);
                        for (var b = 0; b < coreWebApp.ModelBo.vch_tran().length; b++)
                        {
                            if (coreWebApp.ModelBo.vch_tran()[b]['sl_no']() == coreWebApp.ModelBo.sub_head_sl_no()) {

                                for (var a = 0; a < coreWebApp.ModelBo.vch_tran()[b]['ref_ledger_alloc_tran']().length; a++)
                                {
                                    if (coreWebApp.ModelBo.vch_tran()[b]['ref_ledger_alloc_tran']()[a]['ref_ledger_id']() === jsonResult['rl_balance'][p]['ref_ledger_id']) {
                                        r1.net_credit_amt(coreWebApp.ModelBo.vch_tran()[b]['ref_ledger_alloc_tran']()[a]['net_credit_amt']());
                                        r1.net_debit_amt(coreWebApp.ModelBo.vch_tran()[b]['ref_ledger_alloc_tran']()[a]['net_debit_amt']());
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                    coreWebApp.ModelBo.ref_ledger_alloc_temp.valueHasMutated();
                    $('#adv-alloc-loading').hide();
                    //applysmartcontrols($('#cdialog'));
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', true);
            }
        });
    }
    core_ac.ref_alloc = ref_alloc;

    function ref_alloc_update() {
        var ref_total = 0;
        for (var a = 0; a < coreWebApp.ModelBo.ref_ledger_alloc_temp().length; a++)
        {
            ref_total = ref_total + parseFloat(coreWebApp.ModelBo.ref_ledger_alloc_temp()[a]['net_debit_amt']()) + parseFloat(coreWebApp.ModelBo.ref_ledger_alloc_temp()[a]['net_credit_amt']());
        }

        if (coreWebApp.ModelBo.ref_no() != '' && ref_total != 0) {
            return 'You cannot Create and Allocate Reference at a time.';
        }
        ko.utils.arrayForEach(coreWebApp.ModelBo.vch_tran(), function (a) {
            if (a.sl_no() == coreWebApp.ModelBo.sub_head_sl_no()) {
                if (coreWebApp.ModelBo.ref_no() == '' && coreWebApp.ModelBo.balance_debit_total() != 0) {
                    return 'Please allocate balance ' + coreWebApp.ModelBo.balance_debit_total() + ' to apporpriate Ref Ledger.';
                } else if (coreWebApp.ModelBo.ref_no() == '' && coreWebApp.ModelBo.balance_credit_total() != 0) {
                    return 'Please allocate balance ' + coreWebApp.ModelBo.balance_credit_total() + ' to apporpriate Ref Ledger.';
                }
                a.ref_no(coreWebApp.ModelBo.ref_no());
                a.ref_desc(coreWebApp.ModelBo.ref_desc());
                a['sub_head_ledger_tran'].removeAll();
                a['ref_ledger_alloc_tran'].removeAll();
                var sl = 0;
                for (var b = 0; b < coreWebApp.ModelBo.ref_ledger_alloc_temp().length; b++)
                {
                    if (coreWebApp.ModelBo.ref_ledger_alloc_temp()[b]['net_debit_amt']() > 0 || coreWebApp.ModelBo.ref_ledger_alloc_temp()[b]['net_credit_amt']() > 0) {
                        sl = sl + 1;
                        var newItem = coreWebApp.ModelBo.addNewRow('ref_ledger_alloc_tran', a);
                        newItem.branch_id(a['branch_id']());
                        newItem.affect_voucher_id(a['voucher_id']());
                        newItem.affect_vch_tran_id(a['vch_tran_id']());
                        newItem.affect_doc_date(coreWebApp.ModelBo.doc_date());
                        newItem.account_id(a['account_id']());
                        newItem.net_debit_amt(coreWebApp.ModelBo.ref_ledger_alloc_temp()[b]['net_debit_amt']());
                        newItem.net_credit_amt(coreWebApp.ModelBo.ref_ledger_alloc_temp()[b]['net_credit_amt']());
                        newItem.ref_ledger_id(coreWebApp.ModelBo.ref_ledger_alloc_temp()[b]['ref_ledger_id']())
                        newItem.ref_ledger_alloc_id(sl)
                        newItem.status(coreWebApp.ModelBo.status());
                    }
                }
            }
        });
        return 'OK';
    }
    core_ac.ref_alloc_update = ref_alloc_update;

    function visible_create_ref(dataItem) {
        if (coreWebApp.ModelBo.is_create_ref() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.visible_create_ref = visible_create_ref;

    function visible_alloc_ref(dataItem) {
        if (coreWebApp.ModelBo.is_alloc_ref() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.visible_alloc_ref = visible_alloc_ref;

    function cal_ref_total_amt(dataItem) {
        ko.utils.arrayForEach(coreWebApp.ModelBo.vch_tran(), function (a) {
            total += new Number(item.net_credit_amt());
        });
        dataItem['ref_total_amt'](total);
    }
    core_ac.cal_ref_total_amt = cal_ref_total_amt;

    function ref_led_acc_combo_filter(fltr) {
        fltr = ' (is_ref_ledger = true Or account_type_id = 49) ';
        return fltr;
    }
    core_ac.ref_led_acc_combo_filter = ref_led_acc_combo_filter;

    function enable_recodate() {
        return coreWebApp.ModelBo.collected();
    }
    core_ac.enable_recodate = enable_recodate;

    function sub_head_alloc_ui(opts) {
        $.ajax({
            url: '?r=core/ac/form/detailrequired',
            type: 'GET',
            data: {'account_id': opts.account_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    if (jsonResult['is_detail_reqd'] === 'true') {
                        if (jsonResult['sub_head_dim_id'] != -1) { // Sub head ledger                            
                            opts.module = 'core/ac';
                            opts.alloc_view = 'subHeadAlloc/SubHeadAllocUI';
                            opts.call_init = sub_head_init;
                            opts.call_update = sub_head_update;
                            coreWebApp.showAllocV2(opts);
                        }
                        if (jsonResult['is_ref_ledger'] === 'true') {
                            opts.module = 'core/ac';
                            opts.alloc_view = 'subHeadAlloc/RefLedgerAllocUI';
                            opts.call_init = ref_ledger_init;
                            opts.call_update = ref_ledger_update;
                            coreWebApp.showAllocV2(opts);
                        }
                    } else {
                        coreWebApp.toastmsg('warning', 'Select Details', 'Details are not available for the account.', false);
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Sub Head', 'Failed with errors on server', true);
            }
        });
    }
    core_ac.sub_head_alloc_ui = sub_head_alloc_ui;

    function sub_head_init(opts, after_init) {
        var sub_head_alloc = new function () {
            self = this;
            this.sl_no = ko.observable(opts.sl_no);
            this.sub_head_account_id = ko.observable(opts.account_id);
            this.debit_amt_total = ko.observable(opts.debit_amt_total);
            this.debit_amt_total_fc = ko.observable(opts.debit_amt_total_fc);
            this.total_amt = ko.pureComputed(function () {
                var total = new Number();
                ko.utils.arrayForEach(self.sl_temp(), function (item) {
                    total += parseFloat(item.alloc_amt());
                });
                return total.toFixed(2);
            });
            this.total_amt_fc = ko.pureComputed(function () {
                var total_fc = new Number();
                ko.utils.arrayForEach(self.sl_temp(), function (item) {
                    total_fc += parseFloat(item.alloc_amt_fc());
                });
                return total_fc.toFixed(2);
            });
            this.balance_total = ko.pureComputed(function () {
                var balance_total = parseFloat(self.debit_amt_total()) - parseFloat(self.total_amt());
                return balance_total.toFixed(2);
            });
            this.balance_total_fc = ko.pureComputed(function () {
                var balance_total_fc = parseFloat(self.debit_amt_total_fc()) - parseFloat(self.total_amt_fc());
                return balance_total_fc.toFixed(2);
            });
        };
        sub_head_alloc.sl_temp = build_sl_temp();
        for (var a = 0; a < opts.sl_tran().length; a++)
        {
            var slt = opts.sl_tran()[a];
            var nr = sub_head_alloc.sl_temp.addNewRow();
            nr.sub_head_ledger_id(slt.sub_head_ledger_id());
            nr.branch_id(slt.branch_id());
            nr.voucher_id(slt.voucher_id());
            nr.doc_date(slt.doc_date());
            nr.account_id(slt.account_id());
            nr.sub_head_id(slt.sub_head_id());
            nr.fc_type_id(slt.fc_type_id());
            nr.exch_rate(slt.exch_rate());
            nr.debit_amt_fc(slt.debit_amt_fc());
            nr.credit_amt_fc(slt.credit_amt_fc());
            nr.credit_amt(slt.credit_amt());
            nr.debit_amt(slt.debit_amt());
            nr.narration(slt.narration());
            nr.status(slt.status());
            nr.not_by_alloc(slt.not_by_alloc());
            if (opts.dc == 'C') {
                nr.alloc_amt(slt.credit_amt());
                nr.alloc_amt_fc(slt.credit_amt_fc());
            } else {
                nr.alloc_amt(slt.debit_amt());
                nr.alloc_amt_fc(slt.debit_amt_fc());
            }
            sub_head_alloc.sl_temp.push(nr);
        }
        sub_head_alloc.addNewRow = function () {
            var nr = sub_head_alloc.sl_temp.addNewRow();
            nr.account_id(opts.account_id);
            nr.branch_id(opts.branch_id);
            sub_head_alloc.sl_temp.push(nr);
        }
        opts.model = sub_head_alloc;
    }
    core_ac.sub_head_init = sub_head_init;

    function build_sl_temp() {
        var sl_temp = ko.observableArray();
        sl_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.sub_head_id = ko.observable(-1);
            cobj.branch_id = ko.observable(-1);
            cobj.account_id = ko.observable(-1);
            cobj.fc_type_id = ko.observable(-1);
            cobj.voucher_id = ko.observable('');
            cobj.vch_tran_id = ko.observable('');
            cobj.sub_head_ledger_id = ko.observable('');
            cobj.narration = ko.observable('');
            cobj.exch_rate = ko.observable(1);
            cobj.debit_amt_fc = ko.observable(0);
            cobj.credit_amt_fc = ko.observable(0);
            cobj.debit_amt = ko.observable(0);
            cobj.credit_amt = ko.observable(0);
            cobj.alloc_amt = ko.observable(0);
            cobj.alloc_amt_fc = ko.observable(0);
            cobj.status = ko.observable(0);
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.is_select = ko.observable(false);
            cobj.not_by_alloc = ko.observable(false);
            return cobj;
        };
        return sl_temp;
    }
    core_ac.build_sl_temp = build_sl_temp;

    function sub_head_update(opts) {
        var is_valid = true;
        if (opts.fc_type_id !== 0) {
            // validate balance fc
            if (opts.model.balance_total_fc() != 0) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Please allocate balance ' + opts.model.balance_total_fc() + ' to apporpriate Sub Head.');
                return false;
            }
        } else {
            // validate balance
            if (opts.model.balance_total() != 0) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Please allocate balance ' + opts.model.balance_total() + ' to apporpriate Sub Head.');
                return false;
            }
        }
        var is_valid = true;
        // Check duplicate sub heads
        for (var a = 0; a < opts.model.sl_temp().length; a++)
        {
            var sub_head_cnt = 0;
            for (var b = 0; b < opts.model.sl_temp().length; b++)
            {
                if (opts.model.sl_temp()[a]['sub_head_id']() == opts.model.sl_temp()[b]['sub_head_id']()) {
                    sub_head_cnt = sub_head_cnt + 1;
                }
            }

            if (sub_head_cnt > 1) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Duplicate Sub Heads not allowed');
                return false;
            }
        }
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }
        // clear existing alloc
        opts.sl_tran.removeAll();

        for (var p = 0; p < opts.model.sl_temp().length; ++p) {
            var slt = opts.model.sl_temp()[p];
            if (parseFloat(slt.alloc_amt()) > 0 || parseFloat(slt.alloc_amt_fc()) > 0) {
                var nr = coreWebApp.ModelBo.addNewRow(opts.shl_tran_name, opts.row);
                nr.company_id(coreWebApp.ModelBo.company_id());
                nr.branch_id(slt.branch_id());
                nr.finyear(coreWebApp.ModelBo.finyear());
                nr.voucher_id(slt.voucher_id());
                nr.vch_tran_id(slt.vch_tran_id());
                nr.doc_date(coreWebApp.ModelBo.doc_date());
                nr.account_id(opts.model.sub_head_account_id());
                nr.sub_head_id(slt.sub_head_id());
                nr.fc_type_id(coreWebApp.ModelBo.fc_type_id());
                nr.exch_rate(coreWebApp.ModelBo.exch_rate());
                if (opts.dc == 'C') {
                    nr.credit_amt(slt.alloc_amt());
                    nr.credit_amt_fc(slt.alloc_amt_fc());
                } else {
                    nr.debit_amt(slt.alloc_amt());
                    nr.debit_amt_fc(slt.alloc_amt_fc());
                }
                nr.narration(slt.narration());
                nr.not_by_alloc(0);
            }
        }
        return true;
    }
    core_ac.sub_head_update = sub_head_update;

    function ref_ledger_init(opts, after_init) {
        var ref_ledger_alloc = new function () {
            self = this;
            this.sl_no = ko.observable(opts.sl_no);
            this.voucher_id = ko.observable(opts.voucher_id);
            this.branch_id = ko.observable(opts.branch_id);
            this.ref_no = ko.observable(opts.ref_no);
            this.ref_desc = ko.observable(opts.ref_desc);
            this.is_create_ref = ko.observable(opts.is_create_ref);
            this.is_alloc_ref = ko.observable(opts.is_alloc_ref);
            this.account_id = ko.observable(opts.account_id);
            this.debit_amt_total = ko.observable(opts.debit_amt_total);
            this.debit_amt_total_fc = ko.observable(opts.debit_amt_total_fc);
            this.total_amt = ko.pureComputed(function () {
                var total = new Number();
                ko.utils.arrayForEach(self.ref_ledger_temp(), function (item) {
                    total += parseFloat(item.alloc_amt());
                });
                return total.toFixed(2);
            });
            this.total_amt_fc = ko.pureComputed(function () {
                var total_fc = new Number();
                ko.utils.arrayForEach(self.ref_ledger_temp(), function (item) {
                    total_fc += parseFloat(item.alloc_amt_fc());
                });
                return total_fc.toFixed(2);
            });
            this.balance_total = ko.pureComputed(function () {
                var balance_total = parseFloat(self.debit_amt_total()) - parseFloat(self.total_amt());
                return balance_total.toFixed(2);
            });
            this.balance_total_fc = ko.pureComputed(function () {
                var balance_total_fc = parseFloat(self.debit_amt_total_fc()) - parseFloat(self.total_amt_fc());
                return balance_total_fc.toFixed(2);
            });
        };
        ref_ledger_alloc.ref_ledger_temp = build_ref_ledger_temp();
        ref_ledger_alloc.ref_ledger_tran = opts.ref_ledger_tran;
        if (opts.ref_ledger_tran().length > 0) {
            core_ac.ref_alloc_click(ref_ledger_alloc);
        } else {
            ref_ledger_alloc.is_alloc_ref(false);
            ref_ledger_alloc.is_create_ref(true);
        }
        opts.model = ref_ledger_alloc;
    }

    function build_ref_ledger_temp() {
        var ref_ledger_temp = ko.observableArray();
        ref_ledger_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.ref_ledger_id = ko.observable('');
            cobj.ref_ledger_alloc_id = ko.observable('');
            cobj.branch_id = ko.observable(-1);
            cobj.account_id = ko.observable(-1);
            cobj.affect_voucher_id = ko.observable('');
            cobj.affect_vch_tran_id = ko.observable('');
            cobj.ref_no = ko.observable('');
            cobj.balance = ko.observable(0);
            cobj.net_credit_amt = ko.observable(0);
            cobj.net_debit_amt = ko.observable(0);
            cobj.alloc_amt = ko.observable(0);
            cobj.alloc_amt_fc = ko.observable(0);
            cobj.status = ko.observable(0);
            cobj.affect_doc_date = ko.observable('1970-01-01');
            cobj.is_select = ko.observable(false);
            return cobj;
        };
        return ref_ledger_temp;
    }
    core_ac.build_ref_ledger_temp = build_ref_ledger_temp;

    function ref_ledger_update(opts) {
        debugger;
        var is_valid = true;
        if (opts.model.ref_no() != '' && opts.model.total_amt() != 0) {
            coreWebApp.toastmsg('warning', 'Allocations', 'You cannot Create and Allocate Reference at a time.');
            return false;
        }
        if (opts.model.ref_no() == '' && opts.model.balance_total() != 0) {
            coreWebApp.toastmsg('warning', 'Allocations', 'Please allocate balance ' + opts.model.balance_total() + ' to apporpriate Ref Ledger.');
            return false;
        }
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }
        // clear existing alloc
        opts.ref_ledger_tran.removeAll();
        opts.sl_tran.removeAll();

        opts.row.ref_no(opts.model.ref_no());
        opts.row.ref_desc(opts.model.ref_desc());
        for (var p = 0; p < opts.model.ref_ledger_temp().length; ++p) {
            var rlt = opts.model.ref_ledger_temp()[p];
            var sl = 0;
            if (parseFloat(rlt.alloc_amt()) > 0 || parseFloat(rlt.alloc_amt_fc()) > 0) {
                sl = sl + 1;
                var newItem = coreWebApp.ModelBo.addNewRow(opts.rla_tran_name, opts.row);
                newItem.branch_id(rlt.branch_id());
                newItem.affect_voucher_id(rlt.affect_voucher_id());
                newItem.affect_vch_tran_id(rlt.affect_vch_tran_id());
                newItem.affect_doc_date(coreWebApp.ModelBo.doc_date());
                newItem.account_id(rlt.account_id());
                if (opts.dc == 'C') {
                    newItem.net_credit_amt(rlt.alloc_amt());
                } else {
                    newItem.net_debit_amt(rlt.alloc_amt());
                }
                newItem.ref_ledger_id(rlt.ref_ledger_id())
                newItem.ref_ledger_alloc_id(sl)
                newItem.status(coreWebApp.ModelBo.status());
            }
        }
        return 'OK';
    }
    core_ac.ref_ledger_update = ref_ledger_update;

    function ref_alloc_click(row) {
        console.log('allocate_ref');
        row.is_alloc_ref(true);
        row.is_create_ref(false);
        var start;
        $.ajax({
            url: '?r=core/ac/form/refalloc',
            type: 'GET',
            data: {'voucher_id': row.voucher_id(), 'doc_date': coreWebApp.ModelBo.doc_date,
                'account_id': row.account_id(), 'dc': 'D',
                'branch_id': row.branch_id()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                start = new Date().getTime();
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    row.ref_ledger_temp.removeAll();

                    for (var p = 0; p < jsonResult['rl_balance'].length; p++)
                    {
                        var rlt = jsonResult['rl_balance'][p];
                        var nr = row.ref_ledger_temp.addNewRow();
                        nr.ref_ledger_id(rlt['ref_ledger_id']);
                        nr.affect_voucher_id(rlt['voucher_id']);
                        nr.branch_id(rlt['branch_id']);
                        nr.account_id(rlt['account_id']);
                        nr.balance(rlt['balance']);
                        nr.affect_doc_date(rlt['doc_date']);
                        nr.ref_no(rlt['ref_no']);
                        for (var a = 0; a < row.ref_ledger_tran().length; a++)
                        {                            
                            var rlt_row = row.ref_ledger_tran()[a];
                            if (rlt_row.ref_ledger_id() == rlt['ref_ledger_id']){
                                nr.net_credit_amt(rlt_row.net_credit_amt());
                                nr.net_debit_amt(rlt_row.net_debit_amt());
                                nr.status(rlt_row.status());
                                if (parseFloat(rlt_row.net_credit_amt())>0) {
                                    nr.alloc_amt(rlt_row.net_credit_amt());
                                } else {
                                    nr.alloc_amt(rlt_row.net_debit_amt());
                                }
                            }
                        }
                        row.ref_ledger_temp.push(nr);
                    }
                    $('#adv-alloc-loading').hide();
                    //applysmartcontrols($('#cdialog'));
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', true);
            }
        });
    }
    core_ac.ref_alloc_click = ref_alloc_click;

    function create_ref_alloc_click(row) {
        console.log('create_ref_alloc');
        row.is_create_ref(true);
        row.is_alloc_ref(false);
        console.log('create_ref_alloc');
    }
    core_ac.create_ref_alloc_click = create_ref_alloc_click;

    function sub_head_filter(fltr, datacontext) {
        fltr = ' sub_head_dim_id in (select sub_head_dim_id from ac.account_head where account_id = ' + datacontext.account_id() + ')';
        return fltr;
    }

    core_ac.sub_head_filter = sub_head_filter;

    function create_ref_visible(dataItem) {
        if (dataItem.is_create_ref() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.create_ref_visible = create_ref_visible;

    function alloc_ref_visible(dataItem) {
        if (dataItem.is_alloc_ref() == true) {
            return true;
        } else {
            return false;
        }
    }
    core_ac.alloc_ref_visible = alloc_ref_visible;

    function rpt_sub_head_filter(dataItem) {
        if (parseInt($('#paccount_id').val()) !== -99) {
            fltr = ' account_id = ' + $('#paccount_id').val();
        }
        else{
            fltr = '';
        }
        return fltr;
    }
    core_ac.rpt_sub_head_filter = rpt_sub_head_filter;
    
}(window.core_ac));


