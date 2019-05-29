// Declare core_pr Namespace
window.core_epp = {};

(function (core_epp) {

    function payhead_new_combo_filter(fltr, datacontext) {
        fltr = " payhead_type= '" + datacontext.payhead_type() + "'";
        return fltr;
    }

    core_epp.payhead_new_combo_filter = payhead_new_combo_filter;
    
     function control_enable(dataItem) { 
        if(typeof dataItem.en_pay_type=='undefined')return;
        if(dataItem.en_pay_type() == 0){
            return true;
        }
        else {
            dataItem.pay_perc(0);
            dataItem.min_pay_amt(0);
            dataItem.pay_on_min_amt(0);
            dataItem.max_pay_amt(0);
            dataItem.pay_on_max_amt(0);
            return false;            
        }
    };
     
    core_epp.control_enable=control_enable;
   
     function amt_enable(dataItem) { 
        if(typeof dataItem.en_pay_type=='undefined')return;
        if(dataItem.en_pay_type() == 2){
            return true;
        }
        else {
            dataItem.amt(0);
            return false;            
        }
    };
     
    core_epp.amt_enable=amt_enable;
    
    function enable_ot_rate(dataItem) { 
        if(typeof dataItem.schedule_type=='undefined')return;
        if(dataItem.schedule_type()==1){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_epp.enable_ot_rate=enable_ot_rate 
    
    function enable_btn_pay_schedule(dataItem) {   
        if(coreWebApp.ModelBo.employee_payplan_id() == -1){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_epp.enable_btn_pay_schedule=enable_btn_pay_schedule 
    
    function enable_btn_new_remove(dataItem) {   
        if(coreWebApp.ModelBo.schedule_type() == 1){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_epp.enable_btn_new_remove=enable_btn_new_remove 
    
    function payplan_afterload() {
        $('#note1').parent().hide();
        is_payroll_generated();
    }
    
    core_epp.payplan_afterload = payplan_afterload;
        
    function is_payroll_generated(){ 
        $.ajax({
                url: '?r=core/hr/form/ispayrollgenerated',
                type: 'GET',
                data: {'employee_id': coreWebApp.ModelBo.employee_id(), 'employee_payplan_id': coreWebApp.ModelBo.employee_payplan_id(), 
                            'effective_from_date': coreWebApp.ModelBo.effective_from_date()},
                complete:function(){coreWebApp.stoploading();},
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if(jsonResult['status'] === 'ok'){
                        if(jsonResult['payroll_generated'] == true){
                            $('#cmdsave').prop( "disabled", true );
                            console.log(jsonResult['msg'] );
                            $('#note1').parent().show();
                        }
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
                }
            });
        return 'OK';
    }
    
    core_epp.is_payroll_generated=is_payroll_generated;
    
    function enable_effective_to_date(dataItem) { 
       if(typeof dataItem.is_effective_to_date=='undefined')return;
        if(dataItem.is_end_date() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_epp.enable_effective_to_date=enable_effective_to_date 
    
    function enable_effective_to_date(dataItem) { 
       if(typeof dataItem.is_effective_to_date=='undefined')return;
        if(dataItem.is_end_date() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_epp.enable_effective_to_date=enable_effective_to_date      
    
    // Methods for Pay Plan Details Starts
    function EppDetailRemove(){        
        var cnt=coreWebApp.ModelBo.epp_detail_tran().length;
        for(var d=cnt; d >= 0; d--){                        
            var rl = coreWebApp.ModelBo.epp_detail_tran;
            rl.remove(coreWebApp.ModelBo.epp_detail_tran()[d-1]);
            break;
        }
        
        console.log('EppDetailRemove');
    }
    
    core_epp.EppDetailRemove=EppDetailRemove;
    
    
// Methods to add/edit Emoluments starts
    function EppDetailEmoNew(){
        var parent_pay_sch_temp = ko.observableArray(); 
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        } 
        var rw = { parent_details: '', payhead_id: -1, payhead_type:'E', description: '', step_id: -1, en_pay_type: -1, en_round_type:-1, pay_perc: 0, pay_on_perc: 100,
                amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, epp_detail_temp: parent_pay_sch_temp};
            
        coreWebApp.showAlloc('core/hr', '/employeePayplan/EmployeePayplanDetailNew', 'core_epp.epp_detail_alloc_init', 'core_epp.epp_detail_emo_update', 'core_epp.cancelAllocUpdate',rw);
    }
    
    core_epp.EppDetailEmoNew=EppDetailEmoNew;       

    //function to update pay detail pop up fields to epp_detail_tran
    function epp_detail_emo_update(ctr,dataItem){ 
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }
        if(dataItem[0]['pay_on_perc']() >100){
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if(dataItem[0]['payhead_id']() ==-1){
            return 'Please select Pay Head.';
        }
        if(dataItem[0]['en_pay_type']() ==-1){
            return 'Please select Calculation Type.';
        }
        if(dataItem[0]['en_round_type']() ==-1){
            return 'Please select Round Of.';
        }
        else{         
            var ptd='';

            for(var d=0; d < dataItem[0]['epp_detail_temp']().length; d++){
                if(dataItem[0]['epp_detail_temp']()[d]['is_select']()){
                    if(ptd.length > 0){
                        ptd+= ',';  
                    }
                    ptd+= 'step:' + dataItem[0]['epp_detail_temp']()[d]['step_id']();                        
                }
            }

            if(dataItem[0]['step_id']() == -1){
                var r = coreWebApp.ModelBo.addNewRow('epp_detail_emo_tran', coreWebApp.ModelBo);

                r.parent_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']());
                
                var count =coreWebApp.ModelBo.epp_detail_emo_tran().length;
                r.step_id(count+1000);
                console.log('epp_detail_emo_update');
                
                coreWebApp.ModelBo.epp_detail_emo_tran.valueHasMutated();
                return 'OK';
            }
            else{
                for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
                    var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];

                    if(r.step_id() == dataItem[0]['step_id']()){
                        r.parent_details(ptd);                        
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }

                console.log('epp_detail_alloc_edit_update');
                return 'OK';
                
            }
        }       
    }
   
    core_epp.epp_detail_emo_update = epp_detail_emo_update;     
    
    // Remove row from Emolument tran
    function EppDetailEmoRemove(){        
        var rowused = false;
        var cnt = coreWebApp.ModelBo.epp_detail_emo_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var a = 0; a < coreWebApp.ModelBo.epp_detail_emo_tran().length; a++) {
                var r = coreWebApp.ModelBo.epp_detail_emo_tran()[a];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.epp_detail_ded_tran().length; b++) {
                var r = coreWebApp.ModelBo.epp_detail_ded_tran()[b];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.epp_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.epp_detail_cc_tran()[b];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.epp_detail_emo_tran;
                rl.remove(coreWebApp.ModelBo.epp_detail_emo_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }
        console.log('EppDetailEmoRemove');
    }
    
    core_epp.EppDetailEmoRemove=EppDetailEmoRemove;
    
    function emo_edit_method(pr,prop,rw){
        console.log('emo_edit_method');
        if(coreWebApp.ModelBo.schedule_type() ==0){            
            coreWebApp.toastmsg('warning','Edit Employe Payplan Detail','Cannot edit Employe Payplan Detail for Schedule Type - Pay Schedule',false);
        }
        else{
            if( typeof rw.epp_detail_temp == 'undefined'){
                rw.epp_detail_temp = ko.observableArray();             
            }
            rw.epp_detail_temp.removeAll();
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            } 
            coreWebApp.showAlloc('core/hr','/employeePayplan/EmployeePayplanDetailNew','core_epp.epp_detail_alloc_init','core_epp.epp_detail_emo_update','core_epp.cancelAllocUpdate',rw);
        }
    }
    
    core_epp.emo_edit_method=emo_edit_method;   
   
// Methods to add/edit Emoluments Ends

 
// Methods to add/edit Deductions starts
    function EppDetailDedNew(){
        var parent_pay_sch_temp = ko.observableArray(); 
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        }  
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_ded_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        } 
        var rw = { parent_details: '', payhead_id: -1, payhead_type:'D', description: '', step_id: -1, en_pay_type: -1, en_round_type:-1, pay_perc: 0, pay_on_perc: 100,
                amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, epp_detail_temp: parent_pay_sch_temp};
            
        coreWebApp.showAlloc('core/hr', '/employeePayplan/EmployeePayplanDetailNew', 'core_epp.epp_detail_alloc_init', 'core_epp.epp_detail_ded_update', 'core_epp.cancelAllocUpdate',rw);
    }
    
    core_epp.EppDetailDedNew=EppDetailDedNew;       

    //function to update pay detail pop up fields to epp_detail_tran
    function epp_detail_ded_update(ctr,dataItem){ 
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }
        if(dataItem[0]['pay_on_perc']() >100){
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if(dataItem[0]['payhead_id']() ==-1){
            return 'Please select Pay Head.';
        }
        if(dataItem[0]['en_pay_type']() ==-1){
            return 'Please select Calculation Type.';
        }
        if(dataItem[0]['en_round_type']() ==-1){
            return 'Please select Round Of.';
        }
        else{         
            var ptd='';

            for(var d=0; d < dataItem[0]['epp_detail_temp']().length; d++){
                if(dataItem[0]['epp_detail_temp']()[d]['is_select']()){
                    if(ptd.length > 0){
                        ptd+= ',';  
                    }
                    ptd+= 'step:' + dataItem[0]['epp_detail_temp']()[d]['step_id']();                        
                }
            }

            if(dataItem[0]['step_id']() == -1){
                var r = coreWebApp.ModelBo.addNewRow('epp_detail_ded_tran', coreWebApp.ModelBo);

                r.parent_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']());
                
                var count =coreWebApp.ModelBo.epp_detail_ded_tran().length;
                r.step_id(count+2000);
                console.log('epp_detail_ded_update');
                
                coreWebApp.ModelBo.epp_detail_ded_tran.valueHasMutated();
                return 'OK';
            }
            else{
                for(var d=0; d < coreWebApp.ModelBo.epp_detail_ded_tran().length; d++){
                    var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];

                    if(r.step_id() == dataItem[0]['step_id']()){
                        r.parent_details(ptd);                        
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }

                console.log('epp_detail_ded_update');
                return 'OK';
                
            }
        }       
    }
   
    core_epp.epp_detail_ded_update = epp_detail_ded_update;     
    
    // Remove row from Deductions tran
    function EppDetailDedRemove(){        
        var rowused = false;
        var cnt = coreWebApp.ModelBo.epp_detail_ded_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var b = 0; b < coreWebApp.ModelBo.epp_detail_ded_tran().length; b++) {
                var r = coreWebApp.ModelBo.epp_detail_ded_tran()[b];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_ded_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.epp_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.epp_detail_cc_tran()[b];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_ded_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.epp_detail_ded_tran;
                rl.remove(coreWebApp.ModelBo.epp_detail_ded_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }
        console.log('EppDetailDedRemove');
    }
    
    core_epp.EppDetailDedRemove=EppDetailDedRemove;
    
    function ded_edit_method(pr,prop,rw){
        console.log('ded_edit_method');
        if(coreWebApp.ModelBo.schedule_type() ==0){            
            coreWebApp.toastmsg('warning','Edit Employe Payplan Detail','Cannot edit Employe Payplan Detail for Schedule Type - Pay Schedule',false);
        }
        else{
            if( typeof rw.epp_detail_temp == 'undefined'){
                rw.epp_detail_temp = ko.observableArray();             
            }
            rw.epp_detail_temp.removeAll();
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            }
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_ded_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            }  
            coreWebApp.showAlloc('core/hr','/employeePayplan/EmployeePayplanDetailNew','core_epp.epp_detail_alloc_init','core_epp.epp_detail_ded_update','core_epp.cancelAllocUpdate',rw);
        }
    }
    
    core_epp.ded_edit_method=ded_edit_method;   
   
// Methods to add/edit Deductions Ends

 
// Methods to add/edit Company Contributions starts
    function EppDetailCcNew(){
        var parent_pay_sch_temp = ko.observableArray(); 
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        }  
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_ded_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        } 
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_cc_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_cc_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        } 
        var rw = { parent_details: '', payhead_id: -1, payhead_type:'C', description: '', step_id: -1, en_pay_type: -1, en_round_type:-1, pay_perc: 0, pay_on_perc: 100,
                amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, epp_detail_temp: parent_pay_sch_temp};
            
        coreWebApp.showAlloc('core/hr', '/employeePayplan/EmployeePayplanDetailNew', 'core_epp.epp_detail_alloc_init', 'core_epp.epp_detail_cc_update', 'core_epp.cancelAllocUpdate',rw);
    }
    
    core_epp.EppDetailCcNew=EppDetailCcNew;       

    //function to update pay detail pop up fields to epp_detail_tran
    function epp_detail_cc_update(ctr,dataItem){ 
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }
        if(dataItem[0]['pay_on_perc']() >100){
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if(dataItem[0]['payhead_id']() ==-1){
            return 'Please select Pay Head.';
        }
        if(dataItem[0]['en_pay_type']() ==-1){
            return 'Please select Calculation Type.';
        }
        if(dataItem[0]['en_round_type']() ==-1){
            return 'Please select Round Of.';
        }
        else{         
            var ptd='';

            for(var d=0; d < dataItem[0]['epp_detail_temp']().length; d++){
                if(dataItem[0]['epp_detail_temp']()[d]['is_select']()){
                    if(ptd.length > 0){
                        ptd+= ',';  
                    }
                    ptd+= 'step:' + dataItem[0]['epp_detail_temp']()[d]['step_id']();                        
                }
            }

            if(dataItem[0]['step_id']() == -1){
                var r = coreWebApp.ModelBo.addNewRow('epp_detail_cc_tran', coreWebApp.ModelBo);

                r.parent_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']());
                
                var count =coreWebApp.ModelBo.epp_detail_cc_tran().length;
                r.step_id(count+3000);
                console.log('epp_detail_cc_update');
                
                coreWebApp.ModelBo.epp_detail_cc_tran.valueHasMutated();
                return 'OK';
            }
            else{
                for(var d=0; d < coreWebApp.ModelBo.epp_detail_cc_tran().length; d++){
                    var r = coreWebApp.ModelBo.epp_detail_cc_tran()[d];

                    if(r.step_id() == dataItem[0]['step_id']()){
                        r.parent_details(ptd);                        
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }

                console.log('epp_detail_cc_update');
                return 'OK';
                
            }
        }       
    }
   
    core_epp.epp_detail_cc_update = epp_detail_cc_update;     
    
    // Remove row from Company Contributions tran
    function EppDetailCcRemove(){        
        var rowused = false;
        var cnt = coreWebApp.ModelBo.epp_detail_cc_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var b = 0; b < coreWebApp.ModelBo.epp_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.epp_detail_cc_tran()[b];
                if (r.parent_details().includes('step:' + coreWebApp.ModelBo.epp_detail_cc_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.epp_detail_cc_tran;
                rl.remove(coreWebApp.ModelBo.epp_detail_cc_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }
        console.log('EppDetailCcRemove');
    }
    
    core_epp.EppDetailCcRemove=EppDetailCcRemove;
    
    function cc_edit_method(pr,prop,rw){
        console.log('cc_edit_method');
        if(coreWebApp.ModelBo.schedule_type() ==0){            
            coreWebApp.toastmsg('warning','Edit Employe Payplan Detail','Cannot edit Employe Payplan Detail for Schedule Type - Pay Schedule',false);
        }
        else{
            if( typeof rw.epp_detail_temp == 'undefined'){
                rw.epp_detail_temp = ko.observableArray();             
            }
            rw.epp_detail_temp.removeAll();
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_emo_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_emo_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            }
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_ded_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_ded_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            }  
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_cc_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_cc_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_cc_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            }  
            coreWebApp.showAlloc('core/hr','/employeePayplan/EmployeePayplanDetailNew','core_epp.epp_detail_alloc_init','core_epp.epp_detail_cc_update','core_epp.cancelAllocUpdate',rw);
        }
    }
    
    core_epp.cc_edit_method=cc_edit_method;   
   
// Methods to add/edit Company Contributions Ends
   
    function EppDetailNew(){
        var parent_pay_sch_temp = ko.observableArray(); 
        for(var d=0; d < coreWebApp.ModelBo.epp_detail_tran().length; d++){
            var r = coreWebApp.ModelBo.epp_detail_tran()[d];
            var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};   
            parent_pay_sch_temp.push(r1);
        } 
        var rw = { parent_details: '', payhead_id: -1, description: '', step_id: -1, en_pay_type: -1, en_round_type:-1, pay_perc: 0, pay_on_perc: 100,
                amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, epp_detail_temp: parent_pay_sch_temp};
            
        coreWebApp.showAlloc('core/hr', '/employeePayplan/EmployeePayplanDetailNew', 'core_epp.epp_detail_alloc_init', 'core_epp.epp_detail_alloc_update', 'core_epp.cancelAllocUpdate',rw);
    }
    
    core_epp.EppDetailNew=EppDetailNew;
   
    // function to set default values for Pay Detail
    function epp_detail_alloc_init(){
    }   
    
    core_epp.epp_detail_alloc_init = epp_detail_alloc_init;    

    //function to update pay detail pop up fields to epp_detail_tran
    function epp_detail_alloc_update(ctr,dataItem){ 
        if(dataItem[0]['pay_on_perc']() >100){
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if(dataItem[0]['payhead_id']() ==-1){
            return 'Please select Pay Head.';
        }
        if(dataItem[0]['en_pay_type']() ==-1){
            return 'Please select Calculation Type.';
        }
        if(dataItem[0]['en_round_type']() ==-1){
            return 'Please select Round Of.';
        }
        else{         
            var ptd='';

            for(var d=0; d < dataItem[0]['epp_detail_temp']().length; d++){
                if(dataItem[0]['epp_detail_temp']()[d]['is_select']()){
                    if(ptd.length > 0){
                        ptd+= ',';  
                    }
                    ptd+= 'step:' + dataItem[0]['epp_detail_temp']()[d]['step_id']();                        
                }
            }

            if(dataItem[0]['step_id']() == -1){
                var r = coreWebApp.ModelBo.addNewRow('epp_detail_tran', coreWebApp.ModelBo);

                r.parent_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']());
                var count =coreWebApp.ModelBo.epp_detail_tran().length;
                r.step_id(count);
//                r.employee_payplan_detail_id(-1 * (step_id));
                console.log('epp_detail_alloc_update');
                
                coreWebApp.ModelBo.epp_detail_tran.valueHasMutated();
                return 'OK';
            }
            else{
                for(var d=0; d < coreWebApp.ModelBo.epp_detail_tran().length; d++){
                    var r = coreWebApp.ModelBo.epp_detail_tran()[d];

                    if(r.step_id() == dataItem[0]['step_id']()){
                        r.parent_details(ptd);                        
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }

                console.log('epp_detail_alloc_edit_update');
                return 'OK';
                
            }
        }       
    }
   
    core_epp.epp_detail_alloc_update = epp_detail_alloc_update;
    
    function cancelAllocUpdate() {
    }
    core_epp.cancelAllocUpdate = cancelAllocUpdate;
    
    function edit_method(pr,prop,rw){
        console.log('edit_method');
        if(coreWebApp.ModelBo.schedule_type() ==0){            
            coreWebApp.toastmsg('warning','Edit Employe Payplan Detail','Cannot edit Employe Payplan Detail for Schedule Type - Pay Schedule',false);
        }
        else{
            if( typeof rw.epp_detail_temp == 'undefined'){
                rw.epp_detail_temp = ko.observableArray();             
            }
            rw.epp_detail_temp.removeAll();
            for(var d=0; d < coreWebApp.ModelBo.epp_detail_tran().length; d++){
                var r = coreWebApp.ModelBo.epp_detail_tran()[d];                        
                arr = new Array();
                arr=rw.parent_details().split(",");
                if(r.step_id() < rw.step_id()){
                    var r = coreWebApp.ModelBo.epp_detail_tran()[d];
                    var is_select = false;

                    for(var a=0; a < arr.length; a++){
                        if(arr[a] === 'step:'+r.step_id()){
                            is_select = true;
                        }
                    }

                    var r1 = { employee_payplan_detail_id: r.employee_payplan_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};   
                    rw.epp_detail_temp.push(r1);
                }
            } 
            coreWebApp.showAlloc('core/hr','/employeePayplan/EmployeePayplanDetailNew','core_epp.epp_detail_alloc_init','core_epp.epp_detail_alloc_update','core_epp.cancelAllocUpdate',rw);
        }
    }
    
    core_epp.edit_method=edit_method;   
     
    // Methods for Pay Plan Details Ends
    
    // Methods used to copy Pay Schedule Starts
    function SelectPaySchedule(){
        coreWebApp.showAlloc('core/hr','/employeePayplan/SelectPaySchedule','core_epp.pay_schedule_init','core_epp.pay_schedule_update', 'core_epp.cancelAllocUpdate');
    }
    core_epp.SelectPaySchedule=SelectPaySchedule;
        
    function pay_schedule_init(){
    }
    
    core_epp.pay_schedule_init = pay_schedule_init;

    function cancelAllocUpdate() {
    }
    core_epp.cancelAllocUpdate = cancelAllocUpdate;
    //function to update Schedule Detail
    function pay_schedule_update(){
        if(coreWebApp.ModelBo.pay_schedule_id() == -1 || coreWebApp.ModelBo.pay_schedule_id() == null){            
            return 'Pay Schedule Details','Select Pay Schedule Schedule to get details';
        }
        
        $.ajax({
            url: '?r=core/hr/form/getpayscheduledetails',
            type: 'GET',
            data: {'pay_schedule_id': coreWebApp.ModelBo.pay_schedule_id()},
            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if(jsonResult['status'] === 'ok'){
                    //remove all Pay Schedule Details 
                    coreWebApp.ModelBo.epp_detail_emo_tran.removeAll();
                    coreWebApp.ModelBo.epp_detail_ded_tran.removeAll();
                    coreWebApp.ModelBo.epp_detail_cc_tran.removeAll();
                    //update Pay Schedule Detail
                    for(var p = 0; p < jsonResult['pay_detail'].length; ++p)
                    {             
                        if(jsonResult['pay_detail'][p]['payhead_type'] == 'E'){
                            var r = coreWebApp.ModelBo.addNewRow('epp_detail_emo_tran',coreWebApp.ModelBo);                              
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);                          
                            r.parent_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);  
                        }
                        else if(jsonResult['pay_detail'][p]['payhead_type'] == 'D'){
                            var r = coreWebApp.ModelBo.addNewRow('epp_detail_ded_tran',coreWebApp.ModelBo);                              
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);                          
                            r.parent_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);   
                        }
                        else if(jsonResult['pay_detail'][p]['payhead_type'] == 'C'){                        
                            var r = coreWebApp.ModelBo.addNewRow('epp_detail_cc_tran',coreWebApp.ModelBo);                              
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);                          
                            r.parent_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                        }
                    }
                    
                    if(jsonResult['pay_detail'].length > 0){
                        coreWebApp.ModelBo.ot_rate(jsonResult['pay_detail'][0]['ot_rate']);
                        coreWebApp.ModelBo.ot_holiday_rate(jsonResult['pay_detail'][0]['ot_holiday_rate']);
                        coreWebApp.ModelBo.ot_special_rate(jsonResult['pay_detail'][0]['ot_special_rate']);
                        coreWebApp.ModelBo.pay_schedule_desc(jsonResult['pay_detail'][0]['pay_schedule_desc']);
                    }
                    coreWebApp.ModelBo.epp_detail_emo_tran.valueHasMutated();
                    coreWebApp.ModelBo.epp_detail_ded_tran.valueHasMutated();
                    coreWebApp.ModelBo.epp_detail_cc_tran.valueHasMutated();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
            }
        });
        return 'OK';
    }
   
    core_epp.pay_schedule_update = pay_schedule_update;
    
    function get_pay_schedule_detail(){        
        if(coreWebApp.ModelBo.pay_schedule_id() == -1 || coreWebApp.ModelBo.pay_schedule_id() == null){            
            return 'Pay Schedule Details','Select Pay Schedule Schedule to get details';
        }
        else{
            $.ajax({
                    url: '?r=core/hr/form/getpayscheduledetails',
                    type: 'GET',
                    data: {'pay_schedule_id': coreWebApp.ModelBo.pay_schedule_id()},
                    complete:function(){coreWebApp.stoploading();},
                    success: function (resultdata) {
                        var jsonResult = $.parseJSON(resultdata);
                        if(jsonResult['status'] === 'ok'){
                            //remove all Pay Schedule Details 
                            coreWebApp.ModelBo.pay_schedule_detail_temp.removeAll();

                            //update Pay Schedule Detail
                            for(var p = 0; p < jsonResult['pay_detail'].length; ++p)
                            {                        
                                var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_temp',coreWebApp.ModelBo);                              
                                r.step_id(jsonResult['pay_detail'][p]['step_id']);                          
                                r.parent_details(jsonResult['pay_detail'][p]['parent_details']);
                                r.description(jsonResult['pay_detail'][p]['description']);
                                r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                                r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                                r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                                r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                                r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                                r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                                r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                                r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                                r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                                r.amt(jsonResult['pay_detail'][p]['amt']);
                                r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                                coreWebApp.ModelBo.pay_schedule_detail_temp.valueHasMutated();
                            }
                            coreWebApp.ModelBo.pay_schedule_desc(jsonResult['pay_schedule_desc']);
//                            applysmartcontrols();
                        }
                    },
                    error: function (data) {
                        coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
                    }
                });
            return 'OK';
        }
    }
    
    core_epp.get_pay_schedule_detail=get_pay_schedule_detail;
    
    // Methods used to copy Pay Schedule Ends    
    
}(window.core_epp));
