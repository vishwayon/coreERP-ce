// Declare core_gratuity Namespace

window.core_gratuity = {};

(function (core_gratuity) {
    
    function gratuity_afterload() {  
    }
    
    core_gratuity.gratuity_afterload = gratuity_afterload;
       
    
    function calculate_gratuity() {
        debugger;
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fcalculategratuity',
            type: 'GET',
            data: {'employeeId':coreWebApp.ModelBo.employee_id(),'gratuityFromDate': coreWebApp.ModelBo.gratuity_from_date(),'gratuityToDate':coreWebApp.ModelBo.gratuity_to_date()},
            beforeSend:function(xhr, opts){
                if(coreWebApp.ModelBo.employee_id() == -1) {
                        coreWebApp.toastmsg('error','Gratuity calculate Error','Employee not selected', false);
                        xhr.abort();
                } else 
                if(coreWebApp.ModelBo.gratuity_from_date() > coreWebApp.ModelBo.gratuity_to_date()){
                    coreWebApp.toastmsg('error','Gratuity calculate Error','Gratuity To date should be greater than Gratuity From date.',false);
                    xhr.abort();  
                }
                else{
                    coreWebApp.startloading();}},
            complete:function(){ coreWebApp.stoploading();},
            success: function (resultdata) {
                debugger;
                var jsonResult = $.parseJSON(resultdata);
                 
                if(jsonResult['status'] === 'ok'){
                    
                    coreWebApp.ModelBo.two_years_wages_amt(jsonResult['two_yrs_wages_amt']);
                    
                    coreWebApp.ModelBo.gratuity_tran.removeAll();
                    
                    for(var p = 0; p < jsonResult['gratuity_tran'].length; ++p)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('gratuity_tran',coreWebApp.ModelBo);
                        r1.sl_no(p+1);
                        r1.slab_from_date(jsonResult['gratuity_tran'][p]['slab_from']);
                        r1.slab_to_date(jsonResult['gratuity_tran'][p]['slab_to']);
                        r1.slab_days(jsonResult['gratuity_tran'][p]['slab_days']);
                        r1.gratuity_days(jsonResult['gratuity_tran'][p]['gratuity_days']);
                        r1.amount(jsonResult['gratuity_tran'][p]['amount']);
                        r1.unpaid_days(jsonResult['gratuity_tran'][p]['unpaid_days']);
                    }
                    
                    coreWebApp.ModelBo.gratuity_tran.valueHasMutated();
//                  applysmartcontrols();
                    
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
            }
        });
    }
    
    core_gratuity.calculate_gratuity = calculate_gratuity;
    
}(window.core_gratuity));
    

