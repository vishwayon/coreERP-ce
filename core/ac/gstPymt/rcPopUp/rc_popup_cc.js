typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';
typeof window.core_ac.gst_pymt == 'undefined' ? window.core_ac.gst_pymt = {} : '';
window.core_ac.gst_pymt.rc_popup = {}; 

(function (rc_popup) {
    
    // opts structure {
    //      row: current row being edited (model.row)
    // }
    function select_rc_info(opts) {
        opts.module = 'core/ac';
        opts.alloc_view = 'gstPymt/rcPopUp/RcPopUp';
        opts.call_init = rc_init;
        opts.call_update = rc_update;
        coreWebApp.showAllocV2(opts);
    }
    rc_popup.select_rc_info = select_rc_info;
    
    function rc_init(opts, after_init) {
        var rc_sel = new function () {
            self = this;
        };
        rc_sel.rc_sec_id = ko.observable(opts.row.gtt_rc_sec_id());
        rc_sel.supp_name = ko.observable(opts.row.supp_name());
        rc_sel.supp_addr = ko.observable(opts.row.supp_addr());
        opts.model = rc_sel;
    }
    
    function rc_update(opts) {
        if(rc_valid(opts)) {
            opts.row.gtt_rc_sec_id(opts.model.rc_sec_id());
            opts.row.supp_name(opts.model.supp_name());
            opts.row.supp_addr(opts.model.supp_addr());
            return true;
        }
        return false;
    }
    
    function rc_valid(opts) {
        if(opts.model.rc_sec_id() == -1) {
            coreWebApp.toastmsg('warning', 'Reverse Charge Information', 'Select valid section for Reverse Charge');
            return false;
        }
        if(opts.model.supp_name().length == 0 || opts.model.supp_addr().length == 0) {
            coreWebApp.toastmsg('warning', 'Reverse Charge Information', 'Enter Supplier Name/Address');
            return false;
        }
        return true;
    }
    
    
    
} (window.core_ac.gst_pymt.rc_popup));


