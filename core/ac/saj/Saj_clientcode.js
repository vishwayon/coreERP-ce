// Declare core_ap Namespace
typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
typeof window.core_ac.saj == 'undefined' ? window.core_ac.saj = {} : '';

(function (saj) {

    saj.sl_no = 0;

    function afterload() {
        saj.sl_no = coreWebApp.ModelBo.saj_tran().length;
    }
    saj.afterload = afterload;

    function sub_head_filter(fltr, dataItem) {
        fltr = ' account_id = ' + dataItem.account_id();
        return fltr;
    }
    saj.sub_head_filter = sub_head_filter;
    
    function saj_tran_delete() {
        saj.sl_no = 0;
        $.each(coreWebApp.ModelBo.saj_tran(), function (idx, row) {
            saj.sl_no += 1;
            row.sl_no(saj.sl_no);
        });
    }
    saj.saj_tran_delete = saj_tran_delete;

    function saj_tran_add(row) {
        saj.sl_no += 1;
        row.sl_no(saj.sl_no);
    }
    saj.saj_tran_add = saj_tran_add;

}(window.core_ac.saj));


