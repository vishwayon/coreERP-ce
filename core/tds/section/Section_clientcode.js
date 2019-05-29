// Declare core_ac Namespace. This is module level script//
window.core_section = {};

(function (core_section) {        
    function enable_for_new(dataItem) { 
        if(parseFloat(coreWebApp.ModelBo.section_id()) == -1){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_section.enable_for_new=enable_for_new  
     
}(window.core_section));


