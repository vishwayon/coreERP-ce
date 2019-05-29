// Declare core_leavetype Namespace

window.core_leavetype = {};

(function (core_leavetype) {
    
    function enable_pay_percent(dataItem) { 
        if(typeof dataItem.paid_leave=='undefined')return;
        if(dataItem.paid_leave() == true){
            return true;            
        }
        else {
            dataItem.pay_percent(0);
            return false;
        }
     };
     
    core_leavetype.enable_pay_percent=enable_pay_percent ;
    
    function enable_carry_forward_limit(dataItem) { 
        if(typeof dataItem.carry_forward_at_yearend=='undefined')return;
        if(dataItem.carry_forward_at_yearend() == true){
            return true;            
        }
        else {
            dataItem.carry_forward_limit(0);
            return false;
        }
     };
     
    core_leavetype.enable_carry_forward_limit=enable_carry_forward_limit ;
    
    function enable_carry_forward(dataItem) { 
        if(typeof dataItem.en_entitlement_type=='undefined')return;
        if(dataItem.en_entitlement_type() == '1'){
            return true;            
        } 
        return false;        
     };
     
    core_leavetype.enable_carry_forward=enable_carry_forward ;
    
    function change_entitlement_type(dataItem) { 
//        if(typeof dataItem.en_entitlement_type=='undefined')return;
//        if(dataItem.en_entitlement_type() == '2'){
//            dataItem.installment_principal(0);
//            dataItem.installment_interest(0);
//        }
     };
     
    core_leavetype.change_entitlement_type=change_entitlement_type ;
    
 }(window.core_leavetype));