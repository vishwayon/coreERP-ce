typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.lc_type = {};

(function (lc_type) {

    function exp_ac_enable() {
        return coreWebApp.ModelBo.jdata.req_alloc() || coreWebApp.ModelBo.jdata.post_gl();
    }
    lc_type.exp_ac_enable = exp_ac_enable;

    function liab_ac_enable() {
        return coreWebApp.ModelBo.jdata.post_gl();
    }
    lc_type.liab_ac_enable = liab_ac_enable;

}(window.core_st.lc_type)); 