// Declare core_ap Namespace
typeof window.core_fa == 'undefined' ? window.core_fa = {} : '';

(function (core_fa) {
    
    function calculate_dep() {
        $.ajax({
            url: '?r=core%2Ffa%2Fform%2Fcalculatedep',
            type: 'GET',
            data: {'depDateFrom': coreWebApp.ModelBo.dep_date_from(),'depDateTo':coreWebApp.ModelBo.dep_date_to()},
            beforeSend:function(xhr, opts){
                if(coreWebApp.ModelBo.dep_date_from() > coreWebApp.ModelBo.dep_date_to()){
                    coreWebApp.toastmsg('error','Calculate Dep Error','Dep Date To should be greater than Dep Date From.',false);
                    xhr.abort();  
                }
                else{
                coreWebApp.startloading();}},
            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                 
                if(jsonResult['status'] === 'ok'){
                    coreWebApp.ModelBo.asset_dep_ledger.removeAll();
                    
                    for(var p = 0; p < jsonResult['asset_dep_ledger'].length; ++p)
                    {
                        var r = coreWebApp.ModelBo.addNewRow('asset_dep_ledger',coreWebApp.ModelBo);
                        
                        r.asset_book_id(jsonResult['asset_dep_ledger'][p]['asset_book_id']);
                        r.asset_class_id(jsonResult['asset_dep_ledger'][p]['asset_class_id']);
                        r.asset_item_id(jsonResult['asset_dep_ledger'][p]['asset_item_id']);
                        r.asset_name(jsonResult['asset_dep_ledger'][p]['asset_name']);
                        r.asset_class(jsonResult['asset_dep_ledger'][p]['asset_class']);
                        r.asset_book(jsonResult['asset_dep_ledger'][p]['asset_book']);
                        r.dep_amt(jsonResult['asset_dep_ledger'][p]['dep_amt']);
                    }
                    
                    coreWebApp.ModelBo.asset_dep_ledger.valueHasMutated();
                    coreWebApp.ModelBo.ad_tran.removeAll();
                    
                    for(var p = 0; p < jsonResult['ad_tran'].length; ++p)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('ad_tran',coreWebApp.ModelBo);
                        r1.sl_no(jsonResult['ad_tran'][p]['sl_no']);
                        r1.asset_book_id(jsonResult['ad_tran'][p]['asset_book_id']);
                        r1.asset_book(jsonResult['ad_tran'][p]['asset_book']);
                        r1.asset_class_id(jsonResult['ad_tran'][p]['asset_class_id']);
                        r1.asset_class(jsonResult['ad_tran'][p]['asset_class']);
                        r1.dep_account_id(jsonResult['ad_tran'][p]['dep_account_id']);
                        r1.dep_account(jsonResult['ad_tran'][p]['dep_account']);
                        r1.acc_dep_account_id(jsonResult['ad_tran'][p]['acc_dep_account_id']);
                        r1.acc_dep_account(jsonResult['ad_tran'][p]['acc_dep_account']);
                        r1.dep_amt(jsonResult['ad_tran'][p]['dep_amt']);
                    }
                    coreWebApp.ModelBo.ad_tran.valueHasMutated();
//                    applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Filter','Failed with errors on server',false);
            }
        });
    }    
    core_fa.calculate_dep = calculate_dep;
    
    function ap_calculate_before_tax_amt(dataItem) {
        ap_calculate_disc_amt(dataItem);
    };

    core_fa.ap_calculate_before_tax_amt=ap_calculate_before_tax_amt;
    
    
    function ap_calculate_disc_amt(dataItem) { 
        console.log('ap_calculate_disc_amt');
        console.log(dataItem.gross_credit_amt());        
        console.log(dataItem.gross_credit_amt());
        dataItem.disc_amt((parseFloat(dataItem.gross_credit_amt()) * parseFloat(dataItem.disc_pcnt()))/100);  
        ap_calculate_total(dataItem);
    };

    core_fa.ap_calculate_disc_amt=ap_calculate_disc_amt;
        
    function ap_calculate_total(dataItem){
        dataItem.before_tax_amt(parseFloat(dataItem.gross_credit_amt()) - parseFloat(dataItem.disc_amt()) + parseFloat(dataItem.round_off_amt()) + parseFloat(dataItem.misc_taxable_amt()));
        dataItem.total_purchase_amt(parseFloat(dataItem.gross_credit_amt()) - parseFloat(dataItem.disc_amt()) + parseFloat(dataItem.round_off_amt()) + parseFloat(dataItem.tax_amt()) + parseFloat(dataItem.lc_amt()));
        dataItem.credit_amt(parseFloat(dataItem.before_tax_amt()) + parseFloat(dataItem.tax_amt()) + parseFloat(dataItem.misc_non_taxable_amt()));
        dataItem.net_credit_amt(parseFloat(dataItem.credit_amt()));        
    }
            
    core_fa.ap_calculate_total=ap_calculate_total;
    
            
    function ap_discount_percent_enable(dataItem) { 
        if(dataItem.disc_is_value()){
            return false;            
        }
        else {
            return true;
        }
    };
    
    core_fa.ap_discount_percent_enable=ap_discount_percent_enable;
    
    function ap_discount_enable(dataItem) { 
        if(dataItem.disc_is_value()){
            dataItem.disc_pcnt(0);
            return true;            
        }
        else {
            return false;
        }
    };
     
    core_fa.ap_discount_enable=ap_discount_enable;
        
    function ap_liability_acc_enable(dataItem) {   
       if(typeof dataItem.supplier_paid=='undefined')return;
       if(dataItem.supplier_paid() == false){
           return true;            
       }
       else {
           dataItem.account_affected_id(-1);
           return false;
       }
    };
    
    core_fa.ap_liability_acc_enable=ap_liability_acc_enable;
    
    function ap_lc_taxable_enable(dataItem) {   
       if(typeof dataItem.supplier_paid=='undefined')return;
       if(dataItem.supplier_paid() == true){
           return true;            
       }
       else {
           dataItem.is_taxable(false);
           return false;
       }
    };
    
    core_fa.ap_lc_taxable_enable=ap_lc_taxable_enable;
    
    function ap_tran_add_new_row(newRow) {         
        newRow.use_start_date(coreWebApp.ModelBo.doc_date());
    }
    
    core_fa.ap_tran_add_new_row = ap_tran_add_new_row;    
    
    function asset_purchase_account_combo_filter(fltr){
        if(coreWebApp.ModelBo.en_purchase_type()==0){
            fltr=' account_type_id = 2 ';
        }
        else if(coreWebApp.ModelBo.en_purchase_type()==1){
            fltr=' account_type_id = 1 ';
        }  
        else if(coreWebApp.ModelBo.en_purchase_type()==2){
            fltr=' account_type_id = 12';
        }
        else if(coreWebApp.ModelBo.en_purchase_type()==3){
            fltr=' account_type_id not in (0, 1, 2, 7, 12, 23, 24, 21, 22, 18, 38)';
        } else {
            fltr += " account_type_id = -1 ";
        }                 
        return fltr;
    }
    
    core_fa.asset_purchase_account_combo_filter=asset_purchase_account_combo_filter;
    
    function asset_sale_account_combo_filter(fltr){
        if(coreWebApp.ModelBo.en_sales_type()==0){
            fltr=' account_type_id = 2 ';
        }
        if(coreWebApp.ModelBo.en_sales_type()==1){
            fltr=' account_type_id = 1 ';
        }  
        if(coreWebApp.ModelBo.en_sales_type()==2){
            fltr=' account_type_id = 7';
        }
        if(coreWebApp.ModelBo.en_sales_type()==3){
            fltr=' account_type_id not in (0, 1, 2, 7, 12, 23, 24, 21, 22, 18, 38)';
        }                      
        return fltr;
    }
    
    core_fa.asset_sale_account_combo_filter=asset_sale_account_combo_filter;
    
    
    function enable_sale_amt(dataItem) {            
        if(dataItem.selected() == true){
            return true;            
        }
        else {
            return false;
        }
     };
     
    core_fa.enable_sale_amt=enable_sale_amt  
    
    function subclass_filter(fltr, dataItem) {
        if(parseInt(dataItem.asset_class_id()) !== -1) {
            fltr = ' asset_class_id = ' + dataItem.asset_class_id();
        }
        return fltr;
    }
    core_fa.subclass_filter = subclass_filter;
    
}(window.core_fa));


