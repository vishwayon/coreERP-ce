// Declare core_payrollgeneration Namespace

window.core_payrollgeneration = {};

(function (core_payrollgeneration) {    
    
    function enable_pr_gen(dataItem) {            
        if(dataItem.status() == 5){
            return false;            
        }
        else {
            return true;
        }
     };
     
    core_payrollgeneration.enable_pr_gen=enable_pr_gen  
    
    function enable_custom_amt(dataItem) {            
        if(dataItem.selected() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_payrollgeneration.enable_custom_amt=enable_custom_amt  
    
    function view_method(pr,prop,rw){
        console.log('view_method');
        coreWebApp.showAlloc('core/hr','payrollGeneration/PayrollGenerationViewTran','core_payrollgeneration.view_init',null,null,rw);
    }
    
    core_payrollgeneration.view_method=view_method;   
    
    
    function view_init(){
        console.log('view_init');
    }
    core_payrollgeneration.view_init = view_init;
    
    function payroll_date_changed(dataItem) 
    {
       generate_payroll();
    };

    core_payrollgeneration.payroll_date_changed = payroll_date_changed; 
    
    function hide_tran(dataItem) {
        return false;
    }

    core_payrollgeneration.hide_tran = hide_tran;

    function getpayheaddetail(dataItem){
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fgetpayheaddetail',
            type: 'GET',
            data: {'payhead_id': dataItem['payhead_id']},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    dataItem.payhead(jsonResult['payhead_detail'][0]['payhead']);
                    dataItem.payhead_type(jsonResult['payhead_detail'][0]['payhead_type']);
                    dataItem.monthly_or_onetime(jsonResult['payhead_detail'][0]['monthly_or_onetime']);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Get Payhead Detail', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }

    core_payrollgeneration.getpayheaddetail = getpayheaddetail;

    function generate_payroll() {
        if(coreWebApp.ModelBo.payroll_tran().length > 0){            
            coreWebApp.showAlloc('core/hr', '/payrollGeneration/PayheadCustomAmount', 'core_payrollgeneration.generate_pr_init', 'core_payrollgeneration.generate_pr_update', 'core_payrollgeneration.cancelUpdate');
        }
        else{
            calculate_payroll();
        }
    }

    core_payrollgeneration.generate_payroll = generate_payroll;    

    function generate_pr_init() {
        for (var a = 0; a < coreWebApp.ModelBo.payroll_custom_tran().length; a++)
        {
            for (var b = 0; b < coreWebApp.ModelBo.payroll_custom_tran_temp().length; b++)
            {
                if ((coreWebApp.ModelBo.payroll_custom_tran()[a]['employee_id']() === coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['employee_id']()) 
                        && (coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_id']() === coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['payhead_id']())) {
                    if(coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_type']() == 'E' || coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_type']() == 'C'){
                        coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['amt'](coreWebApp.ModelBo.payroll_custom_tran()[a]['emolument_amt']());                       
                    }
                    else{
                        coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['amt'](coreWebApp.ModelBo.payroll_custom_tran()[a]['deduction_amt']());
                    }
                    break;
                }
            }
        }
    }
    core_payrollgeneration.generate_pr_init = generate_pr_init;
    
    function cancelUpdate() {
    }
    core_payrollgeneration.cancelUpdate = cancelUpdate;    

    function generate_pr_update() {
        debugger;
        for (var a = 0; a < coreWebApp.ModelBo.payroll_custom_tran().length; a++)
        {
            for (var b = 0; b < coreWebApp.ModelBo.payroll_custom_tran_temp().length; b++)
            {
                if ((coreWebApp.ModelBo.payroll_custom_tran()[a]['employee_id']() === coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['employee_id']()) 
                        && (coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_id']() === coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['payhead_id']())) {
                    if(coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_type']() == 'E' || coreWebApp.ModelBo.payroll_custom_tran()[a]['payhead_type']() == 'C'){
                        coreWebApp.ModelBo.payroll_custom_tran()[a]['emolument_amt'](coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['amt']());                       
                    }
                    else{
                         coreWebApp.ModelBo.payroll_custom_tran()[a]['deduction_amt'](coreWebApp.ModelBo.payroll_custom_tran_temp()[b]['amt']());
                    }
                    break;
                }
            }
        }
        calculate_payroll();
        return 'OK';
    }
    core_payrollgeneration.generate_pr_update = generate_pr_update;
    
    function calculate_payroll()
    {
        console.log('Start');
        $.ajax({
            url: '?r=core/hr/form/generatepayroll',
            type: 'POST',
            data: {'payDateFrom': coreWebApp.ModelBo.pay_from_date(), 'payDateTo': coreWebApp.ModelBo.pay_to_date(),
                'payrollGroupId': coreWebApp.ModelBo.payroll_group_id(), 'dtPayrollCustomTran': ko.mapping.toJSON(coreWebApp.ModelBo.payroll_custom_tran()), '_csrf': $('#_csrf').val()},
            beforeSend: function (xhr, opts) {
                if (coreWebApp.ModelBo.payroll_group_id() == -1) {
                    coreWebApp.toastmsg('error', 'Payroll Generation Error', 'Payroll group not selected.', true);
                        xhr.abort();
                } else 
                if (coreWebApp.ModelBo.pay_from_date() > coreWebApp.ModelBo.pay_to_date()) {
                    coreWebApp.toastmsg('error', 'Payroll Generation Error', 'Payroll To date should be greater than Payroll From date.', true);
                    xhr.abort();  
                }
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    
                    console.log('End');
                    coreWebApp.ModelBo.payroll_tran.removeAll();
                    for (var p = 0; p < jsonResult['payroll_tran'].length; ++p)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('payroll_tran', coreWebApp.ModelBo);
                        r1.sl_no(jsonResult['payroll_tran'][p]['sl_no']);
                        r1.employee_id(jsonResult['payroll_tran'][p]['employee_id']);
                        r1.employee_no(jsonResult['payroll_tran'][p]['employee_no']);
                        r1.employee_fullname(jsonResult['payroll_tran'][p]['employee_fullname']);
                        r1.pay_days(jsonResult['payroll_tran'][p]['pay_days']);
                        r1.no_pay_days(jsonResult['payroll_tran'][p]['no_pay_days']);
                        r1.half_pay_days(jsonResult['payroll_tran'][p]['half_pay_days']);
                        r1.tot_ot_hr(jsonResult['payroll_tran'][p]['tot_ot_hr']);
                        r1.tot_ot_holiday_hr(jsonResult['payroll_tran'][p]['tot_ot_holiday_hr']);
                        r1.tot_ot_special_hr(jsonResult['payroll_tran'][p]['tot_ot_special_hr']);
                        r1.tot_ot_amt(jsonResult['payroll_tran'][p]['tot_ot_amt']);
                        r1.tot_ot_holiday_amt(jsonResult['payroll_tran'][p]['tot_ot_holiday_amt']);
                        r1.tot_ot_special_amt(jsonResult['payroll_tran'][p]['tot_ot_special_amt']);
                        r1.tot_overtime_amt(jsonResult['payroll_tran'][p]['tot_overtime_amt']);
                        r1.tot_emolument_amt(jsonResult['payroll_tran'][p]['tot_emolument_amt']);
                        r1.tot_deduction_amt(jsonResult['payroll_tran'][p]['tot_deduction_amt']);
                        r1.amt_in_words(jsonResult['payroll_tran'][p]['amt_in_words'])
                        r1.block_payment(jsonResult['payroll_tran'][p]['block_payment']);
                        var slno=0;
                        var w = 0;
                        for (var t = 0; t < jsonResult['payroll_tran_detail'].length; t++) {
                            if (jsonResult['payroll_tran'][p]['employee_id'] == jsonResult['payroll_tran_detail'][t]['employee_id']) {
                                var newItem = coreWebApp.ModelBo.addNewRow('payroll_tran_detail', coreWebApp.ModelBo.payroll_tran()[p]);
                                slno = t + 1;
                                newItem.sl_no(slno);
                                newItem.employee_id(jsonResult['payroll_tran_detail'][t]['employee_id']);
                                newItem.employee_fullname(jsonResult['payroll_tran_detail'][t]['employee_fullname']);
                                newItem.payhead_id(jsonResult['payroll_tran_detail'][t]['payhead_id']);
                                newItem.payhead(jsonResult['payroll_tran_detail'][t]['payhead']);
                                newItem.payhead_type(jsonResult['payroll_tran_detail'][t]['payhead_type']);
                                newItem.emolument_amt(jsonResult['payroll_tran_detail'][t]['emolument_amt']);
                                newItem.deduction_amt(jsonResult['payroll_tran_detail'][t]['deduction_amt']);
                                newItem.monthly_or_onetime(jsonResult['payroll_tran_detail'][t]['monthly_or_onetime']);

                                for (var loan = 0; loan < jsonResult['payroll_tran_loan_detail'].length; loan++) {
                                    if (jsonResult['payroll_tran_detail'][t]['employee_id'] == jsonResult['payroll_tran_loan_detail'][loan]['employee_id'] && jsonResult['payroll_tran_detail'][t]['payhead_id'] == jsonResult['payroll_tran_loan_detail'][loan]['payhead_id']) {
                                        var newLoanItem = coreWebApp.ModelBo.addNewRow('loan_repayment_tran', coreWebApp.ModelBo.payroll_tran()[p].payroll_tran_detail()[w]);
                                        newLoanItem.sl_no(jsonResult['payroll_tran_loan_detail'][loan]['sl_no']);
                                        newLoanItem.employee_id(jsonResult['payroll_tran_loan_detail'][loan]['employee_id']);
                                        newLoanItem.employee_fullname(jsonResult['payroll_tran_loan_detail'][loan]['employee_fullname']);
                                        newLoanItem.payhead_id(jsonResult['payroll_tran_loan_detail'][loan]['payhead_id']);
                                        newLoanItem.payhead(jsonResult['payroll_tran_loan_detail'][loan]['payhead']);
                                        newLoanItem.loan_id(jsonResult['payroll_tran_loan_detail'][loan]['loan_id']);
                                        newLoanItem.installment_principal(jsonResult['payroll_tran_loan_detail'][loan]['installment_principal']);
                                        newLoanItem.installment_interest(jsonResult['payroll_tran_loan_detail'][loan]['installment_interest']);
                                        newLoanItem.installment_amount(jsonResult['payroll_tran_loan_detail'][loan]['installment_amount']);
                                    }
                                }
                                w = w + 1;
                             }
                        }                           
                    }

                    coreWebApp.ModelBo.payroll_tran.valueHasMutated();
//                  applysmartcontrols();
                    $('.smartcombo').each(function () {
                        if ($(this).attr('id') == 'payhead_id') {
                            $(this).trigger('change.select2');
                        }
                    });
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_payrollgeneration.calculate_payroll = calculate_payroll;

    function payroll_tran_detail_add_new_row(newRow) {
        newRow.monthly_or_onetime(0);
    }

    core_payrollgeneration.payroll_tran_detail_add_new_row = payroll_tran_detail_add_new_row;


    function payhead_combo_filter(fltr, dataItem) {
        if (dataItem.monthly_or_onetime() == 0) {
            fltr = ' monthly_or_onetime = 0';
        }
        if (dataItem.monthly_or_onetime() == 1) {
            fltr = ' monthly_or_onetime = 1';
        }
        return fltr;
    }

    core_payrollgeneration.payhead_combo_filter = payhead_combo_filter;

    function payroll_tran_detail_before_delete(pr, prop, rw) {
        console.log('deleting.....' + prop);
        if (rw.monthly_or_onetime()) {
            return false;
        }
        return true;
    }
    core_payrollgeneration.payroll_tran_detail_before_delete = payroll_tran_detail_before_delete;

    function get_payhead_detail(dataItem) {
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fgetpayheaddetail',
            type: 'GET',
            data: {'payhead_id': dataItem['payhead_id']},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    dataItem.payhead(jsonResult['payhead_detail'][0]['payhead']);
                    dataItem.payhead_type(jsonResult['payhead_detail'][0]['payhead_type']);
                    dataItem.monthly_or_onetime(jsonResult['payhead_detail'][0]['monthly_or_onetime']);
                }
//                    applysmartcontrols();
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Get Payhead Detail', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }

    core_payrollgeneration.get_payhead_detail = get_payhead_detail;

    function enable_emo_amt(dataItem) {
        if (typeof dataItem.payhead_type == 'undefined')
            return;
        if (dataItem.monthly_or_onetime() == 1)
            return false;
        if (dataItem.payhead_type() == 'E') {
            dataItem.deduction_amt(0);
            return true;
        }
        else {
            return false;
        }
    }
    ;

    core_payrollgeneration.enable_emo_amt = enable_emo_amt;

    function enable_ded_amt(dataItem) {
        if (typeof dataItem.payhead_type == 'undefined')
            return;
        if (dataItem.monthly_or_onetime() == 1)
            return false;
        if (dataItem.payhead_type() == 'D') {
            dataItem.emolument_amt(0);
            return true;
        }
        else {
            return false;
        }
    }
    ;

    core_payrollgeneration.enable_ded_amt = enable_ded_amt;

    function enable_payhead(dataItem) {
        if (typeof dataItem.payhead_type == 'undefined')
            return;
        if (dataItem.monthly_or_onetime() == 1) {
            return false;
        }
        else {
            return true;
        }
    }
    ;

    core_payrollgeneration.enable_payhead = enable_payhead;
    
    
    function change_payroll_group(dataItem) { 
        
        debugger;
        generate_payroll();  
        
    };
    
    core_payrollgeneration.change_payroll_group = change_payroll_group;
    
 }(window.core_payrollgeneration));
