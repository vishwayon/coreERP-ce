// Declare core_ac Namespace
window.core_tdsreturn = {};

(function (core_tdsreturn) {
    
    function CalculateReturn() {
        $.ajax({
            url: '?r=core%2Ftds%2Fform%2Fcalculatereturn',
            type: 'GET',
            data: {'return_quarter': coreWebApp.ModelBo.return_quarter()},
            beforeSend:function(xhr, opts){         
                
                 if(coreWebApp.ModelBo.return_quarter()=='-1'){
                    coreWebApp.toastmsg('warning','Calculate Return Error','Select Return For to calculate return', false);
                    xhr.abort();  
                }
                else{
                coreWebApp.startloading();}},
            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                 
                if(jsonResult['status'] === 'ok'){
                    coreWebApp.ModelBo.tds_return_challan_tran.removeAll();
                    var found=false;
                    for(var p = 0; p < jsonResult['tds_return'].length; ++p)
                    {
                        found=false;
                        for(var q = 0; q < coreWebApp.ModelBo.tds_return_challan_tran().length; ++q)
                        {                            
                            if(jsonResult['tds_return'][p]['payment_id'] == coreWebApp.ModelBo.tds_return_challan_tran()[q]['payment_id']()){
                                found=true;
                                break;
                            }
                        }
                        if(!found){
                            var r = coreWebApp.ModelBo.addNewRow('tds_return_challan_tran',coreWebApp.ModelBo);                        
                            r.payment_id(jsonResult['tds_return'][p]['payment_id']);
                            r.payment_date(jsonResult['tds_return'][p]['payment_date']);
                            r.tds_total_amt(jsonResult['tds_return'][p]['tds_total_amt']);
                            r.interest_amt(jsonResult['tds_return'][p]['interest_amt']);
                            r.penalty_amt(jsonResult['tds_return'][p]['penalty_amt']);
                            r.tds_payment_amt(jsonResult['tds_return'][p]['tds_payment_amt']);
                            r.account_head(jsonResult['tds_return'][p]['account_head']);
                        }
                    }
                    for(var q = 0; q < coreWebApp.ModelBo.tds_return_challan_tran().length; ++q)
                    {  
                        for(var p = 0; p < jsonResult['tds_return'].length; ++p)                    
                        {                          
                            if(jsonResult['tds_return'][p]['payment_id'] == coreWebApp.ModelBo.tds_return_challan_tran()[q]['payment_id']()){        
                                var r1 = coreWebApp.ModelBo.addNewRow('bill_tds_tran',coreWebApp.ModelBo.tds_return_challan_tran()[q]);                        
                                r1.voucher_id(jsonResult['tds_return'][p]['bill_id']);                    
                                r1.supplier(jsonResult['tds_return'][p]['supplier']);                  
                                r1.doc_date(jsonResult['tds_return'][p]['bill_date']);                      
                                r1.bill_amt(jsonResult['tds_return'][p]['bill_amt']);                    
                                r1.tds_base_rate_amt(jsonResult['tds_return'][p]['tds_base_rate_amt']);                  
                                r1.tds_ecess_amt(jsonResult['tds_return'][p]['tds_ecess_amt']);             
                                r1.tds_surcharge_amt(jsonResult['tds_return'][p]['tds_surcharge_amt']);
                            }
                        }
                    }
                    coreWebApp.ModelBo.tds_return_challan_tran.valueHasMutated();
                    coreWebApp.ModelBo.bill_tds_tran.valueHasMutated();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',true);
            }
        });
    }    
    core_tdsreturn.CalculateReturn = CalculateReturn;
    
    
    function GenerateOutput() {
        $.ajax({
            url: '?r=core%2Ftds%2Fform%2Fgenerateoutput',
            type: 'GET',
            data: {'tds_return_id': coreWebApp.ModelBo.voucher_id()},
            beforeSend:function(xhr, opts){ 
                 if(coreWebApp.ModelBo.status() != 5){
                    coreWebApp.toastmsg('error','Generate Output Error','Post the TDS return to generate output', true);
                    xhr.abort();  
                }
                else{
                coreWebApp.startloading();}},
            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                 
                if(jsonResult['status'] === 'ok'){
//                    coreWebApp.ModelBo.tds_return_challan_tran.removeAll();
//                    var found=false;
//                    for(var p = 0; p < jsonResult['tds_return'].length; ++p)
//                    {
//                        found=false;
//                        for(var q = 0; q < coreWebApp.ModelBo.tds_return_challan_tran().length; ++q)
//                        {                            
//                            if(jsonResult['tds_return'][p]['payment_id'] == coreWebApp.ModelBo.tds_return_challan_tran()[q]['payment_id']()){
//                                found=true;
//                                break;
//                            }
//                        }
//                        if(!found){
//                            var r = coreWebApp.ModelBo.addNewRow('tds_return_challan_tran',coreWebApp.ModelBo);                        
//                            r.payment_id(jsonResult['tds_return'][p]['payment_id']);
//                            r.payment_date(jsonResult['tds_return'][p]['payment_date']);
//                            r.tds_total_amt(jsonResult['tds_return'][p]['tds_total_amt']);
//                            r.interest_amt(jsonResult['tds_return'][p]['interest_amt']);
//                            r.penalty_amt(jsonResult['tds_return'][p]['penalty_amt']);
//                            r.tds_payment_amt(jsonResult['tds_return'][p]['tds_payment_amt']);
//                            r.account_head(jsonResult['tds_return'][p]['account_head']);
//                        }
//                    }
//                    for(var q = 0; q < coreWebApp.ModelBo.tds_return_challan_tran().length; ++q)
//                    {  
//                        for(var p = 0; p < jsonResult['tds_return'].length; ++p)                    
//                        {                          
//                            if(jsonResult['tds_return'][p]['payment_id'] == coreWebApp.ModelBo.tds_return_challan_tran()[q]['payment_id']()){        
//                                var r1 = coreWebApp.ModelBo.addNewRow('bill_tds_tran',coreWebApp.ModelBo.tds_return_challan_tran()[q]);                        
//                                r1.voucher_id(jsonResult['tds_return'][p]['bill_id']);                    
//                                r1.supplier(jsonResult['tds_return'][p]['supplier']);                  
//                                r1.doc_date(jsonResult['tds_return'][p]['bill_date']);                      
//                                r1.bill_amt(jsonResult['tds_return'][p]['bill_amt']);                    
//                                r1.tds_base_rate_amt(jsonResult['tds_return'][p]['tds_base_rate_amt']);                  
//                                r1.tds_ecess_amt(jsonResult['tds_return'][p]['tds_ecess_amt']);             
//                                r1.tds_surcharge_amt(jsonResult['tds_return'][p]['tds_surcharge_amt']);
//                            }
//                        }
//                    }
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',true);
            }
        });
    }    
    core_tdsreturn.GenerateOutput = GenerateOutput;
    
}(window.core_tdsreturn));


