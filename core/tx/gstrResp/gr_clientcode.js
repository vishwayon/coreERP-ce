window.tx_gr = {};

(function (tx_gr) {

    function gstr_resp_view_click() {
        if ($('#gstr_resp_file').val() == '') {
            coreWebApp.toastmsg('error', 'File upload', 'Select a file to proceed');
            return;
        }
        var fd = new FormData(document.getElementById('fileupload'));
        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=core/tx/gst-return/gstr-resp-parse',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            dataType: 'json',
            success: function (result) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model.dt = ko.mapping.fromJS(result.dt);
                ko.cleanNode($('#rptRoot')[0]);
                ko.applyBindings(gstr_model, $('#rptRoot')[0]);
            }
        });
    }
    tx_gr.gstr_resp_view_click = gstr_resp_view_click;

    function gstr2a_resp_view_click() {
        if ($('#gstr_resp_file').val() == '') {
            coreWebApp.toastmsg('error', 'File upload', 'Select a file to proceed');
            return;
        }
        var fd = new FormData(document.getElementById('fileupload'));
        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=core/tx/gst-return/gstr2a-resp-parse',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            dataType: 'json',
            success: function (result) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model.dt = ko.mapping.fromJS(result.dt);
                ko.cleanNode($('#rptRoot')[0]);
                ko.applyBindings(gstr_model, $('#rptRoot')[0]);
            }
        });
    }
    tx_gr.gstr2a_resp_view_click = gstr2a_resp_view_click;

    function gstr2a_req_resp_click() {
        $('#cmd_reqgstr2a').attr('disabled', 'true');
        $('#cmd_reqgstr2a').hide();
        $('#div_res_reqgstr2a').show();
        $('#res_reqgstr2a').html('Request sent. Awaiting response...');
        var fd = {
            username: $('#username').val(),
            statecd: $('#statecd').val(),
            gstin: $('#gstin').val(),
            retperiod: $('#retperiod').val(),
            txn: $('#gstn_txn').val()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-req-gstr2a',
            type: "GET",
            data: {'data': JSON.stringify(fd), 'reqtime': new Date().getTime()},
            dataType: 'json',
            success: function (result) {
                if (result.status == 'OK') {
                    var gstr_model = new function () {
                        self = this;
                    };
                    gstr_model.dt = ko.mapping.fromJS(result.dt);
                    ko.cleanNode($('#rptRoot')[0]);
                    ko.applyBindings(gstr_model, $('#rptRoot')[0]);
                    $('#cmd_reqgstr2a').removeAttr('disabled');
                    $('#div_res_reqgstr2a').hide();
                    $('#cmd_reqgstr2a').show();
                } else {
                    $('#cmd_reqgstr2a').show();
                    $('#res_reqgstr2a').html(result.message + '<br/><br/>' + result.error.message);
                    $('#div_res_reqgstr2a').show();
                    $('#cmd_reqgstr2a').removeAttr('disabled');
                }
            },
            error: function (data) {
                $('#cmd_reqgstr2a').show();
                $('#res_reqgstr2a').html('Request failed.');
                $('#div_res_reqgstr2a').show();
                $('#cmd_reqgstr2a').removeAttr('disabled');
            }
        });
    }
    tx_gr.gstr2a_req_resp_click = gstr2a_req_resp_click;

    function applySmartControls() {
        $('#rptOptions').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });
    }
    tx_gr.applySmartControls = applySmartControls;

}(window.tx_gr));