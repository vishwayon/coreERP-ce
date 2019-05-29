// Declare core_hr Namespace. This is module level script//

window.core_hr = {};

(function (core_hr) {
    
    
    function ppt_acc_combo_filter(fltr){
        if(coreWebApp.ModelBo.txn_type()==0){
            fltr=' account_type_id in(1, 2)';
        }
        if(coreWebApp.ModelBo.txn_type()==1){
            fltr=' account_type_id not in (0, 1, 2, 7, 12, 45)';
        }            
        return fltr;
    }
    
    core_hr.ppt_acc_combo_filter=ppt_acc_combo_filter;
    
}(window.core_hr));

 