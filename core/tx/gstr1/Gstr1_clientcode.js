// Declare core_tx Namespace
typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.gstr1 = {};

(function (gstr1) {

    function pre_process_click() {
        $.ajax({
            url: '?r=core/tx/gst-return/get-pending-doc-view',
            method: 'GET',
            success: function (html) {
                $('#div_pending_doc').html(html);
                get_pending_doc_data();
            }
        });
    }
    gstr1.pre_process_click = pre_process_click;

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
            url: '?r=core/tx/gst-return/get-pending-doc-data',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model.pending = ko.mapping.fromJS(jdata.pending);
                gstr_model.si = ko.mapping.fromJS(jdata.si);
                ko.cleanNode($('#div_pending_doc')[0]);
                ko.applyBindings(gstr_model, $('#div_pending_doc')[0]);
            }
        });
    }

    function view_gstr1_summary_click() {
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr1-summary-view',
            method: 'GET',
            success: function (html) {
                $('#div_pending_doc').html('');
                $('#div_gstr1_summary').html(html);
                get_gstr1_summary_data();
            }
        });
    }
    gstr1.view_gstr1_summary_click = view_gstr1_summary_click;

    function get_gstr1_summary_data() {
        var dataParams = {
            gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period(),
            gt: coreWebApp.ModelBo.annex_info.gt(),
            cur_gt: coreWebApp.ModelBo.annex_info.cur_gt()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr1-summary-data',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model = ko.mapping.fromJS(jdata);
                ko.cleanNode($('#div_gstr1_summ')[0]);
                ko.applyBindings(gstr_model, $('#div_gstr1_summ')[0]);
            }
        });
    }

    function get_gstr1_detail_file() {
        if(coreWebApp.ModelBo.ret_status() != 1) { // Allow for Json download only when return status is 1
            coreWebApp.toastmsg('warning', 'Get JSON Data', 'Save the return before getting JSON data');
            return;
        }
        $('#div_gstr1_json_errors').hide();
        var dataParams = {
            gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
            gst_state_id: coreWebApp.ModelBo.gst_state_id(),
            ret_period_from: coreWebApp.ModelBo.ret_period_from(),
            ret_period_to: coreWebApp.ModelBo.ret_period_to(),
            ret_period: coreWebApp.ModelBo.ret_period(),
            gt: coreWebApp.ModelBo.gt(),
            cur_gt: coreWebApp.ModelBo.cur_gt()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr1-detail-file',
            method: 'GET',
            dataType: 'json',
            data: {jsonParams: JSON.stringify(dataParams)},
            success: function (jdata) {
                if(jdata.status != 'OK') {
                    // File has errors. Display errors
                    var gstr_model = new function () {
                        self = this;
                    };
                    gstr_model = ko.mapping.fromJS(jdata);
                    ko.cleanNode($('#div_gstr1_json_errors')[0]);
                    ko.applyBindings(gstr_model, $('#div_gstr1_json_errors')[0]);
                    $('#div_gstr1_json_errors').show();
                }
                // Always download the file
                var link = document.createElement('a');
                link.setAttribute("href", jdata.filePath);
                link.setAttribute("id", "gstr1_file_link");
                link.setAttribute("download", jdata.fileName);
                var cnt = document.getElementById('content-root');
                cnt.appendChild(link);
                link.click();
            }
        });
    }
    gstr1.get_gstr1_detail_file = get_gstr1_detail_file;

    function get_col_total(col_name, data) {
        var total = new Number(0.00);
        data().forEach(function (row) {
            if(Array.isArray(col_name)) {
                $.each(col_name, function (id, col) {
                   total += parseFloat(row[col]()); 
                });
            } else {
                total += parseFloat(row[col_name]());
            }
        });
        return total;
    }
    gstr1.get_col_total = get_col_total;

}(window.core_tx.gstr1));

