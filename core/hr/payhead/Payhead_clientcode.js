window.core_payhead = {};

(function (core_payhead) {  
    
    function enable_gratuity(dataItem) { 
       if(typeof dataItem.calc_type=='undefined')return;
        if(dataItem.calc_type() == 'Allowance'){
            dataItem.incl_in_gratuity(0);
            return false;            
        }
        else {
            return true;
        }
    };
    
    core_payhead.enable_gratuity=enable_gratuity;   
     
    function enable_leave(dataItem) { 
       if(typeof dataItem.calc_type=='undefined')return;
        if(dataItem.calc_type() == 'Allowance'){    
            dataItem.incl_in_leave(0);
            return false;            
        }
        else {
            return true;
        }
    };
    
    core_payhead.enable_leave=enable_leave;  
     
    function enable_nopay(dataItem) { 
       if(typeof dataItem.calc_type=='undefined')return;
        if(dataItem.calc_type() == 'Allowance'){
            dataItem.incl_in_nopay(0);
            return false;            
        }
        else {
            return true;
        }
    };
    
   core_payhead.enable_nopay=enable_nopay;  
    
}(window.core_payhead));
