// Declare core_loan Namespace

window.core_leave = {};

(function (core_leave) {   
    
    function replacement_req(dataItem) { 
        if(dataItem.replacement_required())
        {
            return true;            
        }
        else 
        {
            return false;
        }
    };
    
    core_leave.replacement_req=replacement_req;
     
    function enable_rejoin_date(dataItem) { 
       if(typeof dataItem.is_rejoin_date=='undefined')return;
        if(dataItem.is_rejoin_date() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_leave.enable_rejoin_date=enable_rejoin_date   
    
    function enable_authorised_date(dataItem) { 
       if(typeof dataItem.is_authorised_on=='undefined')return;
        if(dataItem.is_authorised_on() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_leave.enable_authorised_date=enable_authorised_date 
    
    function leave_authorised_combo_filter(fltr){
        fltr=' employee_id != '+coreWebApp.ModelBo.employee_id();
        return fltr;
    }
    
    core_leave.leave_authorised_combo_filter = leave_authorised_combo_filter
    
    
}(window.core_leave));