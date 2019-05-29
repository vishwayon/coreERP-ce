// Declare st Namespace
typeof window.st == 'undefined' ? window.st = {} : '';
window.st.mrgp = {};

(function (mrgp) {
    mrgp.sl_no = 0;

    function after_load() {
        mrgp.sl_no = coreWebApp.ModelBo.mrgp_tran().length;
        if (coreWebApp.ModelBo.status() != 5 && coreWebApp.ModelBo.doc_stage_id() != 'outward') {
            $('#cmd_addnew_mrgp_tran').hide();
        } else {

            $('#cmd_addnew_mrgp_tran').show();
        }
    }
    mrgp.after_load = after_load;

    function mrgp_tran_delete() {
        mrgp.sl_no = 0;
        coreWebApp.ModelBo.mrgp_tran().forEach(function (row) {
            mrgp.sl_no += 1;
            row.sl_no(mrgp.sl_no);
        });
    }
    mrgp.mrgp_tran_delete = mrgp_tran_delete;

    function mrgp_tran_add(row) {
        mrgp.sl_no += 1;
        row.sl_no(mrgp.sl_no);
    }
    mrgp.mrgp_tran_add = mrgp_tran_add;
    
    function inward_stage_visible(row) {
        if (coreWebApp.ModelBo.doc_stage_id() != 'outward') {
            return true;
        }
        return false;
    }
    mrgp.inward_stage_visible = inward_stage_visible;

    function outward_stage_enable(row) {
        if (coreWebApp.ModelBo.doc_stage_id() == 'outward') {
            return true;
        }
        return false;
    }
    mrgp.outward_stage_enable = outward_stage_enable;

}(window.st.mrgp));
