
// Declare core_employee Namespace

window.core_employee = {};

(function (core_employee) {
    
    function enable_resign_date(dataItem) { 
       if(typeof dataItem.is_resign_date=='undefined')return;
        if(dataItem.is_resign_date() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_employee.enable_resign_date=enable_resign_date   

}(window.core_employee));