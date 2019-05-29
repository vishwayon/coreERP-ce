/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


// Declare core_st Namespace
window.cwf_company = {};
(function (cwf_company) { 
    
    function enable_visible(dataItem) { 
        if(coreWebApp.ModelBo.company_id()==-1){
            return true;            
        }
        else {
            return false;
        }
    };
    
    cwf_company.enable_visible=enable_visible;
    
      
}(window.cwf_company));
