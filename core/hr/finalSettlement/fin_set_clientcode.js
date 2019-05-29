// Declare core_fin_set Namespace

window.core_fin_set = {};

(function (core_fin_set) {
    
    function enable_noticepay(dataItem) { 
        if(typeof coreWebApp.ModelBo.en_resign_type()=='undefined')return;
        if(coreWebApp.ModelBo.en_resign_type() == 1){
            dataItem.notice_pay(0);
            debugger;
            if (coreWebApp.ModelBo.old_resign_type() != coreWebApp.ModelBo.en_resign_type()){
                coreWebApp.ModelBo.fin_set_payroll_tran.removeAll();
                coreWebApp.ModelBo.fin_set_gratuity_tran.removeAll();
            }
            return false;          
        }
        else {
            return true;
        }                
    };
    
    core_fin_set.enable_noticepay = enable_noticepay;    
    
    function noticepay_changed(dataItem) 
    {
       generate_fin_set();
    };
    
    core_fin_set.noticepay_changed = noticepay_changed; 
    
    function fin_set_date_changed(dataItem) 
    {
       generate_fin_set();
    };
    
    core_fin_set.fin_set_date_changed = fin_set_date_changed;  
     
    function generate_fin_set() {   
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fcalcfinalsettlement',
            type: 'GET',
            data: {'finsetFromDate': coreWebApp.ModelBo.fin_set_from_date(),'finsetToDate':coreWebApp.ModelBo.fin_set_to_date(),'employeeId':coreWebApp.ModelBo.employee_id(), 'noticePay':coreWebApp.ModelBo.notice_pay() },
            beforeSend:function(xhr, opts){
                if(coreWebApp.ModelBo.employee_id() == -1) {
                        coreWebApp.toastmsg('error','Calculate Final Settlement Error','Employee not selected', false);
                        xhr.abort();
                } else 
                if(coreWebApp.ModelBo.fin_set_to_date() == null){
                    coreWebApp.toastmsg('error','Calculate Final Settlement Error','Invalid Final Settlement To date',false);
                    xhr.abort();  
                }    
                if(coreWebApp.ModelBo.fin_set_from_date() > coreWebApp.ModelBo.fin_set_to_date()){
                    coreWebApp.toastmsg('error','Calculate Final Settlement Error','Final Settlement To date should be greater than Final Settlement From date',false);
                    xhr.abort();  
                }
                else{
                    coreWebApp.startloading();}},
            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                debugger;
                if(jsonResult['status'] === 'ok'){             
                    coreWebApp.ModelBo.fin_set_payroll_tran.removeAll();
                    coreWebApp.ModelBo.fin_set_gratuity_tran.removeAll();  
                    var slno=0;
                    for(var p = 0; p < jsonResult['fin_set_payroll_tran'].length; p++)
                    {                       
                        var r1 = coreWebApp.ModelBo.addNewRow('fin_set_payroll_tran',coreWebApp.ModelBo);
                        r1.employee_id(coreWebApp.ModelBo.employee_id());
                        r1.pay_days(jsonResult['fin_set_payroll_tran'][p]['pay_days']);
                        r1.no_pay_days(jsonResult['fin_set_payroll_tran'][p]['no_pay_days']);
                        r1.half_pay_days(jsonResult['fin_set_payroll_tran'][p]['half_pay_days']);
                        r1.tot_ot_hr(jsonResult['fin_set_payroll_tran'][p]['tot_ot_hr']);
                        r1.tot_ot_holiday_hr(jsonResult['fin_set_payroll_tran'][p]['tot_ot_holiday_hr']);
                        r1.tot_ot_special_hr(jsonResult['fin_set_payroll_tran'][p]['tot_ot_special_hr']);
                        r1.tot_ot_amt(jsonResult['fin_set_payroll_tran'][p]['tot_ot_amt']);
                        r1.tot_ot_holiday_amt(jsonResult['fin_set_payroll_tran'][p]['tot_ot_holiday_amt']);
                        r1.tot_ot_special_amt(jsonResult['fin_set_payroll_tran'][p]['tot_ot_special_amt']);
                        r1.tot_overtime_amt(jsonResult['fin_set_payroll_tran'][p]['tot_overtime_amt']);
                        r1.tot_emolument_amt(jsonResult['fin_set_payroll_tran'][p]['tot_emolument_amt']);
                        r1.tot_deduction_amt(jsonResult['fin_set_payroll_tran'][p]['tot_deduction_amt']);
                        slno=0;
                        for(var t = 0; t < jsonResult['fin_set_payroll_tran_detail'].length; t++) {                            
                            if(jsonResult['fin_set_payroll_tran'][p]['employee_id'] == jsonResult['fin_set_payroll_tran_detail'][t]['employee_id']){                               
                                var newItem = coreWebApp.ModelBo.addNewRow('fin_set_payroll_tran_detail',coreWebApp.ModelBo.fin_set_payroll_tran()[p]);
                                slno = slno + 1;
                                newItem.sl_no(slno);
                                newItem.employee_id(jsonResult['fin_set_payroll_tran_detail'][t]['employee_id']);  
                                newItem.employee_fullname(jsonResult['fin_set_payroll_tran_detail'][t]['employee_fullname']);
                                newItem.payhead_id(jsonResult['fin_set_payroll_tran_detail'][t]['payhead_id']);
                                newItem.payhead(jsonResult['fin_set_payroll_tran_detail'][t]['payhead']);
                                newItem.payhead_type(jsonResult['fin_set_payroll_tran_detail'][t]['payhead_type']);                       
                                newItem.emolument_amt(jsonResult['fin_set_payroll_tran_detail'][t]['emolument_amt']);
                                newItem.deduction_amt(jsonResult['fin_set_payroll_tran_detail'][t]['deduction_amt']);
                            }                            
                        }  
                        var g1 = coreWebApp.ModelBo.addNewRow('fin_set_gratuity_tran',coreWebApp.ModelBo);
                        g1.employee_id(coreWebApp.ModelBo.employee_id());
                        g1.gratuity_from_date(jsonResult['fin_set_payroll_tran'][p]['gratuity_from_date']);
                        g1.gratuity_to_date(jsonResult['fin_set_payroll_tran'][p]['gratuity_to_date']);
                        g1.gratuity_days(jsonResult['fin_set_payroll_tran'][p]['gratuity_days']);
                        g1.gratuity_amt(jsonResult['fin_set_payroll_tran'][p]['gratuity_amt']);
                        g1.gratuity_already_paid(jsonResult['fin_set_payroll_tran'][p]['gratuity_already_paid']);
                        g1.reducible_amt(jsonResult['fin_set_payroll_tran'][p]['reducible_amt']);
                        var rowno=0;
                        for(var gr = 0; gr < jsonResult['fin_set_payroll_tran_gratuity_detail'].length; gr++) {                            
                            var gratuityItem = coreWebApp.ModelBo.addNewRow('fin_set_gratuity_tran_detail',coreWebApp.ModelBo.fin_set_gratuity_tran()[rowno]);
                            gratuityItem.sl_no(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['sl_no']);
                            gratuityItem.slab_from_date(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['slab_from_date']);  
                            gratuityItem.slab_to_date(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['slab_to_date']);
                            gratuityItem.slab_days(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['slab_days']);
                            gratuityItem.gratuity_days(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['gratuity_days']);
                            gratuityItem.gratuity_amt(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['gratuity_amt']);                       
                            gratuityItem.unpaid_days(jsonResult['fin_set_payroll_tran_gratuity_detail'][gr]['unpaid_days']);                                                        
                        } 
                    }
                    coreWebApp.ModelBo.fin_set_payroll_tran.valueHasMutated();
                    coreWebApp.ModelBo.fin_set_gratuity_tran.valueHasMutated();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
            }
        });
    }
    
    core_fin_set.generate_fin_set = generate_fin_set;   
    
    
}(window.core_fin_set));