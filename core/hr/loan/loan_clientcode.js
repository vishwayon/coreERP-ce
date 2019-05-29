// Declare core_loan Namespace

window.core_loan = {};

(function (core_loan) {

    function enable_loan_principal(dataItem) {
        if (typeof dataItem.en_calculate_by == 'undefined')
            return;
        if (dataItem.en_calculate_by() == '1') {
            return true;
        }
        return false;
    }
    ;

    core_loan.enable_loan_principal = enable_loan_principal;

    function enable_loan_intrest(dataItem) {
        if (typeof dataItem.en_calculate_by == 'undefined')
            return;
        if (dataItem.en_calculate_by() == '2') {
            return true;
        }
        return false;
    }
    ;

    core_loan.enable_loan_intrest = enable_loan_intrest;

    function enable_loan_tran(dataItem) {
        if (typeof dataItem.loan_repaid == 'undefined')
            return;
        if (dataItem.loan_repaid() == true) {
            return false;
        }
        return true;
    }
    ;

    core_loan.enable_loan_tran = enable_loan_tran;


    function calc_loan_detail(dataItem) {

        if (typeof dataItem.en_calculate_by == 'undefined')
            return;

        if (dataItem.no_of_installments() > 0) {

            if (dataItem.en_calculate_by() == '1') {
//                var rate = (coreWebApp.ModelBo.interest_percentage() / 100 / 12);
//                var emi = coreWebApp.ModelBo.loan_principal() * rate * (Math.pow(1 + rate, coreWebApp.ModelBo.no_of_installments()) / (Math.pow(1 + rate, coreWebApp.ModelBo.no_of_installments()) - 1));
//                dataItem.installment_interest((coreWebApp.ModelBo.loan_principal() * rate).toFixed(2));
//                dataItem.installment_principal((emi - dataItem.installment_interest()).toFixed(2));


//                dataItem.installment_principal((parseFloat(dataItem.loan_principal()) / parseFloat(dataItem.no_of_installments())).toFixed(2)); 
//                dataItem.installment_interest((parseFloat(dataItem.loan_interest()) / parseFloat(dataItem.no_of_installments())).toFixed(2));  
            }
            if (dataItem.en_calculate_by() == '2') {
                dataItem.loan_principal((parseFloat(dataItem.installment_principal()) * parseFloat(dataItem.no_of_installments())).toFixed(2));
                dataItem.loan_interest((parseFloat(dataItem.installment_interest()) * parseFloat(dataItem.no_of_installments())).toFixed(2));
            }

        }
//        dataItem.loan_interest((dataItem.installment_interest() * coreWebApp.ModelBo.no_of_installments()).toFixed(2));
        dataItem.total_recovery((parseFloat(dataItem.loan_principal()) + parseFloat(dataItem.loan_interest())).toFixed(2)); 

//        if (dataItem.installment_principal()>0){
//            dataItem.interest_percentage((parseFloat(dataItem.installment_interest()) / parseFloat(dataItem.installment_principal())).toFixed(2));
//        }
        return;
    }

    core_loan.calc_loan_detail = calc_loan_detail;

    function calc_installment() {
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fcalcinstallment',
            type: 'GET',
            data: {'installmentFrom': coreWebApp.ModelBo.loan_from_date(), 'noOfInstallments': coreWebApp.ModelBo.no_of_installments()},
            beforeSend: function (xhr, opts) {
//                if(coreWebApp.ModelBo.loan_repaid() == true) {
//                        coreWebApp.toastmsg('error','Loan Generation Error','Load full/partially has been already paid, cannot modify', false);
//                        xhr.abort();
//                }else
//                if(coreWebApp.ModelBo.no_of_installments() == 0) {
//                        coreWebApp.toastmsg('error','Loan Generation Error','No of Installments not entered', false);
//                        xhr.abort();
//                } else if(coreWebApp.ModelBo.installment_principal() <= 0) {
//                        coreWebApp.toastmsg('error','Loan Generation Error','Invalid Installment Principal', false);
//                        xhr.abort();
//                } else if(coreWebApp.ModelBo.installment_interest() <= 0) {
//                        coreWebApp.toastmsg('error','Loan Generation Error','Invalid Installment Interest', false);
//                        xhr.abort();
//                } else { coreWebApp.startloading();}
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.loan_tran.removeAll();
                    var tot_loan_principal = 0;
                    var tot_loan_interest = 0;
                    var rate = (coreWebApp.ModelBo.interest_percentage() / 100 / 12);
                    var emi = (coreWebApp.ModelBo.loan_principal() * rate * (Math.pow(1 + rate, coreWebApp.ModelBo.no_of_installments()) / (Math.pow(1 + rate, coreWebApp.ModelBo.no_of_installments()) - 1))).toFixed(2);
                    
                    
                    for (var p = 0; p < jsonResult['loan_tran'].length; ++p)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('loan_tran', coreWebApp.ModelBo);
                        r1.sl_no(jsonResult['loan_tran'][p]['sl_no']);
                        r1.employee_id(coreWebApp.ModelBo.employee_id());
                        r1.installment_date(jsonResult['loan_tran'][p]['install_date']);
                        if(p==0){
                            r1.loan_principal_amt(coreWebApp.ModelBo.loan_principal());
                            coreWebApp.ModelBo.loan_recovery_from(jsonResult['loan_tran'][p]['install_date']);
                        }
                        else{
                            r1.loan_principal_amt(coreWebApp.ModelBo.loan_tran()[p-1]['cl_balance']());
                        }
                        r1.installment_interest((r1.loan_principal_amt() * rate).toFixed(2));
                        
                        r1.os_amt((parseFloat(r1.loan_principal_amt()) + parseFloat(r1.installment_interest())).toFixed(2));
                        r1.installment_principal((parseFloat(emi) - parseFloat(r1.installment_interest())).toFixed(2));
                        r1.installment(parseFloat(emi).toFixed(2));
                        r1.cl_balance((parseFloat(r1.loan_principal_amt()) + parseFloat(r1.installment_interest())) - parseFloat(emi)) ;
                        tot_loan_principal = parseFloat(tot_loan_principal) + parseFloat(r1.installment_principal());
                        tot_loan_interest = parseFloat(tot_loan_interest) + parseFloat(r1.installment_interest());
                    }
                    var len = coreWebApp.ModelBo.loan_tran().length;
                    console.log(len);

                    debugger;
                    var diff_principal = (parseFloat(coreWebApp.ModelBo.loan_principal()) - parseFloat(tot_loan_principal)).toFixed(2);
                    if (diff_principal != 0) {
                        coreWebApp.ModelBo.loan_tran()[len - 1]['installment_principal'](parseFloat(coreWebApp.ModelBo.loan_tran()[len - 1]['installment_principal']()) + parseFloat(diff_principal));                        
                        coreWebApp.ModelBo.loan_tran()[len - 1]['cl_balance']((parseFloat(coreWebApp.ModelBo.loan_tran()[len - 1]['loan_principal_amt']()) - parseFloat(emi) - parseFloat(diff_principal) + parseFloat(coreWebApp.ModelBo.loan_tran()[len - 1]['installment_interest']())) ) ;
                    }
//
//                    var diff_interest = (parseFloat(coreWebApp.ModelBo.loan_interest()) - parseFloat(tot_loan_interest)).toFixed(2);
//                    if (diff_interest != 0) {
//                        coreWebApp.ModelBo.loan_tran()[len - 1]['installment_interest'](parseFloat(coreWebApp.ModelBo.installment_interest()) + parseFloat(diff_interest));
//                    }
                    coreWebApp.ModelBo.installment_interest(parseFloat(tot_loan_interest));
                    coreWebApp.ModelBo.loan_interest(parseFloat(tot_loan_interest));
                    coreWebApp.ModelBo.installment_principal(parseFloat(tot_loan_principal) + parseFloat(diff_principal));
                    coreWebApp.ModelBo.total_recovery((parseFloat(coreWebApp.ModelBo.loan_principal()) + parseFloat(coreWebApp.ModelBo.loan_interest())).toFixed(2)); 
                    coreWebApp.ModelBo.loan_tran.valueHasMutated();
                }
            }
        });
    }

    core_loan.calc_installment = calc_installment;

    function loan_tran_detail_before_delete(pr, prop, rw) {
        console.log('deleting.....' + prop);
        console.log(rw.payroll_id());
        if (rw.loan_repaid() == true) {
            return false;
        }
        return true;
    }
    core_loan.loan_tran_detail_before_delete = loan_tran_detail_before_delete;

}(window.core_loan));


