typeof window.core_sys == 'undefined' ? window.core_sys = {} : '';
window.core_sys.wf_ar = {};

(function (wf_ar) {

    function wf_ar_ui(opts) {
        opts.module = 'cwf/sys';
        opts.alloc_view = 'wfApproval/WfApprView';
        opts.call_init = wf_ar_init;
        opts.call_update = wf_ar_update;
        coreWebApp.showAllocV2(opts);
    }
    wf_ar.wf_ar_ui = wf_ar_ui;

    function wf_ar_init(opts, after_init) {
        var wf_ar_alloc = new function () {
            self = this;
        };
        //wf_ar_alloc.wf_ar_temp = {};
        wf_ar_alloc.customer_id = opts.customer_id;
        wf_ar_alloc.order_amt = ko.observable(0);
        wf_ar_alloc.order_val = opts.order_val;
        wf_ar_alloc.balance = ko.observable(0);
        wf_ar_alloc.excess_val = ko.observable(0);
        wf_ar_alloc.cl_to_user = ko.observable('');
        wf_ar_alloc.cl_user_to = ko.observable(-1);
        wf_ar_alloc.cl_reqd = ko.observable(false);
        wf_ar_alloc.inv_id = ko.observable('');
        wf_ar_alloc.inv_date = ko.observable('1970-01-01');
        wf_ar_alloc.overdue_amt = ko.observable(01);
        wf_ar_alloc.overdue_days = ko.observable(0);
        wf_ar_alloc.io_to_user = ko.observable('');
        wf_ar_alloc.io_user_to = ko.observable(-1);
        wf_ar_alloc.io_reqd = ko.observable(false);
        wf_ar_alloc.io_range_exhausted = ko.observable(false);
        wf_ar_alloc.cl_range_exhausted = ko.observable(false);
        opts.model = wf_ar_alloc;
        wf_ar.get_detail();
    }
    wf_ar.wf_ar_init = wf_ar_init;

    function get_detail() {
        $.ajax({
            url: '?r=cwf/sys/wf-approval/validate-wf-ar-data',
            type: 'GET',
            dataType: 'json',
            data: {
                customer_id: self.customer_id,
                order_val: self.order_val
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    self.balance(jsonResult['balance']);
                    self.order_amt(jsonResult['order_val']);
                    self.excess_val(jsonResult['excess_val']);
                    self.cl_to_user(jsonResult['cl_to_user']);
                    self.cl_user_to(jsonResult['cl_user_to']);
                    self.cl_reqd(jsonResult['cl_reqd']);
                    self.overdue_days(jsonResult['overdue_days']);
                    self.inv_id(jsonResult['inv_id']);
                    self.inv_date(jsonResult['inv_date']);
                    self.overdue_amt(jsonResult['overdue_amt']);
                    self.io_to_user(jsonResult['io_to_user']);
                    self.io_user_to(jsonResult['io_user_to']);
                    self.io_reqd(jsonResult['io_reqd']);
                    self.io_range_exhausted(jsonResult['io_range_exhausted']);
                    self.cl_range_exhausted(jsonResult['cl_range_exhausted']);

                    if (parseFloat(self.balance()) > 0) {
                        $('#balance').css("color", "green");
                    } else {
                        $('#balance').css("color", "red");
                    }

                    if (parseFloat(self.excess_val()) > 0) {
                        $('#excess_val').css("color", "green");
                    } else {
                        $('#excess_val').css("color", "red");
                    }

                    $('#wf-ar-loading').hide();
                }
                if ($('#cdUpdate').length > 0) {
                    $('#cdUpdate').html('<span class="ui-button-text">Request Approval</span>');
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    wf_ar.get_detail = get_detail;

    function wf_ar_update(opts) {
        if (opts.model.io_reqd() || opts.model.cl_reqd()) {
            var params = Object();
            params.doc_id = coreWebApp.ModelBo.__doc_id();
            params.doc_date = coreWebApp.ModelBo.doc_date();
            params.route = $('#formModulePath').val();
            params.formname = $('#summaryformName').val();
            params.formparams = coreWebApp.ModelBo.Params;
            params.bo_id = $('#formHeader').val();
            //these 3 fields are to be provided by validation logic
            params.wf_desc = 'Approve Limit';
            params.cl_user_to = opts.model.cl_user_to();
            params.io_user_to = opts.model.io_user_to();

            var res = JSON.stringify(params);
            $.ajax({
                url: '?r=cwf/sys/wf-approval/request-approval',
                type: 'GET',
                dataType: 'json',
                data: {params: res},
                success: function (json_result) {
                    if (json_result.status == 'OK') {
                        coreWebApp.ModelBo.docSecurity.allowSave(false);
                        coreWebApp.ModelBo.docSecurity.allowPost(false);
                        coreWebApp.ModelBo.docSecurity.allowDelete(false);
                        typeof opts.req_after_update_callback != 'undefined' ? opts.req_after_update_callback() : '';
                        coreWebApp.toastmsg('message', 'Request Approval', 'Approval request sent successfully.');
                    }
                }
            });
        }
        delete opts.model; // remove the temporary model created
        return true;
    }
    wf_ar.wf_ar_update = wf_ar_update;

    function get_wf_appr_dt(voucher_id) {
        $.ajax({
            url: '?r=cwf/sys/wf-approval/get-wf-ar-data',
            type: 'GET',
            dataType: 'json',
            data: {'doc_id': voucher_id},
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.wf_appr_temp.removeAll();
                    jsonResult.dt_wf_ar.forEach(itm => {
                        var newOtran = coreWebApp.ModelBo.addNewRow('wf_appr_temp', coreWebApp.ModelBo, true, false);
                        newOtran.user_to(itm.user_to);
                        newOtran.user_from(itm.user_from);
                        newOtran.wf_approved(itm.wf_approved);
                        newOtran.doc_id(itm.doc_id);
                        newOtran.from_user(itm.from_user);
                        newOtran.to_user(itm.to_user);
                        newOtran.wf_comment(itm.wf_comment);
                        newOtran.appr_status(itm.appr_status);
                        newOtran.appr_type(itm.appr_type);
                        newOtran.added_on(itm.added_on);
                        newOtran.acted_on(itm.acted_on);
                        newOtran.is_acted(itm.is_acted);

                    });
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Approval', 'Failed to fetch Approval details', false);
            }
        });
    }
    wf_ar.get_wf_appr_dt = get_wf_appr_dt;

    function visible_io(dataItem) {
        if (dataItem.io_reqd() && !dataItem.cl_range_exhausted() && !dataItem.io_range_exhausted()) {
            return true;
        }
        return false;
    }
    wf_ar.visible_io = visible_io;

    function visible_cl(dataItem) {
        if (dataItem.cl_reqd() && !dataItem.cl_range_exhausted() && !dataItem.io_range_exhausted()) {
            return true;
        }
        return false;
    }
    wf_ar.visible_cl = visible_cl;

    function visible_limit(dataItem) {
        if (dataItem.cl_range_exhausted() && dataItem.io_range_exhausted()) {
            $('#cdUpdate').hide();
            $('#limit_hdr').text("All limits Exceeded. Cannot seek approvals");
            return true;
        }
        if (dataItem.cl_range_exhausted()) {
            $('#cdUpdate').hide();
            $('#limit_hdr').text("Credit limit Exceeded. Cannot seek approvals");
            return true;
        }
        if (dataItem.io_range_exhausted()) {
            $('#cdUpdate').hide();
            $('#limit_hdr').text("Overdue Invoice limit Exceeded. Cannot seek approvals");
            return true;
        }
        if (!dataItem.cl_reqd() && !dataItem.io_reqd()) {
            $('#cdUpdate').hide();
            $('#limit_hdr').text("Credit Limit Available No need of Approval");
            return true;
        }
        $('#cdUpdate').show();
        return false;
    }
    wf_ar.visible_limit = visible_limit;

    function visible_warning(dataItem) {
        if ((dataItem.cl_reqd() || dataItem.io_reqd()) && !dataItem.cl_range_exhausted() && !dataItem.io_range_exhausted()) {
            return true;
        }
        return false;
    }
    wf_ar.visible_warning = visible_warning;

    function enable_wf_ar(row) {
        if (coreWebApp.ModelBo.status() < 5) {
            if (coreWebApp.ModelBo.wf_appr_temp().length > 0) {
                var cnt = 0;
                var added_on;
                for (i = 0; i < coreWebApp.ModelBo.wf_appr_temp().length; i++) {
                    var itm = coreWebApp.ModelBo.wf_appr_temp()[i];
                    if (cnt == 0) {
                        added_on = itm['added_on']();
                        if (itm['wf_approved']() == 1) {
                            if (coreWebApp.ModelBo.docSecurity.allowSave()) {
                                coreWebApp.ModelBo.docSecurity.allowSave(false);
                            }
                            return false;
                        }
                        if (itm['wf_approved']() == 0) {
                            if (coreWebApp.ModelBo.docSecurity.allowSave()) {
                                coreWebApp.ModelBo.docSecurity.allowSave(false);
                            }
                            if (coreWebApp.ModelBo.docSecurity.allowPost()) {
                                coreWebApp.ModelBo.docSecurity.allowPost(false);
                            }
                            if (coreWebApp.ModelBo.docSecurity.allowDelete()) {
                                coreWebApp.ModelBo.docSecurity.allowDelete(false);
                            }
                            return false;
                        }
                    } else {
                        if (added_on == itm['added_on']()) {
                            if (itm['wf_approved']() == 1) {
                                if (coreWebApp.ModelBo.docSecurity.allowSave()) {
                                    coreWebApp.ModelBo.docSecurity.allowSave(false);
                                }
                                return false;
                            }
                            if (itm['wf_approved']() == 0) {
                                if (coreWebApp.ModelBo.docSecurity.allowSave()) {
                                    coreWebApp.ModelBo.docSecurity.allowSave(false);
                                }
                                if (coreWebApp.ModelBo.docSecurity.allowPost()) {
                                    coreWebApp.ModelBo.docSecurity.allowPost(false);
                                }
                                if (coreWebApp.ModelBo.docSecurity.allowDelete()) {
                                    coreWebApp.ModelBo.docSecurity.allowDelete(false);
                                }
                                return false;
                            }
                        }
                    }
                    cnt = cnt + 1;
                }
            }
            return true;
        }
    }
    wf_ar.enable_wf_ar = enable_wf_ar;
}(window.core_sys.wf_ar));


