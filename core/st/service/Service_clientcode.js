// Declare core_ar Namespace
window.core_service = {};
(function (core_service) {

    function enable_fp_price() {
        if (coreWebApp.ModelBo.annex_info.sale_price.price_type() == "FP") {
            return true;
        }
        return false;
    }
    core_service.enable_fp_price = enable_fp_price;

    function war_info_enabled() {
        return coreWebApp.ModelBo.annex_info.war_info.has_war();
    }
    core_service.war_info_enabled = war_info_enabled;


}(window.core_service));
