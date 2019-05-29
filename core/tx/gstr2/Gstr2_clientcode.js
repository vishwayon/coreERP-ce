// Declare core_tx Namespace
typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.gstr2 = {};

(function (gstr2) {

    function pre_process_click() {
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-pending-doc-view',
            method: 'GET',
            success: function (html) {
                $('#div_pending_doc').html(html);
                get_pending_doc_data();
            }
        });
    }
    gstr2.pre_process_click = pre_process_click;

    function get_pending_doc_data() {
        var dataParams = {
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period(),
            gt: coreWebApp.ModelBo.gt(),
            cur_gt: coreWebApp.ModelBo.cur_gt()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-pending-doc-data',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model.dt_pending_doc = ko.mapping.fromJS(jdata);
                ko.cleanNode($('#div_pending_doc')[0]);
                ko.applyBindings(gstr_model, $('#div_pending_doc')[0]);
            }
        });
    }

    function view_gstr2_summary_click() {
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-summary-view',
            method: 'GET',
            success: function (html) {
                $('#div_pending_doc').html('');
                $('#div_gstr2_summary').html(html);
                get_gstr2_summary_data();
            }
        });
    }
    gstr2.view_gstr2_summary_click = view_gstr2_summary_click;

    function get_gstr2_summary_data() {
        var dataParams = {
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-summary-data',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model = ko.mapping.fromJS(jdata);
                ko.cleanNode($('#div_gstr2_summary')[0]);
                ko.applyBindings(gstr_model, $('#div_gstr2_summary')[0]);
            }
        });
    }

    function get_gstr2_detail_data() {
        var dataParams = {
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-detail-data',
            method: 'GET',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                $('#gstr2_json_data').val(jdata);
            }
        });
    }
    gstr2.get_gstr2_detail_data = get_gstr2_detail_data;

    function get_gstr2_detail_file() {
        if (coreWebApp.ModelBo.ret_status() != 1) { // Allow for Json download only when return status is 1
            coreWebApp.toastmsg('warning', 'Get JSON Data', 'Save the return before getting JSON data');
            return;
        }
        var dataParams = {
            gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr2-detail-file',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                var link = document.createElement('a');
                link.setAttribute("href", jdata.filePath);
                link.setAttribute("id", "gstr2_file_link");
                link.setAttribute("download", jdata.fileName);
                var cnt = document.getElementById('content-root');
                cnt.appendChild(link);
                link.click();
            }
        });
    }
    gstr2.get_gstr2_detail_file = get_gstr2_detail_file;

    function get_col_total(col_name, data) {
        var total = new Number(0.00);
        data().forEach(function (row) {
            total += parseFloat(row[col_name]());
        });
        return total;
    }
    gstr2.get_col_total = get_col_total;

    function gstr2_gstn_upload() {
        var dataParams = {
            gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/upload-gstn-gstr2',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            complete: function (jqXHR, textStatus) {
                coreWebApp.stoploading();
            },
            beforeSend: function (xhr) {
                coreWebApp.startloading();
            },
            success: function (result) {
                if (result.status == 'OK') {
                    coreWebApp.ModelBo.annex_info.gstr2a_reco_info.gstn_ret_ref_id(result.ref_id);
                    coreWebApp.toastmsg('success', 'GSTR2', 'GSTR2 uploaded to GSTN successfully');
                } else {
                    coreWebApp.toastmsg('error', 'GSTR2', 'GSTR2 upload to GSTN failed');
                }
            }
        });
    }
    gstr2.gstr2_gstn_upload = gstr2_gstn_upload;

    function gstn_ret_status() {
        gstn_ret_ref_id = coreWebApp.ModelBo.annex_info.gstr2a_reco_info.gstn_ret_ref_id();
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-ret-status',
            method: 'GET',
            dataType: 'json',
            data: {gstn_ret_ref_id: gstn_ret_ref_id, reqtime: new Date().getTime()},
            complete: function (jqXHR, textStatus) {
                coreWebApp.stoploading();
            },
            beforeSend: function (xhr) {
                coreWebApp.startloading();
            },
            success: function (result) {
                if (result.status == 'OK') {
                    coreWebApp.toastmsg('success', 'GSTR', result.retstatusinfo);
                } else {
                    coreWebApp.toastmsg('error', 'GSTR', 'GSTR status check failed');
                }
            }
        });
    }
    gstr2.gstn_ret_status = gstn_ret_status;

    function view_status_toggle() {
        if ($('#gstn_auth').val() == 'true') {
            if (typeof coreWebApp.ModelBo.annex_info.gstr2a_reco_info.gstn_ret_ref_id() != 'undefined' &&
                    coreWebApp.ModelBo.annex_info.gstr2a_reco_info.gstn_ret_ref_id() != '') {
                return true;
            }
        }
        return false;
    }
    gstr2.view_status_toggle = view_status_toggle;

}(window.core_tx.gstr2));

