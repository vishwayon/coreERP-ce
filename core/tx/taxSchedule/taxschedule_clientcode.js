// Declare core_taxschedule Namespace
window.core_taxschedule = {};
(function (core_taxschedule) {      
     function control_enable(dataItem) { 
        if(typeof dataItem.en_tax_type=='undefined')return;
        if(dataItem.en_tax_type() == 0){
            return true;
        }
        else {
            dataItem.tax_perc(0);
            dataItem.tax_on_perc(100);
            dataItem.min_tax_amt(0);
            dataItem.tax_on_min_amt(0);
            dataItem.max_tax_amt(0);
            dataItem.tax_on_max_amt(0);
            return false;            
        }
    };
     
    core_taxschedule.control_enable=control_enable;
    
    function TaxDetailRemove(){        
        var cnt=coreWebApp.ModelBo.tax_detail_tran().length;
        for(var d=cnt; d >= 0; d--){                        
            var rl = coreWebApp.ModelBo.tax_detail_tran;
            rl.remove(coreWebApp.ModelBo.tax_detail_tran()[d-1]);
            break;
        }
    }
    
    core_taxschedule.TaxDetailRemove=TaxDetailRemove;
   
    function TaxScheduleNew(){
        var tax_base = ko.observableArray(); 
        for(var d=0; d < coreWebApp.ModelBo.tax_detail_tran().length; d++){
            var r = coreWebApp.ModelBo.tax_detail_tran()[d];
            var r1 = { tax_detail_id: r.tax_detail_id(), step_id: r.step_id(), description: r.description(), is_select: false};   
            tax_base.push(r1);
        } 
        var rw = { parent_tax_details: '', description: '', step_id: -1, account_id: -1, en_tax_type: -1, en_round_type:-1, tax_perc: 0, tax_on_perc: 100,
                min_tax_amt: 0, tax_on_min_amt: 0, max_tax_amt: 0, tax_on_max_amt: 0, include_base_in_calculation: false, tax_detail_temp: tax_base};
            
        coreWebApp.showAlloc('core/tx','/taxSchedule/TaxScheduleNew','core_taxschedule.tax_detail_alloc_init','core_taxschedule.tax_detail_alloc_update','core_taxschedule.cancelAllocUpdate',rw);
    }
    
    core_taxschedule.TaxScheduleNew=TaxScheduleNew;
   
    // function to set default values for Tax Detail
    function tax_detail_alloc_init(){
    }  
    
    core_taxschedule.tax_detail_alloc_init = tax_detail_alloc_init;
    

    //function to update tax detail pop up fields to tax_detail_tran
    function tax_detail_alloc_update(ctr,dataItem){ 
        if(dataItem[0]['description']() == ''){
            return 'Tax Description cannot be left blank.';
        }
        if(dataItem[0]['tax_on_perc']() >100){
            return 'Tax On Percentage cannot be greater than 100.';
        }
        if(dataItem[0]['account_id']() ==-1){
            return 'Please select Tax Account.';
        }
        if(dataItem[0]['en_tax_type']() ==-1){
            return 'Please select Caluculation Type.';
        }
        if(dataItem[0]['en_round_type']() ==-1){
            return 'Please select Round Of.';
        }
        else{         
            var ptd='';

            if(dataItem[0]['include_base_in_calculation']() == true){
                ptd+='0';
            }

            for(var d=0; d < dataItem[0]['tax_detail_temp']().length; d++){
                if(dataItem[0]['tax_detail_temp']()[d]['is_select']()){
                    if(ptd.length > 0){
                        ptd+= ',';  
                    }
                    ptd+= dataItem[0]['tax_detail_temp']()[d]['tax_detail_id']();                        
                }
            }
            
            if(dataItem[0]['step_id']() == -1){
                var r = coreWebApp.ModelBo.addNewRow('tax_detail_tran', coreWebApp.ModelBo);
                r.parent_tax_details(ptd);
                r.description(dataItem[0]['description']());
                r.account_id(dataItem[0]['account_id']());
                r.en_tax_type(dataItem[0]['en_tax_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.tax_perc(dataItem[0]['tax_perc']());
                r.tax_on_perc(dataItem[0]['tax_on_perc']());
                r.min_tax_amt(dataItem[0]['min_tax_amt']());
                r.tax_on_min_amt(dataItem[0]['tax_on_min_amt']());
                r.max_tax_amt(dataItem[0]['max_tax_amt']());
                r.tax_on_max_amt(dataItem[0]['tax_on_max_amt']());

                var count =coreWebApp.ModelBo.tax_detail_tran().length;
                r.step_id(count);
                r.tax_detail_id(-1 * (count));
                coreWebApp.ModelBo.tax_detail_tran.valueHasMutated();
                return 'OK';                
            }
            else{                
                for(var d=0; d < coreWebApp.ModelBo.tax_detail_tran().length; d++){
                    var r = coreWebApp.ModelBo.tax_detail_tran()[d];

                    if(r.step_id() == dataItem[0]['step_id']()){
                        r.parent_tax_details(ptd);
                        r.description(dataItem[0]['description']());
                        r.account_id(dataItem[0]['account_id']());
                        r.en_tax_type(dataItem[0]['en_tax_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.tax_perc(dataItem[0]['tax_perc']());
                        r.tax_on_perc(dataItem[0]['tax_on_perc']());
                        r.min_tax_amt(dataItem[0]['min_tax_amt']());
                        r.tax_on_min_amt(dataItem[0]['tax_on_min_amt']());
                        r.max_tax_amt(dataItem[0]['max_tax_amt']());
                        r.tax_on_max_amt(dataItem[0]['tax_on_max_amt']());
                    }
                }
                return 'OK';                
            }
        }
    }
   
    core_taxschedule.tax_detail_alloc_update = tax_detail_alloc_update;
    
    function edit_method(pr,prop,rw){
        console.log('edit_method');
        if( typeof rw.tax_detail_temp == 'undefined'){
            rw.tax_detail_temp = ko.observableArray();             
        }
        rw.tax_detail_temp.removeAll();
        for(var d=0; d < coreWebApp.ModelBo.tax_detail_tran().length; d++){
            var r = coreWebApp.ModelBo.tax_detail_tran()[d];                        
            arr = new Array();
            arr=rw.parent_tax_details().split(",");
            if(r.step_id() < rw.step_id()){
                var r = coreWebApp.ModelBo.tax_detail_tran()[d];
                var is_select = false;
                
                for(var a=0; a < arr.length; a++){
                    if(parseInt(arr[a]) === parseInt(r.tax_detail_id())){
                        is_select = true;
                    }
                }
                
                var r1 = { tax_detail_id: r.tax_detail_id(), step_id: r.step_id(), description: r.description(), is_select: is_select};   
                rw.tax_detail_temp.push(r1);
            }
            if(arr.length > 0){
                if (parseInt(arr[0]) == 0){
                    rw.include_base_in_calculation(true);
                }
            }
        }        
        
        coreWebApp.showAlloc('core/tx','/taxSchedule/TaxScheduleNew','core_taxschedule.tax_detail_alloc_init','core_taxschedule.tax_detail_alloc_update','core_taxschedule.cancelAllocUpdate',rw);
    }
    
    core_taxschedule.edit_method=edit_method;   
    
    function cancelAllocUpdate() {
    }
    core_taxschedule.cancelAllocUpdate = cancelAllocUpdate;

    function acc_combo_filter(fltr) {
        fltr = ' account_type_id not in (1, 47, 46, 2, 45, 7, 12)'
        return fltr;
    }

    core_taxschedule.acc_combo_filter = acc_combo_filter;
    
}(window.core_taxschedule));